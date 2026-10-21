<?php
/**
 * LifterLMS integration.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Emits an event when LifterLMS completes a course.
 */
final class LifterLms extends AbstractIntegration {

	/**
	 * Integration id.
	 *
	 * @return string
	 */
	public function getId(): string {
		return 'lifterlms';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function getLabel(): string {
		return 'LifterLMS';
	}

	/**
	 * Detect LifterLMS.
	 *
	 * @return bool
	 */
	public function isActive(): bool {
		return defined( 'LLMS_VERSION' ) || function_exists( 'llms' );
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
	 * Subscribe to lifterlms_course_completed.
	 *
	 * @param callable $listener Receives a CompletionEvent.
	 * @return void
	 */
	public function register( callable $listener ): void {
		add_action(
			'lifterlms_course_completed',
			static function ( $student_id, $course_id ) use ( $listener ): void {
				$student_id = (int) $student_id;
				$course_id  = (int) $course_id;

				if ( $student_id <= 0 || $course_id <= 0 ) {
					return;
				}

				$listener(
					\Verifycate\Issuance\CompletionEvent::create(
						'lifterlms',
						'course_completed',
						$student_id,
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
