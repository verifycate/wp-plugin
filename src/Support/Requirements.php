<?php
/**
 * Runtime requirement checks.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies the host meets the plugin's minimum requirements.
 */
final class Requirements {

	/**
	 * Minimum supported WordPress version.
	 */
	public const MIN_WP = '6.2';

	/**
	 * Minimum supported PHP version.
	 */
	public const MIN_PHP = '7.4';

	/**
	 * Determine whether the current environment meets requirements.
	 *
	 * On failure, an admin notice is queued and the plugin boot is skipped.
	 *
	 * @return bool
	 */
	public static function met(): bool {
		global $wp_version;

		if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
			add_action( 'admin_notices', array( self::class, 'php_notice' ) );

			return false;
		}

		if ( isset( $wp_version ) && version_compare( (string) $wp_version, self::MIN_WP, '<' ) ) {
			add_action( 'admin_notices', array( self::class, 'wp_notice' ) );

			return false;
		}

		return true;
	}

	/**
	 * Render the PHP version admin notice.
	 *
	 * @return void
	 */
	public static function php_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: required PHP version, 2: current PHP version. */
					__( 'Verifycate requires PHP %1$s or higher. This site is running PHP %2$s.', 'verifycate' ),
					self::MIN_PHP,
					PHP_VERSION
				)
			)
		);
	}

	/**
	 * Render the WordPress version admin notice.
	 *
	 * @return void
	 */
	public static function wp_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		global $wp_version;

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: required WordPress version, 2: current WordPress version. */
					__( 'Verifycate requires WordPress %1$s or higher. This site is running WordPress %2$s.', 'verifycate' ),
					self::MIN_WP,
					(string) $wp_version
				)
			)
		);
	}
}
