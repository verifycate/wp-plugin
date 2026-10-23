<?php
/**
 * Local wp-env loopback shim.
 *
 * WordPress runs inside a container while the site URL is localhost:8888 on
 * the host. Route loopback HTTP requests through the host gateway so LearnPress
 * and Site Health can reach the local site.
 *
 * Install as an mu-plugin in the WordPress container:
 *
 *   npx wp-env run cli sh -c 'mkdir -p /var/www/html/wp-content/mu-plugins && cp /var/www/html/wp-content/plugins/wp-plugin/tests/e2e/local-loopback.php /var/www/html/wp-content/mu-plugins/'
 *
 * @package Verifycate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

/**
 * Rewrite localhost loopback requests to the Docker host gateway.
 *
 * @param mixed                $pre  Short-circuit response.
 * @param array<string, mixed> $args Request arguments.
 * @param string               $url  Request URL.
 * @return mixed
 */
function verifycate_local_loopback_request( $pre, array $args, string $url ) {
	if ( 0 !== strpos( $url, 'http://localhost:8888' ) ) {
		return $pre;
	}

	$rewritten                  = str_replace( 'http://localhost:8888', 'http://host.docker.internal:8888', $url );
	$args['reject_unsafe_urls'] = false;

	remove_filter( 'pre_http_request', 'verifycate_local_loopback_request', 10 );
	$response = wp_remote_request( $rewritten, $args );
	add_filter( 'pre_http_request', 'verifycate_local_loopback_request', 10, 3 );

	return $response;
}

add_filter( 'pre_http_request', 'verifycate_local_loopback_request', 10, 3 );
