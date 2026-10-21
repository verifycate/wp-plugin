<?php
/**
 * Cached certificate catalogue.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Api;

use Verifycate\Sdk\Exception\ApiException;
use Verifycate\Sdk\Exception\TransportException;
use Verifycate\Support\Logger;
use Verifycate\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the workspace certificate list for mapping screens and wallet links.
 */
final class CertificateCatalog {

	/**
	 * Cache lifetime in seconds.
	 */
	private const TTL = 300;

	/**
	 * Client factory.
	 *
	 * @var ClientFactory
	 */
	private ClientFactory $factory;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param ClientFactory $factory Client factory.
	 * @param Settings      $settings Settings.
	 */
	public function __construct( ClientFactory $factory, Settings $settings ) {
		$this->factory  = $factory;
		$this->settings = $settings;
	}

	/**
	 * All certificates in the workspace.
	 *
	 * Returns a list of arrays with id, name and status. Failures are logged and
	 * degrade to an empty list so a settings screen still renders.
	 *
	 * @param bool $force Bypass the cache.
	 * @return array<int, array{id: string, name: string, status: string}>
	 */
	public function all( bool $force = false ): array {
		if ( ! $this->factory->isConfigured() ) {
			return array();
		}

		$key    = $this->cacheKey();
		$cached = get_transient( $key );

		if ( ! $force && is_array( $cached ) ) {
			/**
			 * Cached certificate list.
			 *
			 * @var array<int, array{id: string, name: string, status: string}> $cached
			 */
			return $cached;
		}

		try {
			$client       = $this->factory->make();
			$certificates = array();
			$offset       = 0;
			$total        = 0;
			$fetched      = 0;

			do {
				$page     = $client->certificates()->list( null, 200, $offset );
				$total    = $page->getTotal();
				$fetched += count( $page->getItems() );

				foreach ( $page->getItems() as $certificate ) {
					$certificates[] = array(
						'id'     => $certificate->getId(),
						'name'   => $certificate->getName(),
						'status' => $certificate->getStatus(),
					);
				}

				$offset += 200;
			} while ( $fetched < $total && $offset < 1000 );

			usort(
				$certificates,
				static function ( array $left, array $right ): int {
					return strcasecmp( $left['name'], $right['name'] );
				}
			);

			set_transient( $key, $certificates, self::TTL );

			return $certificates;
		} catch ( ApiException $exception ) {
			Logger::warning(
				'Could not load the certificate catalogue.',
				array(
					'status' => $exception->getStatusCode(),
					'error'  => $exception->getMessage(),
				)
			);
		} catch ( TransportException $exception ) {
			Logger::warning( 'Could not reach the API for the certificate catalogue.', array( 'error' => $exception->getMessage() ) );
		} catch ( \Throwable $exception ) {
			Logger::warning( 'Unexpected error loading the certificate catalogue.', array( 'error' => $exception->getMessage() ) );
		}

		if ( is_array( $cached ) ) {
			/**
			 * Cached certificate list.
			 *
			 * @var array<int, array{id: string, name: string, status: string}> $cached
			 */
			return $cached;
		}

		return array();
	}

	/**
	 * Find one certificate in the catalogue.
	 *
	 * @param string $id Certificate id.
	 * @return array{id: string, name: string, status: string}|null
	 */
	public function find( string $id ): ?array {
		foreach ( $this->all() as $certificate ) {
			if ( $certificate['id'] === $id ) {
				return $certificate;
			}
		}

		return null;
	}

	/**
	 * Attribute definitions for a certificate, for the mapping editor.
	 *
	 * @param string $id Certificate id.
	 * @return array<int, array{key: string, label: string}>
	 */
	public function attributes( string $id ): array {
		$key    = 'verifycate_attributes_' . md5( $this->settings->getWorkspace() . '|' . $id );
		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			/**
			 * Cached attribute definitions.
			 *
			 * @var array<int, array{key: string, label: string}> $cached
			 */
			return $cached;
		}

		if ( ! $this->factory->isConfigured() ) {
			return array();
		}

		try {
			$attributes = array();
			foreach ( $this->factory->make()->certificates()->get( $id )->getAttributes() as $attribute ) {
				$attribute_key = isset( $attribute['key'] ) && is_scalar( $attribute['key'] ) ? (string) $attribute['key'] : '';
				if ( '' === $attribute_key ) {
					continue;
				}

				$attributes[] = array(
					'key'   => $attribute_key,
					'label' => isset( $attribute['label'] ) && is_scalar( $attribute['label'] )
						? (string) $attribute['label']
						: $attribute_key,
				);
			}

			set_transient( $key, $attributes, self::TTL );

			return $attributes;
		} catch ( ApiException $exception ) {
			Logger::warning(
				'Could not load certificate attributes.',
				array(
					'certificate' => $id,
					'status'      => $exception->getStatusCode(),
					'error'       => $exception->getMessage(),
				)
			);
		} catch ( TransportException $exception ) {
			Logger::warning(
				'Could not reach the API for certificate attributes.',
				array(
					'certificate' => $id,
					'error'       => $exception->getMessage(),
				)
			);
		} catch ( \Throwable $exception ) {
			Logger::warning(
				'Unexpected error loading certificate attributes.',
				array(
					'certificate' => $id,
					'error'       => $exception->getMessage(),
				)
			);
		}

		return array();
	}

	/**
	 * Invalidate the catalogue.
	 *
	 * @return void
	 */
	public function flush(): void {
		delete_transient( $this->cacheKey() );
	}

	/**
	 * Workspace-scoped cache key.
	 *
	 * @return string
	 */
	private function cacheKey(): string {
		return 'verifycate_catalog_' . md5( $this->settings->getWorkspace() . '|' . $this->settings->getBaseUrl() );
	}
}
