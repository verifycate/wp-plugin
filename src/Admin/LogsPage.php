<?php
/**
 * Logs and queue screen.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Admin;

use Verifycate\Issuance\Queue;
use Verifycate\Support\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Shows queue state and recent issuance logs.
 */
final class LogsPage {

	/**
	 * Queue.
	 *
	 * @var Queue
	 */
	private Queue $queue;

	/**
	 * Constructor.
	 *
	 * @param Queue $queue Queue.
	 */
	public function __construct( Queue $queue ) {
		$this->queue = $queue;
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$level = isset( $_GET['level'] ) ? sanitize_key( wp_unslash( (string) $_GET['level'] ) ) : '';
		$level = in_array( $level, array( 'info', 'warning', 'error', 'debug' ), true ) ? $level : '';

		$counts = $this->queue->counts();
		$jobs   = $this->queue->recent( 50 );
		$logs   = Logger::recent( 200, $level );

		Notices::render();
		?>
		<div class="wrap verifycate-wrap">
			<h1><?php esc_html_e( 'Logs', 'verifycate' ); ?></h1>
			<p class="verifycate-lede">
				<?php esc_html_e( 'Issuance runs through a durable queue. Failed jobs can be retried after fixing the connection, the certificate or the mapping.', 'verifycate' ); ?>
			</p>

			<div class="verifycate-stats">
				<?php foreach ( $counts as $status => $total ) : ?>
					<div class="verifycate-stat">
						<span class="verifycate-stat-value"><?php echo esc_html( (string) $total ); ?></span>
						<span class="verifycate-stat-label"><?php echo esc_html( ucfirst( (string) $status ) ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="verifycate-actions">
				<?php if ( $counts['failed'] > 0 ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="verifycate_retry_failed" />
						<?php wp_nonce_field( 'verifycate_retry_failed' ); ?>
						<?php submit_button( __( 'Retry all failed jobs', 'verifycate' ), 'secondary', 'submit', false ); ?>
					</form>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="verifycate-inline-form">
					<input type="hidden" name="action" value="verifycate_clear_logs" />
					<?php wp_nonce_field( 'verifycate_clear_logs' ); ?>
					<?php submit_button( __( 'Clear logs', 'verifycate' ), 'delete', 'submit', false ); ?>
				</form>
			</div>

			<h2><?php esc_html_e( 'Recent jobs', 'verifycate' ); ?></h2>

			<?php if ( $jobs === array() ) : ?>
				<p><?php esc_html_e( 'No issuance jobs yet.', 'verifycate' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped verifycate-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Job', 'verifycate' ); ?></th>
							<th><?php esc_html_e( 'Status', 'verifycate' ); ?></th>
							<th><?php esc_html_e( 'Attempts', 'verifycate' ); ?></th>
							<th><?php esc_html_e( 'Certificate issue', 'verifycate' ); ?></th>
							<th><?php esc_html_e( 'Last error', 'verifycate' ); ?></th>
							<th><?php esc_html_e( 'Updated', 'verifycate' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'verifycate' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $jobs as $job ) : ?>
							<?php
							$job_id = isset( $job['id'] ) ? (int) $job['id'] : 0;
							$status = isset( $job['status'] ) ? (string) $job['status'] : '';
							?>
							<tr>
								<td><code><?php echo esc_html( (string) $job_id ); ?></code></td>
								<td>
									<span class="verifycate-pill <?php echo 'failed' === $status ? 'is-off' : 'is-on'; ?>">
										<?php echo esc_html( $status ); ?>
									</span>
								</td>
								<td><?php echo esc_html( isset( $job['attempts'] ) ? (string) (int) $job['attempts'] : '0' ); ?></td>
								<td>
									<?php if ( ! empty( $job['public_id'] ) ) : ?>
										<code><?php echo esc_html( (string) $job['public_id'] ); ?></code>
									<?php else : ?>
										<span class="verifycate-muted">—</span>
									<?php endif; ?>
								</td>
								<td>
									<?php echo empty( $job['last_error'] ) ? '<span class="verifycate-muted">—</span>' : esc_html( (string) $job['last_error'] ); ?>
								</td>
								<td><?php echo esc_html( $this->formatDate( isset( $job['updated_at'] ) ? (string) $job['updated_at'] : '' ) ); ?></td>
								<td>
									<?php if ( 'failed' === $status ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<input type="hidden" name="action" value="verifycate_retry_job" />
											<input type="hidden" name="job_id" value="<?php echo esc_attr( (string) $job_id ); ?>" />
											<?php wp_nonce_field( 'verifycate_retry_job' ); ?>
											<button type="submit" class="button button-small"><?php esc_html_e( 'Retry', 'verifycate' ); ?></button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<hr />

			<h2><?php esc_html_e( 'Activity log', 'verifycate' ); ?></h2>

			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="verifycate-filter">
				<input type="hidden" name="page" value="verifycate-logs" />
				<label for="verifycate-log-level"><?php esc_html_e( 'Level', 'verifycate' ); ?></label>
				<select id="verifycate-log-level" name="level">
					<option value=""><?php esc_html_e( 'All levels', 'verifycate' ); ?></option>
					<?php foreach ( array( 'info', 'warning', 'error', 'debug' ) as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $level, $option ); ?>>
							<?php echo esc_html( ucfirst( $option ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Filter', 'verifycate' ), 'secondary', 'submit', false ); ?>
			</form>

			<?php if ( $logs === array() ) : ?>
				<p><?php esc_html_e( 'No log entries yet.', 'verifycate' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped verifycate-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'verifycate' ); ?></th>
							<th><?php esc_html_e( 'Level', 'verifycate' ); ?></th>
							<th><?php esc_html_e( 'Message', 'verifycate' ); ?></th>
							<th><?php esc_html_e( 'Context', 'verifycate' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $log ) : ?>
							<?php
							$log_level = isset( $log['level'] ) ? (string) $log['level'] : 'info';
							$context   = isset( $log['context'] ) && is_string( $log['context'] ) ? $log['context'] : '';
							?>
							<tr>
								<td><?php echo esc_html( $this->formatDate( isset( $log['created_at'] ) ? (string) $log['created_at'] : '' ) ); ?></td>
								<td>
									<span class="verifycate-pill <?php echo in_array( $log_level, array( 'error', 'warning' ), true ) ? 'is-off' : 'is-on'; ?>">
										<?php echo esc_html( $log_level ); ?>
									</span>
								</td>
								<td><?php echo esc_html( isset( $log['message'] ) ? (string) $log['message'] : '' ); ?></td>
								<td>
									<?php if ( '' !== $context ) : ?>
										<code class="verifycate-context"><?php echo esc_html( $this->prettyContext( $context ) ); ?></code>
									<?php else : ?>
										<span class="verifycate-muted">—</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Format a UTC MySQL timestamp in site time.
	 *
	 * @param string $value UTC timestamp.
	 * @return string
	 */
	private function formatDate( string $value ): string {
		if ( '' === $value ) {
			return '—';
		}

		$timestamp = strtotime( $value . ' UTC' );
		if ( false === $timestamp ) {
			return $value;
		}

		return wp_date( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Pretty-print a JSON context string.
	 *
	 * @param string $context JSON.
	 * @return string
	 */
	private function prettyContext( string $context ): string {
		$decoded = json_decode( $context, true );

		if ( ! is_array( $decoded ) ) {
			return $context;
		}

		$encoded = wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		return is_string( $encoded ) ? $encoded : $context;
	}
}
