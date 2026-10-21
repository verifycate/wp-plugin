<?php
/**
 * Tests for completion events.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Verifycate\Issuance\CompletionEvent;

/**
 * @covers \Verifycate\Issuance\CompletionEvent
 */
final class CompletionEventTest extends TestCase {

	/**
	 * Events survive a queue round-trip.
	 */
	public function test_round_trip(): void {
		$event = new CompletionEvent(
			'learndash',
			'course_completed',
			7,
			42,
			'Leadership',
			88.0,
			new \DateTimeImmutable( '2026-05-01T10:00:00Z' ),
			array( 'cohort' => 'Spring' )
		);

		$restored = CompletionEvent::fromArray( $event->toArray() );

		$this->assertSame( 'learndash', $restored->getIntegration() );
		$this->assertSame( 'course_completed', $restored->getEvent() );
		$this->assertSame( 7, $restored->getUserId() );
		$this->assertSame( 42, $restored->getCourseId() );
		$this->assertSame( 'Leadership', $restored->getCourseTitle() );
		$this->assertSame( 88.0, $restored->getScore() );
		$this->assertSame( '2026-05-01T10:00:00+00:00', $restored->getCompletedAt()->format( 'c' ) );
		$this->assertSame( array( 'cohort' => 'Spring' ), $restored->getContext() );
	}

	/**
	 * The dedupe key separates learners and courses.
	 */
	public function test_dedupe_key(): void {
		$one = CompletionEvent::create( 'learndash', 'course_completed', 7, 42 );
		$two = CompletionEvent::create( 'learndash', 'course_completed', 7, 42 );
		$three = CompletionEvent::create( 'learndash', 'course_completed', 8, 42 );
		$four = CompletionEvent::create( 'learndash', 'course_completed', 7, 43 );

		$this->assertSame( $one->getDedupeKey(), $two->getDedupeKey() );
		$this->assertNotSame( $one->getDedupeKey(), $three->getDedupeKey() );
		$this->assertNotSame( $one->getDedupeKey(), $four->getDedupeKey() );
	}

	/**
	 * Missing optional fields get safe defaults.
	 */
	public function test_from_array_defaults(): void {
		$event = CompletionEvent::fromArray( array( 'integration' => 'custom' ) );

		$this->assertSame( 'custom', $event->getIntegration() );
		$this->assertSame( 'course_completed', $event->getEvent() );
		$this->assertSame( 0, $event->getUserId() );
		$this->assertNull( $event->getScore() );
		$this->assertSame( array(), $event->getContext() );
	}
}
