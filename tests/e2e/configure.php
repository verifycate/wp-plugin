<?php
/**
 * Point the local plugin at the mock Verifycate API.
 *
 * @package Verifycate
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$settings = new Verifycate\Support\Settings();
$settings->save(
	array(
		'workspace'  => 'acme',
		'api_key'    => 'vc_test_key',
		'base_url'   => 'http://host.docker.internal:4567',
		'portal_url' => 'http://localhost:8888',
	)
);

echo 'configured';
