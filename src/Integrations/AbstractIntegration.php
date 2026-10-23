<?php
/**
 * Shared integration behaviour.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for LMS adapters.
 */
abstract class AbstractIntegration implements IntegrationInterface {

	/**
	 * Event name emitted for a normal completion.
	 *
	 * @return string
	 */
	public function getEventName(): string {
		return 'course_completed';
	}

	/**
	 * Post type that stores courses for the LMS.
	 *
	 * @return string
	 */
	abstract protected function getCoursePostType(): string;

	/**
	 * Published courses for the mapping picker.
	 *
	 * @param int $limit Maximum courses.
	 * @return array<int, string>
	 */
	public function getCourses( int $limit = 300 ): array {
		$post_type = $this->getCoursePostType();
		if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
			return array();
		}

		/**
		 * Filter the number of courses loaded into the mapping picker.
		 *
		 * @param int    $limit Maximum courses.
		 * @param string $integration Integration id.
		 */
		$limit = (int) apply_filters( 'verifycate_course_picker_limit', max( 1, min( 1000, $limit ) ), $this->getId() );

		$posts = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'publish',
				'numberposts'      => $limit,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		$courses = array();

		foreach ( $posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				$courses[ (int) $post->ID ] = (string) get_the_title( $post );
			}
		}

		return $courses;
	}

	/**
	 * Quizzes available for quiz triggers.
	 *
	 * @param int $limit Maximum quizzes.
	 * @return array<int, array{id: int, title: string, course_id: int, course_title: string}>
	 */
	public function getQuizzes( int $limit = 500 ): array {
		return array();
	}

	/**
	 * Lessons available for lesson triggers.
	 *
	 * @param int $limit Maximum lessons.
	 * @return array<int, array{id: int, title: string, course_id: int, course_title: string}>
	 */
	public function getLessons( int $limit = 500 ): array {
		return array();
	}

	/**
	 * Course title for an id, falling back to a neutral label.
	 *
	 * @param int $course_id Course id.
	 * @return string
	 */
	protected function courseTitle( int $course_id ): string {
		if ( $course_id <= 0 ) {
			return '';
		}

		$title = get_the_title( $course_id );

		return is_string( $title ) ? $title : '';
	}

	/**
	 * A normal completion event.
	 *
	 * @param int                  $user_id User id.
	 * @param int                  $course_id Course id.
	 * @param float|null           $score Optional score.
	 * @param array<string, mixed> $context Extra data.
	 * @return \Verifycate\Issuance\CompletionEvent
	 */
	protected function event( int $user_id, int $course_id, ?float $score = null, array $context = array() ): \Verifycate\Issuance\CompletionEvent {
		return \Verifycate\Issuance\CompletionEvent::create(
			$this->getId(),
			$this->getEventName(),
			$user_id,
			$course_id,
			$this->courseTitle( $course_id ),
			$score,
			$context
		);
	}
}
