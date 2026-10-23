<?php
/**
 * LMS integration contract.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * One source of course-completion events.
 */
interface IntegrationInterface {

	/**
	 * Stable integration id stored on mappings.
	 *
	 * @return string
	 */
	public function getId(): string;

	/**
	 * Human-readable label.
	 *
	 * @return string
	 */
	public function getLabel(): string;

	/**
	 * Whether the source plugin is active.
	 *
	 * @return bool
	 */
	public function isActive(): bool;

	/**
	 * Event name emitted for a normal course completion.
	 *
	 * @return string
	 */
	public function getEventName(): string;

	/**
	 * Subscribe to the source plugin's completion hook.
	 *
	 * @param callable $listener Receives a CompletionEvent.
	 * @return void
	 */
	public function register( callable $listener ): void;

	/**
	 * Published courses available for mapping.
	 *
	 * @param int $limit Maximum courses.
	 * @return array<int, string> Course id => title.
	 */
	public function getCourses( int $limit = 300 ): array;

	/**
	 * Quizzes available for quiz triggers.
	 *
	 * @param int $limit Maximum quizzes.
	 * @return array<int, array{id: int, title: string, course_id: int, course_title: string}>
	 */
	public function getQuizzes( int $limit = 500 ): array;

	/**
	 * Lessons available for lesson triggers.
	 *
	 * @param int $limit Maximum lessons.
	 * @return array<int, array{id: int, title: string, course_id: int, course_title: string}>
	 */
	public function getLessons( int $limit = 500 ): array;
}
