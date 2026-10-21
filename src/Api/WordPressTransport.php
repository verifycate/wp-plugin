<?php
/**
 * WordPress HTTP transport for the Verifycate SDK.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Api;

use Verifycate\Sdk\Exception\TransportException;
use Verifycate\Sdk\Http\Request;
use Verifycate\Sdk\Http\Response;
use Verifycate\Sdk\Http\TransportInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Routes SDK requests through wp_remote_request.
 *
 * Using the WordPress HTTP API keeps the site's proxy, TLS, timeout and
 * filtering behaviour in charge of outbound traffic.
 */
final class WordPressTransport implements TransportInterface {

	/**
	 * Send a request through the WordPress HTTP API.
	 *
	 * @param Request $request SDK request.
	 * @return Response
	 *
	 * @throws TransportException When WordPress cannot complete the request.
	 */
	public function send( Request $request ): Response {
		$args = array(
			'method'      => $request->getMethod(),
			'headers'     => $request->getHeaders(),
			'timeout'     => $request->getTimeout(),
			'redirection' => 0,
			'sslverify'   => true,
		);

		if ( null !== $request->getBody() ) {
			$args['body'] = $request->getBody();
		}

		$response = wp_remote_request( $request->getUrl(), $args );

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The message is not rendered as HTML.
			throw new TransportException( $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = (string) wp_remote_retrieve_body( $response );

		if ( $status <= 0 ) {
			throw new TransportException( 'The Verifycate API returned an empty HTTP response.' );
		}

		$headers = wp_remote_retrieve_headers( $response );
		/**
		 * Normalized response headers.
		 *
		 * @var array<string, array<int, string>> $normalized
		 */
		$normalized = array();

		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			/**
			 * All response headers.
			 *
			 * @var array<string, array<int, string>> $all
			 */
			$all = $headers->getAll();
			foreach ( $all as $name => $values ) {
				$normalized[ strtolower( (string) $name ) ] = array_values( array_map( 'strval', (array) $values ) );
			}
		} elseif ( is_array( $headers ) || $headers instanceof \Traversable ) {
			foreach ( $headers as $name => $values ) {
				$normalized[ strtolower( (string) $name ) ] = array_values( array_map( 'strval', (array) $values ) );
			}
		}

		return new Response( $status, $normalized, $body );
	}
}
