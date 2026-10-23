<?php
/**
 * Durable issuance queue.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Issuance;

use Verifycate\Install;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Custom tables; identifiers are internal and every value is prepared.

/**
 * A small database-backed queue with retries and visible state.
 */
final class Queue {

	/**
	 * Maximum attempts before a retryable job becomes failed.
	 */
	public const MAX_ATTEMPTS = 5;

	/**
	 * Seconds after which a processing job is considered abandoned.
	 */
	private const LOCK_TIMEOUT = 600;

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'verifycate_jobs';
	}

	/**
	 * Stable dedupe key for a mapping and event.
	 *
	 * @param Mapping         $mapping Mapping.
	 * @param CompletionEvent $event Event.
	 * @return string
	 */
	public static function dedupeKey( Mapping $mapping, CompletionEvent $event ): string {
		return $mapping->getId() . ':' . $event->getDedupeKey();
	}

	/**
	 * Queue one issuance job.
	 *
	 * @param Mapping         $mapping Mapping snapshot.
	 * @param CompletionEvent $event Event snapshot.
	 * @return int Job id, or 0 on failure.
	 */
	public function enqueue( Mapping $mapping, CompletionEvent $event ): int {
		global $wpdb;

		$payload = wp_json_encode(
			array(
				'mapping' => $mapping->toArray(),
				'event'   => $event->toArray(),
			)
		);

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$this->table(),
			array(
				'mapping_id'   => $mapping->getId(),
				'dedupe_key'   => self::dedupeKey( $mapping, $event ),
				'payload'      => false === $payload ? '{}' : $payload,
				'status'       => 'pending',
				'attempts'     => 0,
				'available_at' => $now,
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		if ( 1 === wp_rand( 1, 100 ) ) {
			$this->purgeOld();
		}

		$this->scheduleSoon();

		return (int) $wpdb->insert_id;
	}

	/**
	 * Whether a mapping already completed for this event.
	 *
	 * @param string $dedupe_key Dedupe key.
	 * @return bool
	 */
	public function hasCompleted( string $dedupe_key ): bool {
		return $this->countByStatus( $dedupe_key, array( 'done' ) ) > 0;
	}

	/**
	 * Whether a mapping has an open job for this event.
	 *
	 * @param string $dedupe_key Dedupe key.
	 * @return bool
	 */
	public function hasOpen( string $dedupe_key ): bool {
		return $this->countByStatus( $dedupe_key, array( 'pending', 'processing' ) ) > 0;
	}

	/**
	 * Claim due jobs for processing.
	 *
	 * @param int $limit Batch size.
	 * @return array<int, array<string, mixed>>
	 */
	public function claim( int $limit = 10 ): array {
		global $wpdb;

		$limit = max( 1, min( 50, $limit ) );
		$table = $this->table();
		$now   = current_time( 'mysql', true );
		$stale = gmdate( 'Y-m-d H:i:s', time() - self::LOCK_TIMEOUT );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'pending', locked_at = NULL WHERE status = 'processing' AND locked_at < %s",
				$stale
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE status = 'pending' AND available_at <= %s ORDER BY id ASC LIMIT %d",
				$now,
				$limit
			)
		);

		$jobs = array();

		foreach ( is_array( $ids ) ? $ids : array() as $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET status = 'processing', locked_at = %s, attempts = attempts + 1, updated_at = %s WHERE id = %d AND status = 'pending'",
					$now,
					$now,
					(int) $id
				)
			);

			if ( 1 !== (int) $updated ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ),
				ARRAY_A
			);

			if ( is_array( $row ) ) {
				$jobs[] = $row;
			}
		}

		return $jobs;
	}

	/**
	 * Mark a job as done.
	 *
	 * @param int                  $id Job id.
	 * @param array<string, mixed> $data Optional issue metadata.
	 * @return void
	 */
	public function complete( int $id, array $data = array() ): void {
		global $wpdb;

		$row = array(
			'status'     => 'done',
			'locked_at'  => null,
			'last_error' => null,
			'updated_at' => current_time( 'mysql', true ),
		);

		$formats = array( '%s', '%s', '%s', '%s' );

		if ( isset( $data['issue_id'] ) ) {
			$row['issue_id'] = (string) $data['issue_id'];
			$formats[]       = '%s';
		}

		if ( isset( $data['public_id'] ) ) {
			$row['public_id'] = (string) $data['public_id'];
			$formats[]        = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $this->table(), $row, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/**
	 * Mark a job as failed, or schedule a retry.
	 *
	 * @param int      $id Job id.
	 * @param string   $error Error message.
	 * @param bool     $retryable Whether a retry can help.
	 * @param int|null $retry_after Retry-After seconds from the API.
	 * @return void
	 */
	public function fail( int $id, string $error, bool $retryable, ?int $retry_after = null ): void {
		global $wpdb;

		$table = $this->table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$attempts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT attempts FROM {$table} WHERE id = %d", $id ) );

		if ( $retryable && $attempts < self::MAX_ATTEMPTS ) {
			$delay     = self::retryDelay( $attempts, $retry_after );
			$available = gmdate( 'Y-m-d H:i:s', time() + $delay );
			$status    = 'pending';
			$locked_at = null;
		} else {
			$available = current_time( 'mysql', true );
			$status    = 'failed';
			$locked_at = null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'status'       => $status,
				'available_at' => $available,
				'locked_at'    => $locked_at,
				'last_error'   => $error,
				'updated_at'   => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( 'pending' === $status ) {
			$this->scheduleAt( strtotime( $available . ' UTC' ) );
		}
	}

	/**
	 * Retry delay in seconds for an attempt count.
	 *
	 * @param int      $attempts Attempt number, starting at 1.
	 * @param int|null $retry_after Server-provided Retry-After.
	 * @return int
	 */
	public static function retryDelay( int $attempts, ?int $retry_after = null ): int {
		if ( null !== $retry_after && $retry_after > 0 ) {
			return max( 30, $retry_after );
		}

		$attempts = max( 1, $attempts );
		$delay    = 300 * ( 2 ** ( $attempts - 1 ) );

		return (int) min( 6 * HOUR_IN_SECONDS, $delay );
	}

	/**
	 * Return failed jobs to the queue.
	 *
	 * @return int Number of jobs reset.
	 */
	public function retryFailed(): int {
		global $wpdb;

		$table = $this->table();
		$now   = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'pending', attempts = 0, available_at = %s, locked_at = NULL, updated_at = %s WHERE status = 'failed'",
				$now,
				$now
			)
		);

		if ( is_int( $updated ) && $updated > 0 ) {
			$this->scheduleSoon();
		}

		return is_int( $updated ) ? $updated : 0;
	}

	/**
	 * Retry one job.
	 *
	 * @param int $id Job id.
	 * @return bool
	 */
	public function retry( int $id ): bool {
		global $wpdb;

		$table = $this->table();
		$now   = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'pending', attempts = 0, available_at = %s, locked_at = NULL, last_error = NULL, updated_at = %s WHERE id = %d",
				$now,
				$now,
				$id
			)
		);

		if ( is_int( $updated ) && $updated > 0 ) {
			$this->scheduleSoon();

			return true;
		}

		return false;
	}

	/**
	 * Job counts by status.
	 *
	 * @return array<string, int>
	 */
	public function counts(): array {
		global $wpdb;

		$table = $this->table();
		$rows  = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status",
			ARRAY_A
		);

		$counts = array(
			'pending'    => 0,
			'processing' => 0,
			'done'       => 0,
			'failed'     => 0,
		);

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$status = isset( $row['status'] ) ? (string) $row['status'] : '';
			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ] = (int) $row['total'];
			}
		}

		return $counts;
	}

	/**
	 * Recent jobs, newest first.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function recent( int $limit = 50 ): array {
		global $wpdb;

		$table = $this->table();
		$limit = max( 1, min( 200, $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Schedule a one-off worker run shortly.
	 *
	 * @return void
	 */
	public function scheduleSoon(): void {
		$this->scheduleAt( time() + 5 );
	}

	/**
	 * Schedule a worker run at a timestamp.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return void
	 */
	private function scheduleAt( int $timestamp ): void {
		$next = wp_next_scheduled( Install::CRON_HOOK );

		if ( false !== $next && $next <= $timestamp ) {
			return;
		}

		wp_schedule_single_event( max( time() + 1, $timestamp ), Install::CRON_HOOK );
	}

	/**
	 * Drop completed and failed jobs older than a retention window.
	 *
	 * @param int $days Retention in days.
	 * @return int Rows removed.
	 */
	public function purgeOld( int $days = 30 ): int {
		global $wpdb;

		$table  = $this->table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, $days ) * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status IN ('done','failed') AND updated_at < %s",
				$cutoff
			)
		);

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * Count jobs with a dedupe key and one of the statuses.
	 *
	 * @param string             $dedupe_key Dedupe key.
	 * @param array<int, string> $statuses Statuses.
	 * @return int
	 */
	private function countByStatus( string $dedupe_key, array $statuses ): int {
		global $wpdb;

		if ( $statuses === array() ) {
			return 0;
		}

		$table        = $this->table();
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$args         = array_merge( array( $dedupe_key ), $statuses );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE dedupe_key = %s AND status IN ({$placeholders})",
				$args
			)
		);

		return (int) $count;
	}
}
