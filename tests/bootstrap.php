<?php
/**
 * PHPUnit bootstrap for unit tests.
 *
 * These tests cover pure PHP logic and do not boot WordPress. WordPress
 * functions are mocked with Brain Monkey, and the few WordPress classes used by
 * value objects are stubbed below.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );
defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$verifycate_polyfills = dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
if ( is_readable( $verifycate_polyfills ) ) {
	require_once $verifycate_polyfills;
}

if ( ! class_exists( 'WP_User' ) ) {
	/**
	 * Minimal WP_User for unit tests.
	 */
	class WP_User {
		/** @var int */
		public $ID = 0;

		/** @var string */
		public $user_email = '';

		/** @var string */
		public $display_name = '';

		/** @var string */
		public $user_login = '';

		/**
		 * @param int    $id Id.
		 * @param string $email Email.
		 * @param string $name Display name.
		 * @param string $login Login.
		 */
		public function __construct( int $id = 0, string $email = '', string $name = '', string $login = '' ) {
			$this->ID           = $id;
			$this->user_email   = $email;
			$this->display_name = $name;
			$this->user_login   = $login;
		}
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Minimal WP_Post for unit tests.
	 */
	class WP_Post {
		/** @var int */
		public $ID = 0;

		/** @var string */
		public $post_title = '';

		/** @var string */
		public $post_content = '';

		/**
		 * @param int    $id Id.
		 * @param string $title Title.
		 */
		public function __construct( int $id = 0, string $title = '' ) {
			$this->ID         = $id;
			$this->post_title = $title;
		}
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error for unit tests.
	 */
	class WP_Error {
		/** @var string */
		private string $code;

		/** @var string */
		private string $message;

		/**
		 * @param string $code Code.
		 * @param string $message Message.
		 */
		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		/**
		 * @return string
		 */
		public function get_error_message(): string {
			return $this->message;
		}

		/**
		 * @return string
		 */
		public function get_error_code(): string {
			return $this->code;
		}
	}
}
