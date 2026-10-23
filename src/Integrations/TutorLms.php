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
	 * Quizzes available for quiz triggers.
	 *
	 * @param int $limit Maximum quizzes.
	 * @return array<int, array{id: int, title: string, course_id: int, course_title: string}>
	 */
	public function getQuizzes( int $limit = 500 ): array {
		return $this->getTriggerItems( 'tutor_quiz', 'quiz', $limit );
	}

	/**
	 * Lessons available for lesson triggers.
	 *
	 * @param int $limit Maximum lessons.
	 * @return array<int, array{id: int, title: string, course_id: int, course_title: string}>
	 */
	public function getLessons( int $limit = 500 ): array {
		return $this->getTriggerItems( 'lesson', 'lesson', $limit );
	}

	/**
	 * Load Tutor content and resolve its parent course.
	 *
	 * @param string $post_type Post type.
	 * @param string $content_type Tutor content type.
	 * @param int    $limit Maximum items.
	 * @return array<int, array{id: int, title: string, course_id: int, course_title: string}>
	 */
	private function getTriggerItems( string $post_type, string $content_type, int $limit ): array {
		$items = array();
		$posts = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'publish',
				'numberposts'      => max( 1, min( 1000, $limit ) ),
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post || ! function_exists( 'tutor_utils' ) ) {
				continue;
			}

			$course_id = (int) tutor_utils()->get_course_id_by( $content_type, $post->ID );
			if ( $course_id <= 0 ) {
				continue;
			}

			$items[] = array(
				'id'           => (int) $post->ID,
				'title'        => (string) get_the_title( $post ),
				'course_id'    => $course_id,
				'course_title' => (string) get_the_title( $course_id ),
			);
		}

		return $items;
	}

	/**
	 * Subscribe to LMS completion events.
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
		add_action(
			'tutor_lesson_completed_after',
			static function ( $lesson_id, $user_id = 0 ) use ( $listener ): void {
				$lesson_id = (int) $lesson_id;
				$user_id   = (int) $user_id;

				if ( $user_id <= 0 ) {
					$user_id = (int) get_current_user_id();
				}

				if ( $lesson_id <= 0 || $user_id <= 0 ) {
					return;
				}

				$course_id = 0;
				if ( function_exists( 'tutor_utils' ) ) {
					$course_id = (int) tutor_utils()->get_course_id_by( 'lesson', $lesson_id );
				}

				if ( $course_id <= 0 ) {
					return;
				}

				$listener(
					\Verifycate\Issuance\CompletionEvent::create(
						'tutor',
						\Verifycate\Issuance\Mapping::EVENT_LESSON_COMPLETED,
						$user_id,
						$course_id,
						(string) get_the_title( $course_id ),
						null,
						array(
							'lesson_id'    => $lesson_id,
							'lesson_title' => (string) get_the_title( $lesson_id ),
						),
						$lesson_id
					)
				);
			},
			10,
			2
		);

		add_action(
			'tutor_quiz/attempt_ended',
			static function ( $attempt_id, $course_id = 0, $user_id = 0 ) use ( $listener ): void {
				global $wpdb;

				$attempt_id = (int) $attempt_id;
				$course_id  = (int) $course_id;
				$user_id    = (int) $user_id;

				if ( $attempt_id <= 0 || $course_id <= 0 || $user_id <= 0 ) {
					return;
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$attempt = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT quiz_id, earned_marks, total_marks FROM {$wpdb->prefix}tutor_quiz_attempts WHERE attempt_id = %d",
						$attempt_id
					),
					ARRAY_A
				);

				if ( ! is_array( $attempt ) || empty( $attempt['quiz_id'] ) ) {
					return;
				}

				$quiz_id = (int) $attempt['quiz_id'];
				$passed  = false;
				$checker = array( '\Tutor\Models\QuizModel', 'is_quiz_passed' );

				if ( class_exists( '\Tutor\Models\QuizModel' ) && is_callable( $checker ) ) {
					$passed = (bool) call_user_func( $checker, $quiz_id, $user_id );
				}

				if ( ! $passed ) {
					return;
				}

				$score = null;
				if ( isset( $attempt['earned_marks'], $attempt['total_marks'] ) && is_numeric( $attempt['earned_marks'] ) && is_numeric( $attempt['total_marks'] ) && (float) $attempt['total_marks'] > 0 ) {
					$score = ( (float) $attempt['earned_marks'] / (float) $attempt['total_marks'] ) * 100;
				}

				$listener(
					\Verifycate\Issuance\CompletionEvent::create(
						'tutor',
						\Verifycate\Issuance\Mapping::EVENT_QUIZ_PASSED,
						$user_id,
						$course_id,
						(string) get_the_title( $course_id ),
						$score,
						array(
							'quiz_id'    => $quiz_id,
							'quiz_title' => (string) get_the_title( $quiz_id ),
						),
						$quiz_id
					)
				);
			},
			10,
			3
		);
	}
}
