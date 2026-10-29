<?php
/**
 * Uninstall routine for Verifycate.
 *
 * Runs only when the plugin is deleted from the WordPress admin, and only when
 * the site owner opted in to data removal on the Setup screen.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$verifycate_settings = get_option( 'verifycate_settings' );

if ( ! is_array( $verifycate_settings ) || empty( $verifycate_settings['delete_data'] ) ) {
	$verifycate_cleanup_enabled = false;
} else {
	$verifycate_cleanup_enabled = true;
}

/**
 * Remove this site's Verifycate data.
 *
 * @return void
 */
$verifycate_cleanup = static function (): void {
	global $wpdb;

	$settings = get_option( 'verifycate_settings' );
	if ( ! is_array( $settings ) || empty( $settings['delete_data'] ) ) {
		return;
	}

	wp_clear_scheduled_hook( 'verifycate_process_queue' );

	delete_option( 'verifycate_settings' );
	delete_option( 'verifycate_db_version' );
	delete_transient( 'verifycate_queue_lock' );

	foreach ( array( 'mappings', 'jobs', 'logs' ) as $verifycate_table ) {
		$verifycate_name = $wpdb->prefix . 'verifycate_' . $verifycate_table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $verifycate_name ) );
	}
};

if ( $verifycate_cleanup_enabled || is_multisite() ) {
	if ( is_multisite() ) {
		$verifycate_sites = get_sites( array( 'fields' => 'ids' ) );

		foreach ( $verifycate_sites as $verifycate_site_id ) {
			switch_to_blog( (int) $verifycate_site_id );
			$verifycate_cleanup();
			restore_current_blog();
		}
	} else {
		$verifycate_cleanup();
	}
}
