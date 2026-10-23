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
	public const DEFAULT_PORTAL_URL = Urls::SITE_URL;

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
	 * Whether an API key is stored.
	 *
	 * @return bool
	 */
	public function hasApiKey(): bool {
		return '' !== $this->getApiKey();
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
	 * API key permissions reported by the API.
	 *
	 * @return array<int, string>
	 */
	public function getPermissions(): array {
		$value = $this->values['permissions'] ?? array();

		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_map( 'strval', $value ) );
	}

	/**
	 * Whether the API key has unrestricted workspace access.
	 *
	 * @return bool
	 */
	public function hasFullAccess(): bool {
		if ( ! array_key_exists( 'full_access', $this->values ) && ! array_key_exists( 'permissions', $this->values ) ) {
			return true;
		}

		return ! empty( $this->values['full_access'] );
	}

	/**
	 * Whether the workspace has an active seat. Older settings are treated as live.
	 *
	 * @return bool
	 */
	public function isSeatLive(): bool {
		return ! array_key_exists( 'seat_live', $this->values ) || ! empty( $this->values['seat_live'] );
	}

	/**
	 * Whether the stored key can both read and write certificates.
	 *
	 * @return bool
	 */
	public function canWriteCertificates(): bool {
		return $this->hasFullAccess() || in_array( 'certificates:write', $this->getPermissions(), true );
	}

	/**
	 * Whether the connection is ready to issue certificates.
	 *
	 * @return bool
	 */
	public function isReadyToIssue(): bool {
		return $this->isConnected() && $this->isSeatLive() && $this->canWriteCertificates();
	}

	/**
	 * Human-readable reason the connection cannot issue, if any.
	 *
	 * @return string
	 */
	public function connectionIssue(): string {
		if ( ! $this->isConnected() ) {
			return '';
		}

		if ( ! $this->isSeatLive() ) {
			return __( 'This workspace has no active Verifycate plan. Activate a plan before issuing certificates.', 'verifycate' );
		}

		if ( ! $this->canWriteCertificates() ) {
			return __( 'This API key can read certificates but cannot issue them. Create a key with the certificates:write scope and reconnect.', 'verifycate' );
		}

		return '';
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
		$workspace_input = '';
		if ( isset( $input['workspace_override'] ) && is_scalar( $input['workspace_override'] ) ) {
			$workspace_input = (string) $input['workspace_override'];
		} elseif ( isset( $input['workspace'] ) && is_scalar( $input['workspace'] ) ) {
			$workspace_input = (string) $input['workspace'];
		}

		$workspace = self::normalizeWorkspace( $workspace_input );

		$api_key = isset( $input['api_key'] ) && is_scalar( $input['api_key'] )
			? trim( (string) $input['api_key'] )
			: '';

		$base_url = isset( $input['base_url'] ) && is_scalar( $input['base_url'] )
			? esc_url_raw( (string) $input['base_url'] )
			: '';

		$portal_url = isset( $input['portal_url'] ) && is_scalar( $input['portal_url'] )
			? esc_url_raw( (string) $input['portal_url'] )
			: '';

		if ( '' !== $workspace ) {
			$this->values['workspace'] = $workspace;
		} else {
			unset( $this->values['workspace'] );
		}

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
		unset(
			$this->values['api_key'],
			$this->values['last_check'],
			$this->values['permissions'],
			$this->values['full_access'],
			$this->values['seat_live'],
			$this->values['connected_at']
		);

		$this->persist();
	}

	/**
	 * Persist the workspace and API-key metadata resolved from the API.
	 *
	 * @param string             $slug Workspace slug.
	 * @param array<int, string> $permissions API key permissions.
	 * @param bool               $full_access Whether the key has full access.
	 * @param bool               $seat_live Whether the workspace has an active seat.
	 * @return void
	 */
	public function setResolvedWorkspace( string $slug, array $permissions, bool $full_access, bool $seat_live ): void {
		$this->values['workspace']    = sanitize_title( $slug );
		$this->values['permissions']  = array_values( array_map( 'strval', $permissions ) );
		$this->values['full_access']  = $full_access;
		$this->values['seat_live']    = $seat_live;
		$this->values['connected_at'] = time();

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
	 * Accept a workspace slug or a dashboard URL and return the slug.
	 *
	 * Examples:
	 *   acme
	 *   https://app.verifycate.com/acme
	 *   https://app.verifycate.com/acme/api-keys
	 *   app.verifycate.com/acme
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function normalizeWorkspace( string $value ): string {
		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		$has_scheme     = 1 === preg_match( '#^https?://#i', $value );
		$looks_like_url = $has_scheme
			|| false !== strpos( $value, 'verifycate.' )
			|| false !== strpos( $value, 'app.' );

		if ( $looks_like_url ) {
			$url  = $has_scheme ? $value : 'https://' . ltrim( $value, '/' );
			$path = (string) wp_parse_url( $url, PHP_URL_PATH );
			$path = trim( $path, '/' );

			if ( '' === $path ) {
				return '';
			}

			$segments = explode( '/', $path );
			$value    = rawurldecode( (string) $segments[0] );
		} elseif ( false !== strpos( $value, '/' ) ) {
			$value = explode( '/', $value )[0];
		}

		return sanitize_title( $value );
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
