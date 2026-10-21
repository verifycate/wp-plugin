<?php
/**
 * Verifycate API client factory.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Api;

use Verifycate\Sdk\Client;
use Verifycate\Sdk\Config;
use Verifycate\Sdk\Exception\ApiException;
use Verifycate\Sdk\Exception\TransportException;
use Verifycate\Support\Logger;
use Verifycate\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Builds an SDK client from the saved settings.
 */
final class ClientFactory {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Whether the workspace and API key are configured.
	 *
	 * @return bool
	 */
	public function isConfigured(): bool {
		return $this->settings->isConnected();
	}

	/**
	 * Build a client.
	 *
	 * @return Client
	 */
	public function make(): Client {
		$config = new Config(
			$this->settings->getApiKey(),
			$this->settings->getWorkspace(),
			$this->settings->getBaseUrl(),
			20.0,
			sprintf(
				'verifycate-wp/%s (WordPress %s; PHP %s)',
				VERIFYCATE_VERSION,
				get_bloginfo( 'version' ),
				PHP_VERSION
			)
		);

		return new Client( $config, new WordPressTransport() );
	}

	/**
	 * Call the API with the saved credentials and report the result.
	 *
	 * @return array{ok: bool, message: string, total: int}
	 */
	public function testConnection(): array {
		if ( ! $this->isConfigured() ) {
			$result = array(
				'ok'      => false,
				'message' => __( 'Add a workspace slug and API key before testing the connection.', 'verifycate' ),
				'total'   => 0,
			);

			$this->settings->setLastCheck( false, $result['message'] );

			return $result;
		}

		try {
			$page   = $this->make()->certificates()->list( null, 1 );
			$result = array(
				'ok'      => true,
				'message' => sprintf(
					/* translators: %d: number of certificates in the workspace. */
					_n( 'Connected. %d certificate is available.', 'Connected. %d certificates are available.', $page->getTotal(), 'verifycate' ),
					$page->getTotal()
				),
				'total'   => $page->getTotal(),
			);
		} catch ( ApiException $exception ) {
			$result = array(
				'ok'      => false,
				'message' => $this->describeApiError( $exception ),
				'total'   => 0,
			);

			Logger::error(
				'Connection test failed.',
				array(
					'status' => $exception->getStatusCode(),
					'error'  => $exception->getMessage(),
				)
			);
		} catch ( TransportException $exception ) {
			$result = array(
				'ok'      => false,
				'message' => sprintf(
					/* translators: %s: transport error message. */
					__( 'Could not reach the Verifycate API: %s', 'verifycate' ),
					$exception->getMessage()
				),
				'total'   => 0,
			);

			Logger::error( 'Connection test could not reach the API.', array( 'error' => $exception->getMessage() ) );
		} catch ( \Throwable $exception ) {
			$result = array(
				'ok'      => false,
				'message' => __( 'The Verifycate API returned an unexpected response. Check the Logs screen for details.', 'verifycate' ),
				'total'   => 0,
			);

			Logger::error( 'Connection test failed with an unexpected error.', array( 'error' => $exception->getMessage() ) );
		}

		$this->settings->setLastCheck( $result['ok'], $result['message'] );

		return $result;
	}

	/**
	 * Turn an API error into an actionable admin message.
	 *
	 * @param ApiException $exception API error.
	 * @return string
	 */
	private function describeApiError( ApiException $exception ): string {
		if ( $exception->isUnauthorized() ) {
			return __( 'The API key was rejected. Check that it belongs to this workspace and has not been revoked.', 'verifycate' );
		}

		if ( $exception->isPaymentRequired() ) {
			return __( 'This workspace needs an active Verifycate plan before certificates can be issued.', 'verifycate' );
		}

		if ( $exception->isForbidden() ) {
			return __( 'The API key is valid but does not have permission to read certificates.', 'verifycate' );
		}

		$detail = $exception->getDetail();
		if ( '' !== $detail ) {
			return $detail;
		}

		return sprintf(
			/* translators: %d: HTTP status code. */
			__( 'The Verifycate API returned HTTP %d.', 'verifycate' ),
			$exception->getStatusCode()
		);
	}
}
