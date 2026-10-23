<?php
/**
 * Plugin Name:       Verifycate
 * Plugin URI:        https://verifycate.com
 * Description:       Issue verifiable certificates from your WordPress LMS. Connects LearnDash, Tutor LMS, LearnPress, LifterLMS, Sensei and more to Verifycate.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Verifycate
 * Author URI:        https://verifycate.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       verifycate
 * Domain Path:       /languages
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate;

defined( 'ABSPATH' ) || exit;

define( 'VERIFYCATE_VERSION', '0.1.0' );
define( 'VERIFYCATE_FILE', __FILE__ );
define( 'VERIFYCATE_DIR', plugin_dir_path( __FILE__ ) );
define( 'VERIFYCATE_URL', plugin_dir_url( __FILE__ ) );
define( 'VERIFYCATE_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Register the plugin's PSR-4 autoloader.
 *
 * Prefers the Composer autoloader when present and falls back to a minimal
 * class loader so the plugin still runs from a fresh checkout without
 * `composer install`. The Verifycate PHP SDK is a Composer dependency; the
 * fallback cannot load it, so Requirements shows an admin notice in that case.
 *
 * @return void
 */
function autoload(): void {
	if ( is_readable( VERIFYCATE_DIR . 'vendor/autoload.php' ) ) {
		require_once VERIFYCATE_DIR . 'vendor/autoload.php';
		return;
	}

	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = __NAMESPACE__ . '\\';
			if ( 0 !== strncmp( $prefix, $class_name, strlen( $prefix ) ) ) {
				return;
			}

			$relative = substr( $class_name, strlen( $prefix ) );
			$path     = VERIFYCATE_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	);
}

autoload();

register_activation_hook( __FILE__, array( Install::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Install::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( Install::class, 'maybeUpgrade' ), 5 );
// phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- The one-minute schedule is registered by Install::addSchedule().
add_filter( 'cron_schedules', array( Install::class, 'addSchedule' ) );

if ( ! Support\Requirements::met() ) {
	return;
}

add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain( 'verifycate', false, dirname( VERIFYCATE_BASENAME ) . '/languages' );
	},
	1
);

add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance()->boot();
	}
);
