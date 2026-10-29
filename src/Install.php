<?php
/**
 * Installation, upgrades and scheduled maintenance.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the plugin's database tables.
 */
final class Install {

	/**
	 * Schema version. Bump whenever a table changes.
	 */
	public const DB_VERSION = '1.1.0';

	/**
	 * Cron hook used by the issuance queue.
	 */
	public const CRON_HOOK = 'verifycate_process_queue';

	/**
	 * Create tables, defaults and scheduled events on activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::createTables();
		update_option( 'verifycate_db_version', self::DB_VERSION, false );
		self::schedule();
	}

	/**
	 * Remove scheduled events when the plugin is deactivated.
	 *
	 * Activation data is kept so re-activating the plugin resumes where the
	 * site left off. Uninstall handles destructive cleanup.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		delete_transient( 'verifycate_queue_lock' );
	}

	/**
	 * Run schema upgrades for sites that update the plugin in place.
	 *
	 * @return void
	 */
	public static function maybeUpgrade(): void {
		$installed = (string) get_option( 'verifycate_db_version', '' );

		if ( self::DB_VERSION !== $installed || ! self::tablesExist() ) {
			self::createTables();
			self::migrateMappingTriggers();
			update_option( 'verifycate_db_version', self::DB_VERSION, false );
			self::schedule();
		}
	}

	/**
	 * Create or update the plugin tables with dbDelta.
	 *
	 * @return void
	 */
	public static function createTables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$mappings        = $wpdb->prefix . 'verifycate_mappings';
		$jobs            = $wpdb->prefix . 'verifycate_jobs';
		$logs            = $wpdb->prefix . 'verifycate_logs';

		$sql = array(
			"CREATE TABLE {$mappings} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				integration varchar(40) NOT NULL,
				event varchar(100) NOT NULL DEFAULT '',
				trigger_id bigint(20) unsigned NOT NULL DEFAULT 0,
				course_id bigint(20) unsigned NOT NULL DEFAULT 0,
				certificate_id char(36) NOT NULL,
				certificate_name varchar(255) NOT NULL DEFAULT '',
				attributes longtext NULL,
				auto_issue tinyint(1) NOT NULL DEFAULT 1,
				duplicate_policy varchar(20) NOT NULL DEFAULT 'skip',
				score_min decimal(6,2) NOT NULL DEFAULT 0.00,
				enabled tinyint(1) NOT NULL DEFAULT 1,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY mapping (integration,event,trigger_id,course_id,certificate_id),
				KEY certificate (certificate_id),
				KEY enabled (enabled)
			) {$charset_collate};",
			"CREATE TABLE {$jobs} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				mapping_id bigint(20) unsigned NOT NULL,
				dedupe_key varchar(191) NOT NULL,
				payload longtext NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				attempts smallint(5) unsigned NOT NULL DEFAULT 0,
				available_at datetime NOT NULL,
				locked_at datetime NULL,
				last_error text NULL,
				issue_id char(36) NULL,
				public_id varchar(191) NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY status_available (status,available_at),
				KEY dedupe (dedupe_key),
				KEY mapping (mapping_id)
			) {$charset_collate};",
			"CREATE TABLE {$logs} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				level varchar(20) NOT NULL DEFAULT 'info',
				message text NOT NULL,
				context longtext NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY level (level),
				KEY created_at (created_at)
			) {$charset_collate};",
		);

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Migrate existing course mappings onto the trigger-aware schema.
	 *
	 * Empty event values historically meant "any event". Native mappings must
	 * become explicitly course-completed before quiz and lesson events start
	 * firing, otherwise old mappings would issue on the new triggers.
	 *
	 * @return void
	 */
	private static function migrateMappingTriggers(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'verifycate_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "UPDATE %i SET event = 'course_completed' WHERE event = ''", $table ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$index = $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i WHERE Key_name = %s', $table, 'mapping' ), ARRAY_A );
		$keys  = array();

		foreach ( is_array( $index ) ? $index : array() as $row ) {
			if ( isset( $row['Column_name'] ) ) {
				$keys[] = (string) $row['Column_name'];
			}
		}

		$expected = array( 'integration', 'event', 'trigger_id', 'course_id', 'certificate_id' );

		if ( $keys === $expected ) {
			return;
		}

		if ( $keys !== array() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP INDEX mapping', $table ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD UNIQUE KEY mapping (integration,event,trigger_id,course_id,certificate_id)', $table ) );
	}

	/**
	 * Schedule the recurring queue worker.
	 *
	 * The plugin also schedules one-off runs when a job is queued, so this
	 * recurring event is a safety net for retries and missed events.
	 *
	 * @return void
	 */
	public static function schedule(): void {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		wp_schedule_event( time() + 300, 'verifycate_minute', self::CRON_HOOK );
	}

	/**
	 * Add the one-minute cron schedule used by the queue.
	 *
	 * @param array<string, array<string, mixed>> $schedules Existing schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public static function addSchedule( array $schedules ): array {
		$schedules['verifycate_minute'] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Every minute (Verifycate queue)', 'verifycate' ),
		);

		return $schedules;
	}

	/**
	 * Whether all plugin tables exist.
	 *
	 * @return bool
	 */
	private static function tablesExist(): bool {
		global $wpdb;

		foreach ( array( 'verifycate_mappings', 'verifycate_jobs', 'verifycate_logs' ) as $table ) {
			$name = $wpdb->prefix . $table;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $name ) ) );

			if ( $found !== $name ) {
				return false;
			}
		}

		return true;
	}
}
