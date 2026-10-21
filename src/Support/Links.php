<?php
/**
 * Deep links into the Verifycate app.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Builds app and docs links for the setup screens.
 */
final class Links {

	/**
	 * Verifycate app base URL.
	 *
	 * The API host is used to pick the development app, and a filter lets hosts
	 * point at a self-hosted or staging app.
	 *
	 * @param Settings $settings Settings.
	 * @return string
	 */
	public static function appUrl( Settings $settings ): string {
		$api = strtolower( $settings->getBaseUrl() );
		$app = 'https://app.verifycate.com';

		if ( false !== strpos( $api, '.dev' ) || false !== strpos( $api, 'localhost' ) || false !== strpos( $api, 'host.docker.internal' ) ) {
			$app = 'https://app.verifycate.dev';
		}

		/**
		 * Filter the Verifycate app base URL used by setup links.
		 *
		 * @param string   $app App base URL.
		 * @param Settings $settings Settings.
		 */
		$app = (string) apply_filters( 'verifycate_app_url', $app, $settings );

		return untrailingslashit( $app );
	}

	/**
	 * Workspace API keys screen.
	 *
	 * @param Settings $settings Settings.
	 * @return string
	 */
	public static function apiKeysUrl( Settings $settings ): string {
		$workspace = $settings->getWorkspace();
		$app       = self::appUrl( $settings );

		return '' === $workspace ? $app : $app . '/' . rawurlencode( $workspace ) . '/api-keys';
	}

	/**
	 * Workspace certificate list.
	 *
	 * @param Settings $settings Settings.
	 * @return string
	 */
	public static function certificatesUrl( Settings $settings ): string {
		$workspace = $settings->getWorkspace();
		$app       = self::appUrl( $settings );

		return '' === $workspace ? $app : $app . '/' . rawurlencode( $workspace ) . '/certificates';
	}

	/**
	 * Create-certificate screen.
	 *
	 * @param Settings $settings Settings.
	 * @return string
	 */
	public static function newCertificateUrl( Settings $settings ): string {
		$workspace = $settings->getWorkspace();
		$app       = self::appUrl( $settings );

		return '' === $workspace ? $app : $app . '/' . rawurlencode( $workspace ) . '/create-certificate';
	}

	/**
	 * Wallet shortcode.
	 *
	 * @return string
	 */
	public static function walletShortcode(): string {
		return '[verifycate_wallet]';
	}
}
