<?php
/**
 * Recipient certificate wallet.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Frontend;

use Verifycate\Api\ClientFactory;
use Verifycate\Sdk\Exception\ApiException;
use Verifycate\Sdk\Exception\TransportException;
use Verifycate\Support\Logger;
use Verifycate\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the logged-in learner's certificates as a shortcode and block.
 */
final class Wallet {

	/**
	 * Wallet cache lifetime.
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
	 * Register the shortcode, block and styles.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'verifycate_wallet', array( $this, 'shortcode' ) );
		add_action( 'init', array( $this, 'registerBlock' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Register the server-rendered wallet block.
	 *
	 * @return void
	 */
	public function registerBlock(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			'verifycate/wallet',
			array(
				'api_version'     => '2',
				'title'           => __( 'Verifycate Wallet', 'verifycate' ),
				'category'        => 'widgets',
				'icon'            => 'awards',
				'description'     => __( 'Show the logged-in learner their Verifycate certificates.', 'verifycate' ),
				'attributes'      => array(
					'title'     => array(
						'type'    => 'string',
						'default' => __( 'My certificates', 'verifycate' ),
					),
					'emptyText' => array(
						'type'    => 'string',
						'default' => __( 'No certificates yet.', 'verifycate' ),
					),
					'limit'     => array(
						'type'    => 'number',
						'default' => 20,
					),
				),
				'render_callback' => array( $this, 'renderBlock' ),
			)
		);
	}

	/**
	 * Enqueue wallet styles when the shortcode or block is present.
	 *
	 * @return void
	 */
	public function assets(): void {
		wp_register_style(
			'verifycate-wallet',
			VERIFYCATE_URL . 'assets/wallet.css',
			array(),
			VERIFYCATE_VERSION
		);

		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( has_shortcode( $post->post_content, 'verifycate_wallet' ) || has_block( 'verifycate/wallet', $post ) ) {
			wp_enqueue_style( 'verifycate-wallet' );
		}
	}

	/**
	 * Shortcode renderer.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'title'      => __( 'My certificates', 'verifycate' ),
				'empty_text' => __( 'No certificates yet.', 'verifycate' ),
				'limit'      => 20,
			),
			is_array( $atts ) ? $atts : array(),
			'verifycate_wallet'
		);

		return $this->render(
			array(
				'title'      => (string) $atts['title'],
				'empty_text' => (string) $atts['empty_text'],
				'limit'      => (int) $atts['limit'],
			)
		);
	}

	/**
	 * Block renderer.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function renderBlock( array $attributes ): string {
		return $this->render(
			array(
				'title'      => isset( $attributes['title'] ) ? (string) $attributes['title'] : '',
				'empty_text' => isset( $attributes['emptyText'] ) ? (string) $attributes['emptyText'] : '',
				'limit'      => isset( $attributes['limit'] ) ? (int) $attributes['limit'] : 20,
			)
		);
	}

	/**
	 * Render the wallet.
	 *
	 * @param array<string, mixed> $args Wallet arguments.
	 * @return string
	 */
	public function render( array $args ): string {
		$title      = isset( $args['title'] ) && '' !== (string) $args['title'] ? (string) $args['title'] : __( 'My certificates', 'verifycate' );
		$empty_text = isset( $args['empty_text'] ) && '' !== (string) $args['empty_text'] ? (string) $args['empty_text'] : __( 'No certificates yet.', 'verifycate' );
		$limit      = isset( $args['limit'] ) ? max( 1, min( 50, (int) $args['limit'] ) ) : 20;

		if ( ! is_user_logged_in() ) {
			return sprintf(
				'<div class="verifycate-wallet"><p class="verifycate-wallet__empty">%s</p></div>',
				wp_kses_post(
					sprintf(
						/* translators: %s: login link. */
						esc_html__( 'Please %s to see your certificates.', 'verifycate' ),
						'<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'log in', 'verifycate' ) . '</a>'
					)
				)
			);
		}

		if ( ! $this->factory->isConfigured() ) {
			return '';
		}

		$user = wp_get_current_user();
		if ( ! $user instanceof \WP_User || '' === (string) $user->user_email ) {
			return '';
		}

		$issues = $this->getIssues( (string) $user->user_email );

		if ( is_wp_error( $issues ) ) {
			$message = current_user_can( 'manage_options' )
				? $issues->get_error_message()
				: __( 'Certificates are temporarily unavailable. Please try again later.', 'verifycate' );

			return sprintf(
				'<div class="verifycate-wallet"><p class="verifycate-wallet__empty">%s</p></div>',
				esc_html( $message )
			);
		}

		$issues = array_slice( $issues, 0, $limit );

		$html  = '<div class="verifycate-wallet">';
		$html .= '<h2 class="verifycate-wallet__title">' . esc_html( $title ) . '</h2>';

		if ( $issues === array() ) {
			$html .= '<p class="verifycate-wallet__empty">' . esc_html( $empty_text ) . '</p>';
		} else {
			$html .= '<ul class="verifycate-wallet__list">';

			foreach ( $issues as $issue ) {
				$html .= $this->renderIssue( $issue );
			}

			$html .= '</ul>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Fetch and normalize the learner's issues.
	 *
	 * @param string $email Recipient email.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	private function getIssues( string $email ) {
		$key    = 'verifycate_wallet_' . md5( $this->settings->getWorkspace() . '|' . strtolower( $email ) );
		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			/**
			 * Cached wallet issues.
			 *
			 * @var array<int, array<string, mixed>> $cached
			 */
			return $cached;
		}

		try {
			$recipient = $this->factory->make()->recipients()->get( $email );
			$issues    = array();

			foreach ( $recipient->getIssues() as $issue ) {
				if ( $issue->getCertificateStatus() !== 'active' ) {
					continue;
				}

				$issues[] = array(
					'id'               => $issue->getId(),
					'certificate_name' => $issue->getCertificateName(),
					'status'           => $issue->getStatus(),
					'public_id'        => $issue->getPublicId(),
					'issued_at'        => null === $issue->getIssuedAt() ? null : $issue->getIssuedAt()->format( DATE_ATOM ),
					'expires_at'       => null === $issue->getExpiresAt() ? null : $issue->getExpiresAt()->format( DATE_ATOM ),
					'pending_reason'   => $issue->getPendingReason(),
				);
			}

			usort(
				$issues,
				static function ( array $left, array $right ): int {
					return strcmp( (string) ( $right['issued_at'] ?? '' ), (string) ( $left['issued_at'] ?? '' ) );
				}
			);

			/**
			 * Filter the issues shown in a learner's wallet.
			 *
			 * @param array<int, array<string, mixed>> $issues Normalized issues.
			 * @param string                           $email Recipient email.
			 */
			$issues = apply_filters( 'verifycate_wallet_issues', $issues, $email );

			set_transient( $key, $issues, self::TTL );

			return $issues;
		} catch ( ApiException $exception ) {
			if ( $exception->isNotFound() ) {
				set_transient( $key, array(), self::TTL );

				return array();
			}

			Logger::warning(
				'Could not load the learner wallet.',
				array(
					'status' => $exception->getStatusCode(),
					'error'  => $exception->getMessage(),
				)
			);
		} catch ( TransportException $exception ) {
			Logger::warning( 'Could not reach the API for the learner wallet.', array( 'error' => $exception->getMessage() ) );
		} catch ( \Throwable $exception ) {
			Logger::warning( 'Unexpected error loading the learner wallet.', array( 'error' => $exception->getMessage() ) );
		}

		return new \WP_Error( 'verifycate_wallet', __( 'Certificates are temporarily unavailable. Please try again later.', 'verifycate' ) );
	}

	/**
	 * Render one wallet row.
	 *
	 * @param array<string, mixed> $issue Normalized issue.
	 * @return string
	 */
	private function renderIssue( array $issue ): string {
		$name   = isset( $issue['certificate_name'] ) ? (string) $issue['certificate_name'] : '';
		$status = isset( $issue['status'] ) ? (string) $issue['status'] : 'pending';
		$public = isset( $issue['public_id'] ) ? (string) $issue['public_id'] : '';

		$meta = array();
		if ( ! empty( $issue['issued_at'] ) ) {
			$timestamp = strtotime( (string) $issue['issued_at'] );
			if ( false !== $timestamp ) {
				/* translators: %s: issue date. */
				$meta[] = sprintf( __( 'Issued %s', 'verifycate' ), wp_date( (string) get_option( 'date_format' ), $timestamp ) );
			}
		}

		if ( 'pending' === $status ) {
			$meta[] = __( 'Awaiting release', 'verifycate' );
		} elseif ( 'expired' === $status ) {
			$meta[] = __( 'Expired', 'verifycate' );
		} else {
			$meta[] = __( 'Active', 'verifycate' );
		}

		$html  = '<li class="verifycate-wallet__item">';
		$html .= '<div class="verifycate-wallet__body">';
		$html .= '<h3 class="verifycate-wallet__certificate">' . esc_html( $name ) . '</h3>';
		$html .= '<p class="verifycate-wallet__meta">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
		$html .= '</div>';

		if ( 'active' === $status && '' !== $public ) {
			$url   = rtrim( $this->settings->getPortalUrl(), '/' ) . '/c/' . rawurlencode( $public );
			$html .= '<a class="verifycate-wallet__link" href="' . esc_url( $url ) . '" rel="noopener noreferrer">' . esc_html__( 'View certificate', 'verifycate' ) . '</a>';
		}

		$html .= '</li>';

		return $html;
	}
}
