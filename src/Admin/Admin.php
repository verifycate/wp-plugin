<?php
/**
 * Admin screens and form handlers.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Admin;

use Verifycate\Api\CertificateCatalog;
use Verifycate\Api\ClientFactory;
use Verifycate\Integrations\Registry;
use Verifycate\Issuance\AttributeMapper;
use Verifycate\Issuance\MappingRepository;
use Verifycate\Issuance\Queue;
use Verifycate\Support\Logger;
use Verifycate\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Verifycate admin menu and handles privileged actions.
 */
final class Admin {

	/**
	 * Capability required for every screen.
	 */
	public const CAPABILITY = 'manage_options';

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
	 * Certificate catalogue.
	 *
	 * @var CertificateCatalog
	 */
	private CertificateCatalog $catalog;

	/**
	 * Mapping repository.
	 *
	 * @var MappingRepository
	 */
	private MappingRepository $mappings;

	/**
	 * Queue.
	 *
	 * @var Queue
	 */
	private Queue $queue;

	/**
	 * Integration registry.
	 *
	 * @var Registry
	 */
	private Registry $integrations;

	/**
	 * Settings page.
	 *
	 * @var SettingsPage|null
	 */
	private ?SettingsPage $settings_page = null;

	/**
	 * Mappings page.
	 *
	 * @var MappingsPage|null
	 */
	private ?MappingsPage $mappings_page = null;

	/**
	 * Logs page.
	 *
	 * @var LogsPage|null
	 */
	private ?LogsPage $logs_page = null;

	/**
	 * Constructor.
	 *
	 * @param Settings           $settings Settings.
	 * @param ClientFactory      $factory Client factory.
	 * @param CertificateCatalog $catalog Certificate catalogue.
	 * @param MappingRepository  $mappings Mapping repository.
	 * @param Queue              $queue Queue.
	 * @param Registry           $integrations Integrations.
	 */
	public function __construct(
		Settings $settings,
		ClientFactory $factory,
		CertificateCatalog $catalog,
		MappingRepository $mappings,
		Queue $queue,
		Registry $integrations
	) {
		$this->settings     = $settings;
		$this->factory      = $factory;
		$this->catalog      = $catalog;
		$this->mappings     = $mappings;
		$this->queue        = $queue;
		$this->integrations = $integrations;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );

		add_action( 'admin_post_verifycate_save_settings', array( $this, 'saveSettings' ) );
		add_action( 'admin_post_verifycate_test_connection', array( $this, 'testConnection' ) );
		add_action( 'admin_post_verifycate_disconnect', array( $this, 'disconnect' ) );
		add_action( 'admin_post_verifycate_save_mapping', array( $this, 'saveMapping' ) );
		add_action( 'admin_post_verifycate_delete_mapping', array( $this, 'deleteMapping' ) );
		add_action( 'admin_post_verifycate_retry_job', array( $this, 'retryJob' ) );
		add_action( 'admin_post_verifycate_retry_failed', array( $this, 'retryFailed' ) );
		add_action( 'admin_post_verifycate_clear_logs', array( $this, 'clearLogs' ) );

