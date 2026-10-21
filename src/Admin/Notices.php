<?php
/**
 * Admin notices.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * One-shot admin notices carried across a redirect.
 */
final class Notices {

	/**
	 * Queue a notice for the current user.
	 *
	 * @param string $type success, error, warning or info.
	 * @param string $message Message.
	 * @return void
	 */
	public static function add( string $type, string $message ): void {
		$type = in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info';

		set_transient(
			self::key(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);
	}

	/**
	 * Render and clear the queued notice.
	 *
	 * @return void
	 */
	public static function render(): void {
		$notice = get_transient( self::key() );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( self::key() );

		$type = isset( $notice['type'] ) && is_string( $notice['type'] ) ? $notice['type'] : 'info';

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( (string) $notice['message'] )
		);
	}

	/**
	 * Per-user transient key.
	 *
	 * @return string
	 */
	private static function key(): string {
		return 'verifycate_notice_' . get_current_user_id();
	}
}
