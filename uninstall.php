<?php
/**
 * Uninstall routine for Verifycate.
 *
 * Runs only when the plugin is deleted from the WordPress admin.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/*
 * The plugin stores no data yet. When it begins storing options, user meta,
 * transients, scheduled events or custom tables, remove them here. Never delete
 * data unless the site owner opted in via a "remove data on uninstall" setting.
 */
