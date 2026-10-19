<?php
/**
 * Plugin bootstrap.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates plugin bootstrapping and hook registration.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor to enforce the singleton.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of the singleton.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Boot the plugin: register hooks and load translations.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load the plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'verifycate',
			false,
			dirname( VERIFYCATE_BASENAME ) . '/languages'
		);
	}
}
