<?php
/**
 * LearnPress integration.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Emits an event when LearnPress finishes a course.
 */
final class LearnPress extends AbstractIntegration {

	/**
	 * Integration id.
	 *
	 * @return string
	 */
	public function getId(): string {
		return 'learnpress';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function getLabel(): string {
		return 'LearnPress';
	}

	/**
	 * Detect LearnPress.
	 *
	 * @return bool
	 */
	public function isActive(): bool {
		return defined( 'LP_PLUGIN_FILE' ) || class_exists( 'LearnPress' );
	}

	/**
	 * Course post type.
	 *
	 * @return string
	 */
	protected function getCoursePostType(): string {
		return 'lp_course';
	}

	/**
	 * Subscribe to learn-press/user-course-finished.
	 *
	 * @param callable $listener Receives a CompletionEvent.
	 * @return void
	 */
	public function register( callable $listener ): void {
		add_action(
			'learn-press/user-course-finished',
			static function ( $course_id, $user_id = 0, $result = true ) use ( $listener ): void {
				$course_id = (int) $course_id;
				$user_id   = (int) $user_id;

				if ( ! $result || $course_id <= 0 || $user_id <= 0 ) {
					return;
				}

				$listener(
					\Verifycate\Issuance\CompletionEvent::create(
						'learnpress',
						'course_completed',
						$user_id,
						$course_id,
						(string) get_the_title( $course_id )
					)
				);
			},
			10,
			3
		);
	}
}
