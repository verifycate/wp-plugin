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
	 * Quizzes available for quiz triggers.
	 *
	 * @param int $limit Maximum quizzes.
	 * @return array<int, array{id: int, title: string, course_id: int, course_title: string}>
	 */
	public function getQuizzes( int $limit = 500 ): array {
		$items = array();
		$posts = get_posts(
			array(
				'post_type'        => 'quiz',
				'post_status'      => 'publish',
				'numberposts'      => max( 1, min( 1000, $limit ) ),
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post || ! function_exists( 'Sensei' ) ) {
				continue;
			}

			$lesson_id = (int) get_post_meta( $post->ID, '_quiz_lesson', true );
			$course_id = $lesson_id > 0 ? (int) Sensei()->lesson->get_course_id( $lesson_id ) : 0;

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
	 * Lessons available for lesson triggers.
	 *
	 * @param int $limit Maximum lessons.
	 * @return array<int, array{id: int, title: string, course_id: int, course_title: string}>
	 */
	public function getLessons( int $limit = 500 ): array {
		$items = array();
		$posts = get_posts(
			array(
				'post_type'        => 'lesson',
				'post_status'      => 'publish',
				'numberposts'      => max( 1, min( 1000, $limit ) ),
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post || ! function_exists( 'Sensei' ) ) {
				continue;
			}

			$course_id = (int) Sensei()->lesson->get_course_id( $post->ID );
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
		add_action(
			'sensei_user_lesson_end',
			static function ( $user_id, $lesson_id ) use ( $listener ): void {
				$user_id   = (int) $user_id;
				$lesson_id = (int) $lesson_id;

				if ( $user_id <= 0 || $lesson_id <= 0 || ! function_exists( 'Sensei' ) ) {
					return;
				}

				$course_id = (int) Sensei()->lesson->get_course_id( $lesson_id );
				if ( $course_id <= 0 ) {
					return;
				}

				$listener(
					\Verifycate\Issuance\CompletionEvent::create(
						'sensei',
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
			'sensei_user_quiz_grade',
			static function ( $user_id, $quiz_id, $grade, $quiz_passmark = 0 ) use ( $listener ): void {
				$user_id       = (int) $user_id;
				$quiz_id       = (int) $quiz_id;
				$grade         = (float) $grade;
				$quiz_passmark = (float) $quiz_passmark;

				if ( $user_id <= 0 || $quiz_id <= 0 || ! function_exists( 'Sensei' ) ) {
					return;
				}

				if ( $quiz_passmark > 0 && $grade < $quiz_passmark ) {
					return;
				}

				$lesson_id = (int) get_post_meta( $quiz_id, '_quiz_lesson', true );
				$course_id = $lesson_id > 0 ? (int) Sensei()->lesson->get_course_id( $lesson_id ) : 0;

				if ( $course_id <= 0 ) {
					return;
				}

				$listener(
					\Verifycate\Issuance\CompletionEvent::create(
						'sensei',
						\Verifycate\Issuance\Mapping::EVENT_QUIZ_PASSED,
						$user_id,
						$course_id,
						(string) get_the_title( $course_id ),
						$grade,
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
