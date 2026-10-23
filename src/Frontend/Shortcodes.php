<?php
/**
 * Public certificate shortcodes.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Frontend;

use Verifycate\Api\ClientFactory;
use Verifycate\Sdk\Dto\PublicCertificate;
use Verifycate\Sdk\Exception\ApiException;
use Verifycate\Sdk\Exception\TransportException;
use Verifycate\Support\Logger;
use Verifycate\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a single public certificate and a verification lookup form.
 */
final class Shortcodes {

	/**
	 * Public certificate cache lifetime.
	 */
	private const TTL = 300;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Client factory.
	 *
	 * @var ClientFactory
	 */
	private ClientFactory $factory;

	/**
	 * Constructor.
	 *
	 * @param Settings      $settings Settings.
	 * @param ClientFactory $factory Client factory.
	 */
	public function __construct( Settings $settings, ClientFactory $factory ) {
		$this->settings = $settings;
		$this->factory  = $factory;
	}

	/**
	 * Register shortcodes and shared styles.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'verifycate_certificate', array( $this, 'certificateShortcode' ) );
		add_shortcode( 'verifycate_verify', array( $this, 'verifyShortcode' ) );
	}

	/**
	 * Render one public certificate.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function certificateShortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'public_id' => '',
				'workspace' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'verifycate_certificate'
		);

		$public_id = sanitize_text_field( (string) $atts['public_id'] );
		$workspace = sanitize_title( (string) $atts['workspace'] );

		if ( '' === $public_id ) {
			return $this->adminNote( __( 'Add a public_id to the [verifycate_certificate] shortcode.', 'verifycate' ) );
		}

		if ( ! $this->factory->isConfigured() ) {
			return $this->adminNote( __( 'Connect Verifycate before using this shortcode.', 'verifycate' ) );
		}

		$certificate = $this->getCertificate( $public_id, $workspace );

		if ( is_wp_error( $certificate ) ) {
			return '<p class="verifycate-verify__error">' . esc_html( $certificate->get_error_message() ) . '</p>';
		}

		return $this->renderCertificate( $certificate, $public_id );
	}

	/**
	 * Render a public verification form and its result.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function verifyShortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'title'  => __( 'Verify a certificate', 'verifycate' ),
				'button' => __( 'Verify', 'verifycate' ),
			),
			is_array( $atts ) ? $atts : array(),
			'verifycate_verify'
		);

		if ( ! $this->factory->isConfigured() ) {
			return $this->adminNote( __( 'Connect Verifycate before using this shortcode.', 'verifycate' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public lookup.
		$public_id = isset( $_GET['verifycate_public_id'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['verifycate_public_id'] ) ) : '';
		$title     = (string) $atts['title'];
		$button    = (string) $atts['button'];
		$input_id  = wp_unique_id( 'verifycate-public-id-' );

		$html = '<div class="verifycate-verify">';
		if ( '' !== $title ) {
			$html .= '<h2 class="verifycate-verify__title">' . esc_html( $title ) . '</h2>';
		}
		$html .= '<form class="verifycate-verify__form" method="get">';
		$html .= '<label class="screen-reader-text" for="' . esc_attr( $input_id ) . '">' . esc_html__( 'Certificate public ID', 'verifycate' ) . '</label>';
		$html .= '<input id="' . esc_attr( $input_id ) . '" type="text" name="verifycate_public_id" value="' . esc_attr( $public_id ) . '" placeholder="' . esc_attr__( 'Certificate public ID', 'verifycate' ) . '" />';
		$html .= '<button type="submit" class="verifycate-verify__button">' . esc_html( $button ) . '</button>';
		$html .= '</form>';

		if ( '' !== $public_id ) {
			$certificate = $this->getCertificate( $public_id, '' );

			if ( is_wp_error( $certificate ) ) {
				$html .= '<p class="verifycate-verify__error">' . esc_html( $certificate->get_error_message() ) . '</p>';
			} else {
				$html .= $this->renderCertificate( $certificate, $public_id );
			}
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Fetch and normalize a public certificate.
	 *
	 * @param string $public_id Public certificate id.
	 * @param string $workspace Optional workspace override.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function getCertificate( string $public_id, string $workspace ) {
		$workspace_key = '' !== $workspace ? $workspace : $this->settings->getWorkspace();
		$cache_key     = 'verifycate_certificate_' . md5( $workspace_key . '|' . $public_id );
		$cached        = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			/**
			 * Cached public certificate data.
			 *
			 * @var array<string, mixed> $cached
			 */
			return $cached;
		}

		try {
			$certificate = $this->factory->make()->verification()->get( $public_id, '' !== $workspace ? $workspace : null );
			$data        = $this->normalize( $certificate, $public_id );

			set_transient( $cache_key, $data, self::TTL );

			return $data;
		} catch ( ApiException $exception ) {
			if ( $exception->isNotFound() ) {
				return new \WP_Error( 'verifycate_certificate_not_found', __( 'No certificate was found with that public ID.', 'verifycate' ) );
			}

			Logger::warning(
				'Could not load a public certificate.',
				array(
					'status' => $exception->getStatusCode(),
					'error'  => $exception->getMessage(),
				)
			);
		} catch ( TransportException $exception ) {
			Logger::warning( 'Could not reach the API for a public certificate.', array( 'error' => $exception->getMessage() ) );
		} catch ( \Throwable $exception ) {
			Logger::warning( 'Unexpected error loading a public certificate.', array( 'error' => $exception->getMessage() ) );
		}

		return new \WP_Error( 'verifycate_certificate', __( 'Certificate details are temporarily unavailable. Please try again later.', 'verifycate' ) );
	}

	/**
	 * Convert a public certificate DTO into cacheable data.
	 *
	 * @param PublicCertificate $certificate Certificate.
	 * @param string            $fallback_id Fallback public id.
	 * @return array<string, mixed>
	 */
	private function normalize( PublicCertificate $certificate, string $fallback_id ): array {
		$issued  = $certificate->getIssuedAt();
		$expires = $certificate->getExpiresAt();

		return array(
			'public_id'        => '' !== $certificate->getPublicId() ? $certificate->getPublicId() : $fallback_id,
			'status'           => $certificate->getStatus(),
			'recipient_name'   => $certificate->getRecipientName(),
			'certificate_name' => $certificate->getCertificateName(),
			'issued_at'        => null === $issued ? '' : $issued->format( DATE_ATOM ),
			'expires_at'       => null === $expires ? '' : $expires->format( DATE_ATOM ),
			'png_url'          => $certificate->getPngUrl(),
			'pdf_url'          => $certificate->getPdfUrl(),
		);
	}

	/**
	 * Render a certificate card.
	 *
	 * @param array<string, mixed> $certificate Certificate data.
	 * @param string               $fallback_id Fallback public id.
	 * @return string
	 */
	private function renderCertificate( array $certificate, string $fallback_id ): string {
		$public_id = isset( $certificate['public_id'] ) ? (string) $certificate['public_id'] : $fallback_id;
		$name      = isset( $certificate['certificate_name'] ) ? (string) $certificate['certificate_name'] : '';
		$recipient = isset( $certificate['recipient_name'] ) ? (string) $certificate['recipient_name'] : '';
		$status    = isset( $certificate['status'] ) ? (string) $certificate['status'] : '';
		$pdf       = isset( $certificate['pdf_url'] ) ? (string) $certificate['pdf_url'] : '';
		$meta      = array();

		if ( ! empty( $certificate['issued_at'] ) ) {
			$timestamp = strtotime( (string) $certificate['issued_at'] );
			if ( false !== $timestamp ) {
				/* translators: %s: issue date. */
				$meta[] = sprintf( __( 'Issued %s', 'verifycate' ), wp_date( (string) get_option( 'date_format' ), $timestamp ) );
			}
		}

		if ( ! empty( $certificate['expires_at'] ) ) {
			$timestamp = strtotime( (string) $certificate['expires_at'] );
			if ( false !== $timestamp ) {
				/* translators: %s: expiry date. */
				$meta[] = sprintf( __( 'Expires %s', 'verifycate' ), wp_date( (string) get_option( 'date_format' ), $timestamp ) );
			}
		}

		$meta[] = 'active' === $status ? __( 'Active', 'verifycate' ) : __( 'Expired', 'verifycate' );

		$view_url = rtrim( $this->settings->getPortalUrl(), '/' ) . '/c/' . rawurlencode( $public_id );
		$class    = 'active' === $status ? 'is-active' : 'is-expired';

		$html  = '<div class="verifycate-certificate-card ' . esc_attr( $class ) . '">';
		$html .= '<div class="verifycate-certificate-card__body">';
		if ( '' !== $name ) {
			$html .= '<h2 class="verifycate-certificate-card__title">' . esc_html( $name ) . '</h2>';
		}
		if ( '' !== $recipient ) {
			$html .= '<p class="verifycate-certificate-card__recipient">' . esc_html( $recipient ) . '</p>';
		}
		$html .= '<p class="verifycate-certificate-card__meta">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
		$html .= '</div>';
		$html .= '<div class="verifycate-certificate-card__actions">';
		$html .= '<a class="verifycate-wallet__link" href="' . esc_url( $view_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View certificate', 'verifycate' ) . '</a>';
		if ( '' !== $pdf ) {
			$html .= '<a class="verifycate-certificate-card__download" href="' . esc_url( $pdf ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Download PDF', 'verifycate' ) . '</a>';
		}
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Show a setup note only to administrators.
	 *
	 * @param string $message Message.
	 * @return string
	 */
	private function adminNote( string $message ): string {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		return '<p class="verifycate-verify__error">' . esc_html( $message ) . '</p>';
	}
}
