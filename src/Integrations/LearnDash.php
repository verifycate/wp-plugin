<?php
/**
 * LearnDash integration.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Emits an event when LearnDash marks a course complete.
 */
final class LearnDash extends AbstractIntegration {

	/**
	 * Integration id.
	 *
	 * @return string
	 */
	public function getId(): string {
		return 'learndash';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function getLabel(): string {
		return 'LearnDash';
	}

	/**
	 * Detect LearnDash.
	 *
	 * @return bool
	 */
	public function isActive(): bool {
		return defined( 'LEARNDASH_VERSION' ) || function_exists( 'learndash_course_status' );
	}

	/**
	 * Course post type.
	 *
	 * @return string
	 */
	protected function getCoursePostType(): string {
		return 'sfwd-courses';
	}

	/**
	 * Subscribe to learndash_course_completed.
	 *
	 * @param callable $listener Receives a CompletionEvent.
	 * @return void
	 */
	public function register( callable $listener ): void {
		add_action(
			'learndash_course_completed',
			static function ( $data ) use ( $listener ): void {
				if ( ! is_array( $data ) || empty( $data['user'] ) || empty( $data['course'] ) ) {
					return;
				}

				$user   = $data['user'];
				$course = $data['course'];

				if ( ! is_object( $user ) || ! isset( $user->ID ) || ! is_object( $course ) || ! isset( $course->ID ) ) {
					return;
				}

				$score = null;
				if ( isset( $data['progress']['score'] ) && is_numeric( $data['progress']['score'] ) ) {
					$score = (float) $data['progress']['score'];
				}

				$listener(
					\Verifycate\Issuance\CompletionEvent::create(
						'learndash',
						'course_completed',
						(int) $user->ID,
						(int) $course->ID,
						isset( $course->post_title ) ? (string) $course->post_title : '',
						$score
					)
				);
			},
			10,
			1
		);
	}
}
