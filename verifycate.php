<?php
/**
 * Plugin Name:       Verifycate
 * Plugin URI:        https://verifycate.com/wordpress
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
 * Network:           false
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
 * `composer install`.
 *
 * @return void
 */
function autoload(): void {
	if ( is_readable( VERIFYCATE_DIR . 'vendor/autoload.php' ) ) {
		require_once VERIFYCATE_DIR . 'vendor/autoload.php';
		return;
	}

	spl_autoload_register(
		static function ( string $class ): void {
			$prefix = __NAMESPACE__ . '\\';
			if ( 0 !== strncmp( $prefix, $class, strlen( $prefix ) ) ) {
				return;
			}

			$relative = substr( $class, strlen( $prefix ) );
			$path     = VERIFYCATE_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	);
}

autoload();

if ( ! Support\Requirements::met() ) {
	return;
}

add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance()->boot();
	}
);
