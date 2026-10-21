<?php
/**
 * Plugin settings.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Support;

use Verifycate\Sdk\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the plugin's single option.
 */
final class Settings {

	/**
	 * Option name.
	 */
	public const OPTION = 'verifycate_settings';

	/**
	 * Default recipient portal URL.
	 */
	public const DEFAULT_PORTAL_URL = 'https://verifycate.com';

	/**
	 * Stored values.
	 *
	 * @var array<string, mixed>
	 */
	private array $values;

	/**
	 * Load settings, optionally from a supplied array for tests.
	 *
	 * @param array<string, mixed>|null $values Values to use instead of the option.
	 */
	public function __construct( ?array $values = null ) {
		if ( null !== $values ) {
			$this->values = $values;

			return;
		}

		$stored       = get_option( self::OPTION, array() );
		$this->values = is_array( $stored ) ? $stored : array();
	}

	/**
	 * Workspace slug.
	 *
	 * @return string
	 */
	public function getWorkspace(): string {
		return isset( $this->values['workspace'] ) && is_scalar( $this->values['workspace'] )
			? (string) $this->values['workspace']
			: '';
	}

	/**
	 * Decrypted workspace API key.
	 *
	 * @return string
	 */
	public function getApiKey(): string {
		$stored = isset( $this->values['api_key'] ) && is_scalar( $this->values['api_key'] )
			? (string) $this->values['api_key']
			: '';

		return Crypto::decrypt( $stored );
	}

	/**
	 * API key suitable for display: masked with the last four characters.
	 *
	 * @return string
	 */
	public function getMaskedApiKey(): string {
		$key = $this->getApiKey();

		return '' === $key ? '' : str_repeat( '•', 8 ) . substr( $key, -4 );
	}

	/**
	 * API base URL.
	 *
	 * @return string
	 */
	public function getBaseUrl(): string {
		$value = isset( $this->values['base_url'] ) && is_scalar( $this->values['base_url'] )
			? (string) $this->values['base_url']
			: '';

		return '' === $value ? Config::DEFAULT_BASE_URL : $value;
	}

	/**
	 * Recipient portal URL used for public certificate links.
	 *
	 * @return string
	 */
	public function getPortalUrl(): string {
		$value = isset( $this->values['portal_url'] ) && is_scalar( $this->values['portal_url'] )
			? (string) $this->values['portal_url']
			: '';

		return '' === $value ? self::DEFAULT_PORTAL_URL : $value;
	}

	/**
	 * Whether the site has enough configuration to call the API.
	 *
	 * @return bool
	 */
	public function isConnected(): bool {
		return '' !== $this->getWorkspace() && '' !== $this->getApiKey();
	}

	/**
	 * Whether uninstall should remove settings and data.
	 *
	 * @return bool
	 */
	public function shouldDeleteDataOnUninstall(): bool {
		return ! empty( $this->values['delete_data'] );
	}

	/**
	 * Last connection test result.
	 *
	 * @return array<string, mixed>
	 */
	public function getLastCheck(): array {
		$value = $this->values['last_check'] ?? array();

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Save a connection test result without touching the rest of the settings.
	 *
	 * @param bool   $ok Whether the check succeeded.
	 * @param string $message Result message.
	 * @return void
	 */
	public function setLastCheck( bool $ok, string $message ): void {
		$this->values['last_check'] = array(
			'ok'      => $ok,
			'message' => $message,
			'time'    => time(),
		);

		$this->persist();
	}

	/**
	 * Save sanitized settings from a form submission.
	 *
	 * An empty API-key field keeps the stored key. This lets the settings screen
	 * show a masked placeholder without erasing the secret on every save.
	 *
	 * @param array<string, mixed> $input Raw form input.
	 * @return void
	 */
	public function save( array $input ): void {
		$workspace = isset( $input['workspace'] ) && is_scalar( $input['workspace'] )
			? sanitize_title( (string) $input['workspace'] )
			: '';

		$api_key = isset( $input['api_key'] ) && is_scalar( $input['api_key'] )
			? trim( (string) $input['api_key'] )
			: '';

		$base_url = isset( $input['base_url'] ) && is_scalar( $input['base_url'] )
			? esc_url_raw( (string) $input['base_url'] )
			: '';

		$portal_url = isset( $input['portal_url'] ) && is_scalar( $input['portal_url'] )
			? esc_url_raw( (string) $input['portal_url'] )
			: '';

		$this->values['workspace']   = $workspace;
		$this->values['base_url']    = '' === $base_url ? Config::DEFAULT_BASE_URL : $base_url;
		$this->values['portal_url']  = '' === $portal_url ? self::DEFAULT_PORTAL_URL : $portal_url;
		$this->values['delete_data'] = ! empty( $input['delete_data'] );

		if ( '' !== $api_key && false === strpos( $api_key, '•' ) ) {
			$this->values['api_key'] = Crypto::encrypt( $api_key );
		}

		$this->persist();
	}

	/**
	 * Remove the stored API key while keeping the workspace and URLs.
	 *
	 * @return void
	 */
	public function disconnect(): void {
		unset( $this->values['api_key'], $this->values['last_check'] );

		$this->persist();
	}

	/**
	 * Raw stored values, for diagnostics only.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return $this->values;
	}

	/**
	 * Persist the current values.
	 *
	 * @return void
	 */
	private function persist(): void {
		update_option( self::OPTION, $this->values, false );
	}
}
