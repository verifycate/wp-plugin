<?php
/**
 * Generic custom-hook integration.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Integrations;

use Verifycate\Issuance\CompletionEvent;

defined( 'ABSPATH' ) || exit;

/**
 * Lets site code emit a completion without a dedicated LMS adapter.
 *
 * Usage:
 *
 *     do_action( 'verifycate_course_completed', $user_id, $course_id );
 *     do_action( 'verifycate_course_completed', $user_id, $course_id, 'course_completed', [
 *         'course_title' => 'Advanced Leadership',
 *         'score'        => 92,
 *         'cohort'       => 'Spring 2026',
 *     ] );
 */
final class CustomHook extends AbstractIntegration {

	/**
	 * Integration id.
	 *
	 * @return string
	 */
	public function getId(): string {
		return 'custom';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function getLabel(): string {
		return 'Custom hook';
	}

	/**
	 * Always available.
	 *
	 * @return bool
	 */
	public function isActive(): bool {
		return true;
	}

	/**
	 * Custom hooks have no course post type.
	 *
	 * @return string
	 */
	protected function getCoursePostType(): string {
		return '';
	}

	/**
	 * No course picker entries.
	 *
	 * @param int $limit Maximum courses.
	 * @return array<int, string>
	 */
	public function getCourses( int $limit = 300 ): array {
		return array();
	}

	/**
	 * Subscribe to the plugin's public custom hook.
	 *
	 * @param callable $listener Receives a CompletionEvent.
	 * @return void
	 */
	public function register( callable $listener ): void {
		add_action(
			'verifycate_course_completed',
			static function ( $user_id, $course_id = 0, $event = 'course_completed', $context = array() ) use ( $listener ): void {
				$user_id   = (int) $user_id;
				$course_id = (int) $course_id;
				$context   = is_array( $context ) ? $context : array();

				if ( $user_id <= 0 ) {
					return;
				}

				$title = isset( $context['course_title'] ) && is_scalar( $context['course_title'] )
					? (string) $context['course_title']
					: ( $course_id > 0 ? (string) get_the_title( $course_id ) : '' );

				$score = isset( $context['score'] ) && is_numeric( $context['score'] )
					? (float) $context['score']
					: null;

				$listener(
					CompletionEvent::create(
						'custom',
						is_scalar( $event ) ? (string) $event : 'course_completed',
						$user_id,
						$course_id,
						$title,
						$score,
						$context
					)
				);
			},
			10,
			4
		);
	}
}
