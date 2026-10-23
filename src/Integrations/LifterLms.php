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
	 * Quizzes available for quiz triggers.
	 *
	 * @param int $limit Maximum quizzes.
	 * @return array<int, array{id: int, title: string, course_id: int, course_title: string}>
	 */
	public function getQuizzes( int $limit = 500 ): array {
		return $this->getTriggerItems( 'llms_quiz', $limit );
	}

	/**
	 * Lessons available for lesson triggers.
	 *
	 * @param int $limit Maximum lessons.
	 * @return array<int, array{id: int, title: string, course_id: int, course_title: string}>
	 */
	public function getLessons( int $limit = 500 ): array {
		return $this->getTriggerItems( 'lesson', $limit );
	}

	/**
	 * Load LifterLMS content and resolve its parent course.
	 *
	 * @param string $post_type Post type.
	 * @param int    $limit Maximum items.
	 * @return array<int, array{id: int, title: string, course_id: int, course_title: string}>
	 */
	private function getTriggerItems( string $post_type, int $limit ): array {
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
			if ( ! $post instanceof \WP_Post || ! function_exists( 'llms_get_post' ) ) {
				continue;
			}

			$model  = llms_get_post( $post->ID );
			$course = is_object( $model ) && method_exists( $model, 'get_course' ) ? $model->get_course() : null;

			if ( ! is_object( $course ) ) {
				continue;
			}

			$course_id = method_exists( $course, 'get' ) ? (int) $course->get( 'id' ) : ( isset( $course->ID ) ? (int) $course->ID : 0 );

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
		add_action(
			'lifterlms_lesson_completed',
			static function ( $student_id, $lesson_id ) use ( $listener ): void {
				$student_id = (int) $student_id;
				$lesson_id  = (int) $lesson_id;

				if ( $student_id <= 0 || $lesson_id <= 0 || ! function_exists( 'llms_get_post' ) ) {
					return;
				}

				$lesson    = llms_get_post( $lesson_id );
				$course    = is_object( $lesson ) && method_exists( $lesson, 'get_course' ) ? $lesson->get_course() : null;
				$course_id = is_object( $course ) && method_exists( $course, 'get' ) ? (int) $course->get( 'id' ) : 0;

				if ( $course_id <= 0 ) {
					return;
				}

				$listener(
					\Verifycate\Issuance\CompletionEvent::create(
						'lifterlms',
						\Verifycate\Issuance\Mapping::EVENT_LESSON_COMPLETED,
						$student_id,
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
			'lifterlms_quiz_passed',
			static function ( $student_id, $quiz_id, $attempt = null ) use ( $listener ): void {
				$student_id = (int) $student_id;
				$quiz_id    = (int) $quiz_id;

				if ( $student_id <= 0 || $quiz_id <= 0 || ! function_exists( 'llms_get_post' ) ) {
					return;
				}

				$quiz      = llms_get_post( $quiz_id );
				$course    = is_object( $quiz ) && method_exists( $quiz, 'get_course' ) ? $quiz->get_course() : null;
				$course_id = is_object( $course ) && method_exists( $course, 'get' ) ? (int) $course->get( 'id' ) : 0;

				if ( $course_id <= 0 ) {
					return;
				}

				$score = null;
				if ( is_object( $attempt ) && method_exists( $attempt, 'get' ) ) {
					$grade = $attempt->get( 'grade' );
					if ( is_numeric( $grade ) ) {
						$score = (float) $grade;
					}
				}

				$listener(
					\Verifycate\Issuance\CompletionEvent::create(
						'lifterlms',
						\Verifycate\Issuance\Mapping::EVENT_QUIZ_PASSED,
						$student_id,
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