		add_action( 'wp_ajax_verifycate_certificate', array( $this, 'ajaxCertificate' ) );
	}

	/**
	 * Register the menu and submenus.
	 *
	 * @return void
	 */
	public function menu(): void {
		add_menu_page(
			__( 'Verifycate', 'verifycate' ),
			__( 'Verifycate', 'verifycate' ),
			self::CAPABILITY,
			'verifycate',
			array( $this, 'renderSettings' ),
			'dashicons-awards',
			58
		);

		add_submenu_page(
			'verifycate',
			__( 'Get started', 'verifycate' ),
			__( 'Get started', 'verifycate' ),
			self::CAPABILITY,
			'verifycate',
			array( $this, 'renderSettings' )
		);

		add_submenu_page(
			'verifycate',
			__( 'Mappings', 'verifycate' ),
			__( 'Mappings', 'verifycate' ),
			self::CAPABILITY,
			'verifycate-mappings',
			array( $this, 'renderMappings' )
		);

		add_submenu_page(
			'verifycate',
			__( 'Logs', 'verifycate' ),
			__( 'Logs', 'verifycate' ),
			self::CAPABILITY,
			'verifycate-logs',
			array( $this, 'renderLogs' )
		);
	}

	/**
	 * Enqueue admin assets on plugin screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function assets( string $hook ): void {
		if ( false === strpos( $hook, 'verifycate' ) ) {
			return;
		}

		wp_enqueue_style(
			'verifycate-admin',
			VERIFYCATE_URL . 'assets/admin.css',
			array(),
			VERIFYCATE_VERSION
		);

		wp_enqueue_script(
			'verifycate-admin',
			VERIFYCATE_URL . 'assets/admin.js',
			array(),
			VERIFYCATE_VERSION,
			true
		);

		$tokens = array();
		foreach ( AttributeMapper::availableTokens() as $token => $label ) {
			if ( false !== strpos( $token, ':' ) ) {
				continue;
			}

			$tokens[] = array(
				'token' => $token,
				'label' => $label,
			);
		}

		wp_localize_script(
			'verifycate-admin',
			'verifycateAdmin',
			array(
				'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
				'nonce'                 => wp_create_nonce( 'verifycate_admin' ),
				'loading'               => __( 'Loading attributes…', 'verifycate' ),
				'noAttributes'          => __( 'This certificate has no declared attributes. Add them in Verifycate, then reload this page.', 'verifycate' ),
				'chooseCertificate'     => __( 'Choose a certificate to load its attributes.', 'verifycate' ),
				'preview'               => __( 'Preview:', 'verifycate' ),
				'insertToken'           => __( 'Insert token…', 'verifycate' ),
				'insertTokenLabel'      => __( 'Insert token:', 'verifycate' ),
				'moreTokens'            => __( 'More tokens', 'verifycate' ),
				'moreTokensPlaceholder' => __( 'More tokens…', 'verifycate' ),
				'clear'                 => __( 'Clear', 'verifycate' ),
				'tokens'                => $tokens,
				'confirmDelete'         => __( 'Delete this mapping? Existing issued certificates are not affected.', 'verifycate' ),
				'copied'                => __( 'Copied', 'verifycate' ),
			)
		);
	}

	/**
	 * Render the setup page.
	 *
	 * @return void
	 */
	public function renderSettings(): void {
		$this->authorize();
		$this->settingsPage()->render();
	}

	/**
	 * Render the mappings page.
	 *
	 * @return void
	 */
	public function renderMappings(): void {
		$this->authorize();
		$this->mappingsPage()->render();
	}

	/**
	 * Render the logs page.
	 *
	 * @return void
	 */
	public function renderLogs(): void {
		$this->authorize();
		$this->logsPage()->render();
	}

	/**
	 * Save connection settings.
	 *
	 * @return void
	 */
	public function saveSettings(): void {
		$this->authorizePost( 'verifycate_save_settings' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verifyRequest() checks the nonce.
		$this->settings->save( wp_unslash( $_POST ) );
		$this->catalog->flush();

		$result = $this->factory->testConnection();

		if ( $result['ok'] ) {
			$message = $result['message'];

			if ( 0 === $this->mappings->count() ) {
				$message .= ' ' . __( 'Next, map a course.', 'verifycate' );
			}

			Notices::add( 'success', $message );
		} else {
			Notices::add( 'error', $result['message'] );
		}

		$this->redirect( 'verifycate' );
	}

	/**
	 * Test the API connection.
	 *
	 * @return void
	 */
	public function testConnection(): void {
		$this->authorizePost( 'verifycate_test_connection' );

		$result = $this->factory->testConnection();

		Notices::add( $result['ok'] ? 'success' : 'error', $result['message'] );
		$this->redirect( 'verifycate' );
	}

	/**
	 * Remove the stored API key.
	 *
	 * @return void
	 */
	public function disconnect(): void {
		$this->authorizePost( 'verifycate_disconnect' );

		$this->settings->disconnect();
		$this->catalog->flush();

		Notices::add( 'success', __( 'Disconnected. The workspace and URLs were kept.', 'verifycate' ) );
		$this->redirect( 'verifycate' );
	}

	/**
	 * Save a mapping.
	 *
	 * @return void
	 */
	public function saveMapping(): void {
		$this->authorizePost( 'verifycate_save_mapping' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verifyRequest() checks the nonce.
		$post = wp_unslash( $_POST );

		$source = isset( $post['source'] ) && is_string( $post['source'] ) ? $post['source'] : '';
		$parts  = explode( ':', $source, 2 );

		if ( count( $parts ) !== 2 ) {
			Notices::add( 'error', __( 'Choose a course or the custom hook before saving.', 'verifycate' ) );
			$this->redirect( 'verifycate-mappings' );
		}

		$integration = sanitize_key( $parts[0] );
		$course_id   = max( 0, (int) $parts[1] );

		if ( 'custom' === $integration ) {
			$event = isset( $post['custom_event'] ) ? sanitize_key( (string) $post['custom_event'] ) : '';
			if ( '' === $event ) {
				$event = 'course_completed';
			}

			$custom_course = isset( $post['custom_course_id'] ) ? max( 0, (int) $post['custom_course_id'] ) : 0;
			$course_id     = $custom_course;
		} else {
			$event = '';
		}

		$certificate_id = isset( $post['certificate_id'] ) ? sanitize_text_field( (string) $post['certificate_id'] ) : '';
		$certificate    = $this->catalog->find( $certificate_id );

		$certificate_name = $certificate['name'] ?? '';
		if ( '' === $certificate_name && isset( $post['certificate_name'] ) ) {
			$certificate_name = sanitize_text_field( (string) $post['certificate_name'] );
		}

		$saved = $this->mappings->save(
			array(
				'id'               => isset( $post['mapping_id'] ) ? (int) $post['mapping_id'] : 0,
				'integration'      => $integration,
				'event'            => $event,
				'course_id'        => $course_id,
				'certificate_id'   => $certificate_id,
				'certificate_name' => $certificate_name,
				'attributes'       => $this->parseAttributes( isset( $post['attributes'] ) ? (string) $post['attributes'] : '' ),
				'auto_issue'       => ! empty( $post['auto_issue'] ),
				'duplicate_policy' => isset( $post['duplicate_policy'] ) ? sanitize_key( (string) $post['duplicate_policy'] ) : '',
				'enabled'          => ! empty( $post['enabled'] ),
			)
		);

		if ( $saved <= 0 ) {
			Notices::add( 'error', __( 'The mapping could not be saved. Check the course and certificate.', 'verifycate' ) );
		} else {
			Notices::add( 'success', __( 'Mapping saved.', 'verifycate' ) );
			$this->catalog->flush();
		}

		$this->redirect( 'verifycate-mappings' );
	}

	/**
	 * Delete a mapping.
	 *
	 * @return void
	 */
	public function deleteMapping(): void {
		$this->authorizePost( 'verifycate_delete_mapping' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verifyRequest() checks the nonce.
		$id = isset( $_POST['mapping_id'] ) ? (int) $_POST['mapping_id'] : 0;

		if ( $id > 0 && $this->mappings->delete( $id ) ) {
			Notices::add( 'success', __( 'Mapping deleted.', 'verifycate' ) );
		} else {
			Notices::add( 'error', __( 'The mapping could not be deleted.', 'verifycate' ) );
		}

		$this->redirect( 'verifycate-mappings' );
	}

	/**
	 * Retry one failed job.
	 *
	 * @return void
	 */
	public function retryJob(): void {
		$this->authorizePost( 'verifycate_retry_job' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verifyRequest() checks the nonce.
		$id = isset( $_POST['job_id'] ) ? (int) $_POST['job_id'] : 0;

		if ( $id > 0 && $this->queue->retry( $id ) ) {
			Notices::add( 'success', __( 'Job queued for retry.', 'verifycate' ) );
		} else {
			Notices::add( 'error', __( 'The job could not be retried.', 'verifycate' ) );
		}

		$this->redirect( 'verifycate-logs' );
	}

	/**
	 * Retry every failed job.
	 *
	 * @return void
	 */
	public function retryFailed(): void {
		$this->authorizePost( 'verifycate_retry_failed' );

		$count = $this->queue->retryFailed();

		Notices::add(
			'success',
			sprintf(
				/* translators: %d: number of jobs. */
				_n( '%d failed job queued for retry.', '%d failed jobs queued for retry.', $count, 'verifycate' ),
				$count
			)
		);

		$this->redirect( 'verifycate-logs' );
	}

	/**
	 * Clear the log table.
	 *
	 * @return void
	 */
	public function clearLogs(): void {
		$this->authorizePost( 'verifycate_clear_logs' );

		Logger::clear();
		Notices::add( 'success', __( 'Logs cleared.', 'verifycate' ) );

		$this->redirect( 'verifycate-logs' );
	}

	/**
	 * Return certificate attributes to the mapping editor.
	 *
	 * @return void
	 */
	public function ajaxCertificate(): void {
		check_ajax_referer( 'verifycate_admin', 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'verifycate' ) ), 403 );
		}

		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['id'] ) ) : '';

		if ( '' === $id ) {
			wp_send_json_error( array( 'message' => __( 'Choose a certificate first.', 'verifycate' ) ), 400 );
		}

		wp_send_json_success( array( 'attributes' => $this->catalog->attributes( $id ) ) );
	}

	/**
	 * Redirect back to a plugin screen.
	 *
	 * @param string $page Page slug.
	 * @return void
	 */
	private function redirect( string $page ): void {
		wp_safe_redirect( add_query_arg( 'page', $page, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Require capability for a screen.
	 *
	 * @return void
	 */
	private function authorize(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'verifycate' ) );
		}
	}

	/**
	 * Require capability and a nonce for a form handler.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private function authorizePost( string $action ): void {
		$this->authorize();

		check_admin_referer( $action );
	}

	/**
	 * Parse "key = template" lines from the mapping editor.
	 *
	 * @param string $raw Raw textarea.
	 * @return array<string, string>
	 */
	private function parseAttributes( string $raw ): array {
		$attributes = array();
		$lines      = preg_split( '/\r\n|\r|\n/', $raw );

		foreach ( is_array( $lines ) ? $lines : array() as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}

			$separator = strpos( $line, '=' );
			if ( false === $separator ) {
				continue;
			}

			$key   = trim( substr( $line, 0, $separator ) );
			$value = trim( substr( $line, $separator + 1 ) );

			if ( '' === $key ) {
				continue;
			}

			$attributes[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
		}

		return $attributes;
	}

	/**
	 * Lazily build the settings page.
	 *
	 * @return SettingsPage
	 */
	private function settingsPage(): SettingsPage {
		if ( null === $this->settings_page ) {
			$this->settings_page = new SettingsPage( $this->settings, $this->factory, $this->mappings, $this->integrations );
		}

		return $this->settings_page;
	}

	/**
	 * Lazily build the mappings page.
	 *
	 * @return MappingsPage
	 */
	private function mappingsPage(): MappingsPage {
		if ( null === $this->mappings_page ) {
			$this->mappings_page = new MappingsPage( $this->settings, $this->catalog, $this->mappings, $this->integrations );
		}

		return $this->mappings_page;
	}

	/**
	 * Lazily build the logs page.
	 *
	 * @return LogsPage
	 */
	private function logsPage(): LogsPage {
		if ( null === $this->logs_page ) {
			$this->logs_page = new LogsPage( $this->queue );
		}

		return $this->logs_page;
	}
}
