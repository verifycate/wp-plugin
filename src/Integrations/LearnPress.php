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
	 * Quizzes available for quiz triggers.
	 *
	 * @param int $limit Maximum quizzes.
	 * @return array<int, array{id: int, title: string, course_id: int, course_title: string}>
	 */
	public function getQuizzes( int $limit = 500 ): array {
		return $this->getTriggerItems( 'lp_quiz', $limit );
	}

	/**
	 * Lessons available for lesson triggers.
	 *
	 * @param int $limit Maximum lessons.
	 * @return array<int, array{id: int, title: string, course_id: int, course_title: string}>
	 */
	public function getLessons( int $limit = 500 ): array {
		return $this->getTriggerItems( 'lp_lesson', $limit );
	}

	/**
	 * Load LearnPress course items through its section tables.
	 *
	 * @param string $post_type Post type.
	 * @param int    $limit Maximum items.
	 * @return array<int, array{id: int, title: string, course_id: int, course_title: string}>
	 */
	private function getTriggerItems( string $post_type, int $limit ): array {
		global $wpdb;

		$sections = $wpdb->prefix . 'learnpress_sections';
		$items    = $wpdb->prefix . 'learnpress_section_items';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, s.section_course_id AS course_id FROM %i p INNER JOIN %i si ON si.item_id = p.ID INNER JOIN %i s ON s.section_id = si.section_id WHERE p.post_type = %s AND p.post_status = 'publish' GROUP BY p.ID, p.post_title, s.section_course_id ORDER BY s.section_course_id, p.post_title LIMIT %d",
				$wpdb->posts,
				$items,
				$sections,
				$post_type,
				max( 1, min( 1000, $limit ) )
			),
			ARRAY_A
		);

		$result = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$course_id = isset( $row['course_id'] ) ? (int) $row['course_id'] : 0;
			if ( $course_id <= 0 ) {
				continue;
			}

			$result[] = array(
				'id'           => isset( $row['ID'] ) ? (int) $row['ID'] : 0,
				'title'        => isset( $row['post_title'] ) ? (string) $row['post_title'] : '',
				'course_id'    => $course_id,
				'course_title' => (string) get_the_title( $course_id ),
			);
		}

		return $result;
	}

	/**
	 * Subscribe to LMS completion events.
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
		add_action(
			'learn-press/user-lesson/completed',
			static function ( $user_lesson ) use ( $listener ): void {
				if ( ! is_object( $user_lesson ) || empty( $user_lesson->item_id ) || empty( $user_lesson->ref_id ) || empty( $user_lesson->user_id ) ) {
					return;
				}

				$lesson_id = (int) $user_lesson->item_id;
				$course_id = (int) $user_lesson->ref_id;
				$user_id   = (int) $user_lesson->user_id;

				if ( $lesson_id <= 0 || $course_id <= 0 || $user_id <= 0 ) {
					return;
				}

				$listener(
					\Verifycate\Issuance\CompletionEvent::create(
						'learnpress',
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
			1
		);

		add_action(
			'learn-press/user/quiz-finished',
			static function ( $quiz_id, $course_id = 0, $user_id = 0, $user_quiz = null ) use ( $listener ): void {
				$quiz_id   = (int) $quiz_id;
				$course_id = (int) $course_id;
				$user_id   = (int) $user_id;

				if ( $quiz_id <= 0 || $course_id <= 0 || $user_id <= 0 ) {
					return;
				}

				$result = null;
				if ( is_object( $user_quiz ) && method_exists( $user_quiz, 'get_result' ) ) {
					$result = $user_quiz->get_result();
				}

				if ( is_array( $result ) && isset( $result['result'] ) && is_numeric( $result['result'] ) ) {
					$result = (float) $result['result'];
				}

				if ( ! is_numeric( $result ) && function_exists( 'learn_press_get_user' ) ) {
					$user = learn_press_get_user( $user_id );
					if ( is_object( $user ) && method_exists( $user, 'get_quiz_results' ) ) {
						$result = $user->get_quiz_results( $quiz_id, $course_id, 'result' );
					}
				}

				if ( ! is_numeric( $result ) ) {
					return;
				}

				$passing = (float) get_post_meta( $quiz_id, '_lp_passing_grade', true );
				if ( $passing > 0 && (float) $result < $passing ) {
					return;
				}

				$listener(
					\Verifycate\Issuance\CompletionEvent::create(
						'learnpress',
						\Verifycate\Issuance\Mapping::EVENT_QUIZ_PASSED,
						$user_id,
						$course_id,
						(string) get_the_title( $course_id ),
						(float) $result,
						array(
							'quiz_id'    => $quiz_id,
							'quiz_title' => (string) get_the_title( $quiz_id ),
						),
						$quiz_id
					)
				);
			},
			10,
			4
		);
	}
}
