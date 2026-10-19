<?php
/**
 * PHPUnit bootstrap for unit tests.
 *
 * These tests cover pure PHP logic and do not boot WordPress. A WordPress
 * test harness for integration tests (wp-phpunit) is added when the API client
 * and LMS adapters land.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$verifycate_polyfills = dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
if ( is_readable( $verifycate_polyfills ) ) {
	require_once $verifycate_polyfills;
}
