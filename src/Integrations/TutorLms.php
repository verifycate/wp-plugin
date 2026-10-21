<?php
/**
 * Tutor LMS integration.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Emits an event when Tutor LMS completes a course.
 */
final class TutorLms extends AbstractIntegration {

	/**
	 * Integration id.
	 *
	 * @return string
	 */
	public function getId(): string {
		return 'tutor';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function getLabel(): string {
		return 'Tutor LMS';
	}

	/**
	 * Detect Tutor LMS.
	 *
	 * @return bool
	 */
	public function isActive(): bool {
		return defined( 'TUTOR_VERSION' ) || function_exists( 'tutor' );
	}

	/**
	 * Course post type.
	 *
	 * @return string
	 */
	protected function getCoursePostType(): string {
		return 'courses';
	}

	/**
	 * Subscribe to tutor_course_complete_after.
	 *
	 * @param callable $listener Receives a CompletionEvent.
	 * @return void
	 */
	public function register( callable $listener ): void {
		add_action(
			'tutor_course_complete_after',
			static function ( $course_id, $user_id = 0 ) use ( $listener ): void {
				$course_id = (int) $course_id;
				$user_id   = (int) $user_id;

				if ( $user_id <= 0 ) {
					$user_id = (int) get_current_user_id();
				}

				if ( $course_id <= 0 || $user_id <= 0 ) {
					return;
				}

				$listener(
					\Verifycate\Issuance\CompletionEvent::create(
						'tutor',
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
