<?php
/**
 * Plugin logger.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Support;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Custom tables; identifiers are internal and every value is prepared.

/**
 * Writes bounded, queryable logs for issuance and connection problems.
 */
final class Logger {

	/**
	 * Maximum age of retained log rows.
	 */
	private const RETENTION_DAYS = 30;

	/**
	 * Record an informational message.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Structured context.
	 * @return void
	 */
	public static function info( string $message, array $context = array() ): void {
		self::log( 'info', $message, $context );
	}

	/**
	 * Record a warning.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Structured context.
	 * @return void
	 */
	public static function warning( string $message, array $context = array() ): void {
		self::log( 'warning', $message, $context );
	}

	/**
	 * Record an error.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Structured context.
	 * @return void
	 */
	public static function error( string $message, array $context = array() ): void {
		self::log( 'error', $message, $context );
	}

	/**
	 * Insert a log row.
	 *
	 * @param string               $level One of debug, info, warning or error.
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Structured context.
	 * @return void
	 */
	public static function log( string $level, string $message, array $context = array() ): void {
		global $wpdb;

		$level = in_array( $level, array( 'debug', 'info', 'warning', 'error' ), true ) ? $level : 'info';
		$table = $wpdb->prefix . 'verifycate_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$table,
			array(
				'level'      => $level,
				'message'    => $message,
				'context'    => $context === array() ? null : wp_json_encode( $context ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && '' !== $wpdb->last_error ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'Verifycate log insert failed: %s', $wpdb->last_error ) );
		}

		// Clean up occasionally instead of on every insert.
		if ( 1 === wp_rand( 1, 100 ) ) {
			self::prune();
		}
	}

	/**
	 * Recent log rows, newest first.
	 *
	 * @param int    $limit Maximum rows.
	 * @param string $level Optional level filter.
	 * @return array<int, array<string, mixed>>
	 */
	public static function recent( int $limit = 200, string $level = '' ): array {
		global $wpdb;

		$limit = max( 1, min( 1000, $limit ) );
		$table = $wpdb->prefix . 'verifycate_logs';

		if ( '' !== $level ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE level = %s ORDER BY id DESC LIMIT %d', $table, $level, $limit ),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d', $table, $limit ),
				ARRAY_A
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Total log rows.
	 *
	 * @return int
	 */
	public static function count(): int {
		global $wpdb;

		$table = $wpdb->prefix . 'verifycate_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
	}

	/**
	 * Delete every log row.
	 *
	 * @return void
	 */
	public static function clear(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'verifycate_logs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );
	}

	/**
	 * Drop rows older than the retention window.
	 *
	 * @return void
	 */
	private static function prune(): void {
		global $wpdb;

		$table  = $wpdb->prefix . 'verifycate_logs';
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( self::RETENTION_DAYS * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', $table, $cutoff ) );
	}
}
