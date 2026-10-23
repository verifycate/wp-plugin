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
	 * Quizzes available for quiz triggers.
	 *
	 * @param int $limit Maximum quizzes.
	 * @return array<int, array{id: int, title: string, course_id: int, course_title: string}>
	 */
	public function getQuizzes( int $limit = 500 ): array {
		return $this->getSteps( 'sfwd-quiz', $limit );
	}

	/**
	 * Lessons available for lesson triggers.
	 *
	 * @param int $limit Maximum lessons.
	 * @return array<int, array{id: int, title: string, course_id: int, course_title: string}>
	 */
	public function getLessons( int $limit = 500 ): array {
		return $this->getSteps( 'sfwd-lessons', $limit );
	}

	/**
	 * Load LearnDash steps and their parent courses.
	 *
	 * @param string $post_type Post type.
	 * @param int    $limit Maximum items.
	 * @return array<int, array{id: int, title: string, course_id: int, course_title: string}>
	 */
	private function getSteps( string $post_type, int $limit ): array {
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
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$course_id = function_exists( 'learndash_get_course_id' ) ? (int) learndash_get_course_id( $post->ID ) : 0;
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
		add_action(
			'learndash_quiz_completed',
			static function ( $data, $user = null ) use ( $listener ): void {
				if ( ! is_array( $data ) || empty( $data['pass'] ) ) {
					return;
				}

				$quiz    = isset( $data['quiz'] ) ? $data['quiz'] : null;
				$quiz_id = is_object( $quiz ) && isset( $quiz->ID ) ? (int) $quiz->ID : ( is_numeric( $quiz ) ? (int) $quiz : 0 );
				if ( $quiz_id <= 0 ) {
					return;
				}

				$course    = isset( $data['course'] ) ? $data['course'] : null;
				$course_id = is_object( $course ) && isset( $course->ID ) ? (int) $course->ID : 0;
				if ( $course_id <= 0 && function_exists( 'learndash_get_course_id' ) ) {
					$course_id = (int) learndash_get_course_id( $quiz_id );
				}

				$user_id = 0;
				if ( is_object( $user ) && isset( $user->ID ) ) {
					$user_id = (int) $user->ID;
				} elseif ( isset( $data['user'] ) && is_object( $data['user'] ) && isset( $data['user']->ID ) ) {
					$user_id = (int) $data['user']->ID;
				}

				if ( $course_id <= 0 || $user_id <= 0 ) {
					return;
				}

				$score = null;
				if ( isset( $data['percentage'] ) && is_numeric( $data['percentage'] ) ) {
					$score = (float) $data['percentage'];
				} elseif ( isset( $data['score'] ) && is_numeric( $data['score'] ) ) {
					$score = (float) $data['score'];
				}

				$quiz_title = is_object( $quiz ) && isset( $quiz->post_title ) ? (string) $quiz->post_title : (string) get_the_title( $quiz_id );

				$listener(
					\Verifycate\Issuance\CompletionEvent::create(
						'learndash',
						\Verifycate\Issuance\Mapping::EVENT_QUIZ_PASSED,
						$user_id,
						$course_id,
						(string) get_the_title( $course_id ),
						$score,
						array(
							'quiz_id'    => $quiz_id,
							'quiz_title' => $quiz_title,
						),
						$quiz_id
					)
				);
			},
			10,
			2
		);

		add_action(
			'learndash_lesson_completed',
			static function ( $data ) use ( $listener ): void {
				if ( ! is_array( $data ) || empty( $data['user'] ) || empty( $data['lesson'] ) ) {
					return;
				}

				$user      = $data['user'];
				$lesson    = $data['lesson'];
				$lesson_id = is_object( $lesson ) && isset( $lesson->ID ) ? (int) $lesson->ID : ( is_numeric( $lesson ) ? (int) $lesson : 0 );

				if ( $lesson_id <= 0 || ! is_object( $user ) || ! isset( $user->ID ) ) {
					return;
				}

				$course    = isset( $data['course'] ) ? $data['course'] : null;
				$course_id = is_object( $course ) && isset( $course->ID ) ? (int) $course->ID : 0;
				if ( $course_id <= 0 && function_exists( 'learndash_get_course_id' ) ) {
					$course_id = (int) learndash_get_course_id( $lesson_id );
				}

				if ( $course_id <= 0 ) {
					return;
				}

				$lesson_title = is_object( $lesson ) && isset( $lesson->post_title ) ? (string) $lesson->post_title : (string) get_the_title( $lesson_id );

				$listener(
					\Verifycate\Issuance\CompletionEvent::create(
						'learndash',
						\Verifycate\Issuance\Mapping::EVENT_LESSON_COMPLETED,
						(int) $user->ID,
						$course_id,
						(string) get_the_title( $course_id ),
						null,
						array(
							'lesson_id'    => $lesson_id,
							'lesson_title' => $lesson_title,
						),
						$lesson_id
					)
				);
			},
			10,
			1
		);
	}
}
