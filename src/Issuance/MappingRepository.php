<?php
/**
 * Mapping persistence.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Issuance;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Custom tables; identifiers are internal and every value is prepared.

/**
 * Reads and writes the mapping table.
 */
final class MappingRepository {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'verifycate_mappings';
	}

	/**
	 * All mappings, newest first.
	 *
	 * @param bool $enabled_only Only enabled mappings.
	 * @return array<int, Mapping>
	 */
	public function all( bool $enabled_only = false ): array {
		global $wpdb;

		$table = $this->table();
		$sql   = "SELECT * FROM {$table}";

		if ( $enabled_only ) {
			$sql .= ' WHERE enabled = 1';
		}

		$sql .= ' ORDER BY id DESC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return $this->hydrate( is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Find one mapping.
	 *
	 * @param int $id Mapping id.
	 * @return Mapping|null
	 */
	public function find( int $id ): ?Mapping {
		global $wpdb;

		$table = $this->table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return is_array( $row ) ? Mapping::fromRow( $row ) : null;
	}

	/**
	 * Insert or update a mapping.
	 *
	 * @param array<string, mixed> $data Mapping fields.
	 * @return int Mapping id, or 0 on failure.
	 */
	public function save( array $data ): int {
		global $wpdb;

		$id             = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$integration    = isset( $data['integration'] ) ? sanitize_key( (string) $data['integration'] ) : '';
		$event          = isset( $data['event'] ) ? sanitize_key( (string) $data['event'] ) : '';
		$course_id      = isset( $data['course_id'] ) ? max( 0, (int) $data['course_id'] ) : 0;
		$certificate_id = isset( $data['certificate_id'] ) ? sanitize_text_field( (string) $data['certificate_id'] ) : '';
		$certificate    = isset( $data['certificate_name'] ) ? sanitize_text_field( (string) $data['certificate_name'] ) : '';
		$attributes     = isset( $data['attributes'] ) && is_array( $data['attributes'] ) ? $data['attributes'] : array();
		$auto_issue     = ! empty( $data['auto_issue'] );
		$duplicate      = isset( $data['duplicate_policy'] ) ? (string) $data['duplicate_policy'] : Mapping::DUPLICATE_SKIP;
		$duplicate      = in_array( $duplicate, array( Mapping::DUPLICATE_SKIP, Mapping::DUPLICATE_REISSUE ), true )
			? $duplicate
			: Mapping::DUPLICATE_SKIP;
		$enabled        = ! empty( $data['enabled'] );
		$now            = current_time( 'mysql', true );

		if ( '' === $integration || '' === $certificate_id ) {
			return 0;
		}

		$clean_attributes = array();
		foreach ( $attributes as $key => $value ) {
			$key = sanitize_text_field( (string) $key );
			if ( '' === $key || ! is_scalar( $value ) ) {
				continue;
			}

			$clean_attributes[ $key ] = (string) $value;
		}

		$encoded = wp_json_encode( $clean_attributes );
		$row     = array(
			'integration'      => $integration,
			'event'            => $event,
			'course_id'        => $course_id,
			'certificate_id'   => $certificate_id,
			'certificate_name' => $certificate,
			'attributes'       => false === $encoded ? '{}' : $encoded,
			'auto_issue'       => $auto_issue ? 1 : 0,
			'duplicate_policy' => $duplicate,
			'enabled'          => $enabled ? 1 : 0,
			'updated_at'       => $now,
		);

		$formats = array( '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s' );

		if ( $id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$updated = $wpdb->update( $this->table(), $row, array( 'id' => $id ), $formats, array( '%d' ) );

			return false === $updated ? 0 : $id;
		}

		$row['created_at'] = $now;
		$formats[]         = '%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert( $this->table(), $row, $formats );

		return false === $inserted ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * Delete a mapping.
	 *
	 * @param int $id Mapping id.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );

		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Enabled mappings that apply to an event.
	 *
	 * Specific course mappings sort before any-course mappings.
	 *
	 * @param string $integration Integration id.
	 * @param int    $course_id Course id.
	 * @param string $event Event name.
	 * @return array<int, Mapping>
	 */
	public function matching( string $integration, int $course_id, string $event ): array {
		global $wpdb;

		$table = $this->table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE enabled = 1 AND integration = %s AND (course_id = 0 OR course_id = %d) AND (event = '' OR event = %s) ORDER BY course_id DESC, id ASC",
				$integration,
				$course_id,
				$event
			),
			ARRAY_A
		);

		return $this->hydrate( is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Total mapping count.
	 *
	 * @return int
	 */
	public function count(): int {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Hydrate database rows.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return array<int, Mapping>
	 */
	private function hydrate( array $rows ): array {
		$mappings = array();

		foreach ( $rows as $row ) {
			$mappings[] = Mapping::fromRow( $row );
		}

		return $mappings;
	}
}
