<?php
/**
 * Sensei integration.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Emits an event when Sensei completes a course.
 */
final class Sensei extends AbstractIntegration {

	/**
	 * Integration id.
	 *
	 * @return string
	 */
	public function getId(): string {
		return 'sensei';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function getLabel(): string {
		return 'Sensei';
	}

	/**
	 * Detect Sensei.
	 *
	 * @return bool
	 */
	public function isActive(): bool {
		return defined( 'SENSEI_LMS_VERSION' ) || class_exists( 'Sensei' ) || function_exists( 'Sensei' );
	}

	/**
	 * Course post type.
	 *
	 * @return string
	 */
	protected function getCoursePostType(): string {
		return 'course';
	}

	/**
	 * Subscribe to sensei_user_course_end.
	 *
	 * @param callable $listener Receives a CompletionEvent.
	 * @return void
	 */
	public function register( callable $listener ): void {
		add_action(
			'sensei_user_course_end',
			static function ( $user_id, $course_id ) use ( $listener ): void {
				$user_id   = (int) $user_id;
				$course_id = (int) $course_id;

				if ( $user_id <= 0 || $course_id <= 0 ) {
					return;
				}

				$listener(
					\Verifycate\Issuance\CompletionEvent::create(
						'sensei',
						'course_completed',
						$user_id,
						$course_id,
						(string) get_the_title( $course_id )
					)
				);
			},
			10,
			2
		);
	}
}
