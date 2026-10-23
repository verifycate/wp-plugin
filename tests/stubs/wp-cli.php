<?php
/**
 * Minimal WP-CLI stubs for static analysis.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace {
	/**
	 * WP-CLI facade.
	 */
	class WP_CLI {
		/**
		 * Print a line.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function line( string $message ): void {}

		/**
		 * Print success.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function success( string $message ): void {}

		/**
		 * Print a warning.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function warning( string $message ): void {}

		/**
		 * Stop with an error.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function error( string $message ): void {}

		/**
		 * Register a command.
		 *
		 * @param string $name Command name.
		 * @param object $handler Handler.
		 * @return void
		 */
		public static function add_command( string $name, $handler ): void {}
	}
}

namespace WP_CLI\Utils {
	/**
	 * Render a list of items.
	 *
	 * @param string               $format Format.
	 * @param array<int, mixed>    $items Items.
	 * @param array<int, string>   $fields Fields.
	 * @return void
	 */
	function format_items( string $format, array $items, array $fields ): void {}
}
