<?php
/**
 * WP-CLI commands.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Cli;

use Verifycate\Plugin;
use Verifycate\Sdk\Exception\ApiException;
use Verifycate\Support\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Operational commands for issuance, queue and diagnostics.
 */
final class Commands {

	/**
	 * Plugin services.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Show connection and queue status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp verifycate status
	 *
	 * @param array<int, string>    $args Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function status( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );

		$settings = $this->plugin->settings();

		\WP_CLI::line( sprintf( 'Workspace: %s', '' !== $settings->getWorkspace() ? $settings->getWorkspace() : '(not set)' ) );
		\WP_CLI::line( sprintf( 'API key:   %s', '' !== $settings->getApiKey() ? $settings->getMaskedApiKey() : '(not set)' ) );
		\WP_CLI::line( sprintf( 'API URL:   %s', $settings->getBaseUrl() ) );

		if ( ! $settings->isConnected() ) {
			\WP_CLI::warning( 'Verifycate is not connected.' );

			return;
		}

		$result = $this->plugin->factory()->testConnection();
		$result['ok'] ? \WP_CLI::success( $result['message'] ) : \WP_CLI::warning( $result['message'] );

		$counts = $this->plugin->queue()->counts();
		\WP_CLI::line(
			sprintf(
				'Queue:     %d pending, %d processing, %d done, %d failed',
				$counts['pending'],
				$counts['processing'],
				$counts['done'],
				$counts['failed']
			)
		);
	}

	/**
	 * List saved mappings.
	 *
	 * ## EXAMPLES
	 *
	 *     wp verifycate mappings
	 *
	 * @param array<int, string>    $args Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function mappings( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );

		$rows = array();

		foreach ( $this->plugin->mappings()->all() as $mapping ) {
			$integration = $this->plugin->integrations()->get( $mapping->getIntegration() );

			$rows[] = array(
				'id'             => $mapping->getId(),
				'integration'    => null === $integration ? $mapping->getIntegration() : $integration->getLabel(),
				'event'          => $mapping->getEvent(),
				'course'         => 0 === $mapping->getCourseId() ? 'any' : $mapping->getCourseId(),
				'certificate'    => $mapping->getCertificateName(),
				'certificate_id' => $mapping->getCertificateId(),
				'duplicates'     => $mapping->getDuplicatePolicy(),
				'auto_issue'     => $mapping->shouldAutoIssue() ? 'yes' : 'no',
				'enabled'        => $mapping->isEnabled() ? 'yes' : 'no',
			);
		}

		if ( $rows === array() ) {
			\WP_CLI::warning( 'No mappings saved.' );

			return;
		}

		\WP_CLI\Utils\format_items(
			'table',
			$rows,
			array( 'id', 'integration', 'event', 'course', 'certificate', 'certificate_id', 'duplicates', 'auto_issue', 'enabled' )
		);
	}

	/**
	 * Issue a certificate to a learner immediately.
	 *
	 * ## OPTIONS
	 *
	 * <certificate-id>
	 * : Verifycate certificate id.
	 *
	 * --email=<email>
	 * : Recipient email address.
	 *
	 * [--name=<name>]
	 * : Recipient display name.
	 *
	 * [--attributes=<json>]
	 * : Attribute values as a JSON object.
	 *
	 * [--no-issue]
	 * : Import the recipient but leave the issue pending.
	 *
	 * ## EXAMPLES
	 *
	 *     wp verifycate issue 8d7f... --email=ada@example.com --name="Ada Lovelace"
	 *
	 * @param array<int, string>    $args Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function issue( array $args, array $assoc_args ): void {
		$certificate_id = isset( $args[0] ) ? (string) $args[0] : '';
		$email          = isset( $assoc_args['email'] ) ? (string) $assoc_args['email'] : '';

		if ( '' === $certificate_id || '' === $email ) {
			\WP_CLI::error( 'Usage: wp verifycate issue <certificate-id> --email=<email> [--name=<name>] [--attributes=<json>]' );
		}

		$attributes = array();
		if ( isset( $assoc_args['attributes'] ) ) {
			$decoded = json_decode( (string) $assoc_args['attributes'], true );
			if ( ! is_array( $decoded ) ) {
				\WP_CLI::error( '--attributes must be a JSON object.' );
			}

			$attributes = $decoded;
		}

		$recipient = array(
			'email' => $email,
			'name'  => isset( $assoc_args['name'] ) ? (string) $assoc_args['name'] : '',
		);

		if ( $attributes !== array() ) {
			$recipient['attributes'] = $attributes;
		}

		try {
			$result = $this->plugin->factory()->make()->issues()->import( $certificate_id, array( $recipient ) );
			$issue  = $result->getFirstIssue();

			if ( null === $issue ) {
				\WP_CLI::warning( 'The API rejected the recipient.' );

				return;
			}

			if ( $issue->isPending() && ! isset( $assoc_args['no-issue'] ) ) {
				$issue = $this->plugin->factory()->make()->issues()->issue( $issue->getId() );
			}

			\WP_CLI::success( sprintf( 'Issue %s is %s: %s', $issue->getId(), $issue->getStatus(), $issue->getPublicUrl( $this->plugin->settings()->getPortalUrl() ) ) );
		} catch ( ApiException $exception ) {
			\WP_CLI::error( sprintf( 'API error %d: %s', $exception->getStatusCode(), $exception->getMessage() ) );
		} catch ( \Throwable $exception ) {
			\WP_CLI::error( $exception->getMessage() );
		}
	}

	/**
	 * Process queued issuance jobs.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Maximum jobs to process. Default 10.
	 *
	 * ## EXAMPLES
	 *
	 *     wp verifycate process --limit=20
	 *
	 * @param array<int, string>    $args Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function process( array $args, array $assoc_args ): void {
		unset( $args );

		$limit = isset( $assoc_args['limit'] ) ? max( 1, min( 50, (int) $assoc_args['limit'] ) ) : 10;

		$this->plugin->worker()->run( $limit );
		\WP_CLI::success( sprintf( 'Processed up to %d queued jobs.', $limit ) );
	}

	/**
	 * Retry every failed job.
	 *
	 * ## EXAMPLES
	 *
	 *     wp verifycate retry
	 *
	 * @param array<int, string>    $args Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function retry( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );

		$count = $this->plugin->queue()->retryFailed();
		\WP_CLI::success( sprintf( '%d failed jobs queued for retry.', $count ) );
	}

	/**
	 * Show recent logs.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Maximum rows. Default 50.
	 *
	 * [--level=<level>]
	 * : Filter by info, warning, error or debug.
	 *
	 * ## EXAMPLES
	 *
	 *     wp verifycate logs --level=error --limit=20
	 *
	 * @param array<int, string>    $args Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function logs( array $args, array $assoc_args ): void {
		unset( $args );

		$limit = isset( $assoc_args['limit'] ) ? max( 1, min( 500, (int) $assoc_args['limit'] ) ) : 50;
		$level = isset( $assoc_args['level'] ) ? (string) $assoc_args['level'] : '';
		$rows  = Logger::recent( $limit, $level );

		if ( $rows === array() ) {
			\WP_CLI::warning( 'No log entries.' );

			return;
		}

		$formatted = array();
		foreach ( $rows as $row ) {
			$formatted[] = array(
				'time'    => (string) ( $row['created_at'] ?? '' ),
				'level'   => (string) ( $row['level'] ?? '' ),
				'message' => (string) ( $row['message'] ?? '' ),
				'context' => (string) ( $row['context'] ?? '' ),
			);
		}

		\WP_CLI\Utils\format_items( 'table', $formatted, array( 'time', 'level', 'message', 'context' ) );
	}
}
