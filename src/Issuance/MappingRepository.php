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
	 * Paginated mappings, newest first.
	 *
	 * @param int    $page Page number.
	 * @param int    $per_page Mappings per page.
	 * @param string $search Search term.
	 * @param string $orderby Sort key.
	 * @param string $order Sort direction.
	 * @return array<int, Mapping>
	 */
	public function paginate( int $page = 1, int $per_page = 20, string $search = '', string $orderby = 'id', string $order = 'desc' ): array {
		global $wpdb;

		$page     = max( 1, $page );
		$per_page = max( 1, $per_page );
		$offset   = ( $page - 1 ) * $per_page;
		$table    = $this->table();
		$posts    = $wpdb->posts;

		list( $where, $params ) = $this->searchClause( $search );

		$order_sql = $this->orderClause( $orderby, $order );
		$sql       = "SELECT m.* FROM {$table} m LEFT JOIN {$posts} p ON p.ID = m.course_id {$where} {$order_sql} LIMIT %d OFFSET %d";
		$params[]  = $per_page;
		$params[]  = $offset;
		$prepared  = $wpdb->prepare( $sql, $params );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $prepared, ARRAY_A );

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
		$trigger_id     = isset( $data['trigger_id'] ) ? max( 0, (int) $data['trigger_id'] ) : 0;
		$score_min      = isset( $data['score_min'] ) ? max( 0.0, min( 100.0, (float) $data['score_min'] ) ) : 0.0;
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
			'trigger_id'       => $trigger_id,
			'course_id'        => $course_id,
			'certificate_id'   => $certificate_id,
			'certificate_name' => $certificate,
			'attributes'       => false === $encoded ? '{}' : $encoded,
			'auto_issue'       => $auto_issue ? 1 : 0,
			'duplicate_policy' => $duplicate,
			'score_min'        => $score_min,
			'enabled'          => $enabled ? 1 : 0,
			'updated_at'       => $now,
		);

		$formats = array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%f', '%d', '%s' );

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
	 * @param int    $trigger_id Trigger object id.
	 * @return array<int, Mapping>
	 */
	public function matching( string $integration, int $course_id, string $event, int $trigger_id = 0 ): array {
		global $wpdb;

		$table = $this->table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE enabled = 1 AND integration = %s AND (course_id = 0 OR course_id = %d) AND event = %s AND (trigger_id = 0 OR trigger_id = %d) ORDER BY course_id DESC, trigger_id DESC, id ASC",
				$integration,
				$course_id,
				$event,
				$trigger_id
			),
			ARRAY_A
		);

		return $this->hydrate( is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Total mapping count.
	 *
	 * @param string $search Search term.
	 * @return int
	 */
	public function count( string $search = '' ): int {
		global $wpdb;

		$table = $this->table();
		$posts = $wpdb->posts;

		list( $where, $params ) = $this->searchClause( $search );

		$sql = "SELECT COUNT(*) FROM {$table} m LEFT JOIN {$posts} p ON p.ID = m.course_id {$where}";

		if ( $params !== array() ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Build a safe ORDER BY clause.
	 *
	 * @param string $orderby Sort key.
	 * @param string $order Sort direction.
	 * @return string
	 */
	private function orderClause( string $orderby, string $order ): string {
		$direction = 'asc' === strtolower( $order ) ? 'ASC' : 'DESC';

		switch ( $orderby ) {
			case 'course':
				return "ORDER BY m.integration {$direction}, m.course_id {$direction}, p.post_title {$direction}, m.id DESC";
			case 'certificate':
				return "ORDER BY m.certificate_name {$direction}, m.id DESC";
			case 'status':
				return "ORDER BY m.enabled {$direction}, m.id DESC";
			case 'repeat':
				return "ORDER BY m.duplicate_policy {$direction}, m.id DESC";
			case 'release':
				return "ORDER BY m.auto_issue {$direction}, m.id DESC";
			case 'id':
				return "ORDER BY m.id {$direction}";
			default:
				return 'ORDER BY m.id DESC';
		}
	}

	/**
	 * Build the search SQL clause.
	 *
	 * @param string $search Search term.
	 * @return array{0: string, 1: array<int, string>}
	 */
	private function searchClause( string $search ): array {
		global $wpdb;

		$search = trim( $search );

		if ( '' === $search ) {
			return array( '', array() );
		}

		$like  = '%' . $wpdb->esc_like( $search ) . '%';
		$where = 'WHERE (m.certificate_name LIKE %s OR m.certificate_id LIKE %s OR m.integration LIKE %s OR m.event LIKE %s OR CAST(m.course_id AS CHAR) LIKE %s OR p.post_title LIKE %s)';

		return array( $where, array_fill( 0, 6, $like ) );
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
