<?php
/**
 * Tests for mappings.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Verifycate\Issuance\Mapping;

/**
 * @covers \Verifycate\Issuance\Mapping
 */
final class MappingTest extends TestCase {

	/**
	 * Builds a mapping from a queue snapshot.
	 */
	public function test_from_array_reads_attributes(): void {
		$mapping = Mapping::fromArray(
			array(
				'id'             => 3,
				'integration'    => 'learndash',
				'course_id'      => 42,
				'certificate_id' => 'cert-1',
				'attributes'     => array( 'course_hours' => '40' ),
				'auto_issue'     => true,
				'enabled'        => true,
			)
		);

		$this->assertSame( 3, $mapping->getId() );
		$this->assertSame( array( 'course_hours' => '40' ), $mapping->getAttributes() );
		$this->assertTrue( $mapping->shouldAutoIssue() );
	}

	/**
	 * Reads JSON attributes from a database row.
	 */
	public function test_from_row_decodes_json(): void {
		$mapping = Mapping::fromRow(
			array(
				'id'               => 3,
				'integration'      => 'learnpress',
				'course_id'        => 0,
				'certificate_id'   => 'cert-1',
				'attributes'       => '{"course_hours":"12"}',
				'duplicate_policy' => Mapping::DUPLICATE_REISSUE,
				'enabled'          => 1,
			)
		);

		$this->assertSame( array( 'course_hours' => '12' ), $mapping->getAttributes() );
		$this->assertSame( Mapping::DUPLICATE_REISSUE, $mapping->getDuplicatePolicy() );
	}

	/**
	 * Unknown duplicate policies fall back to skip.
	 */
	public function test_unknown_duplicate_policy_falls_back(): void {
		$mapping = Mapping::fromArray(
			array(
				'integration'      => 'custom',
				'certificate_id'   => 'cert-1',
				'duplicate_policy' => 'sometimes',
			)
		);

		$this->assertSame( Mapping::DUPLICATE_SKIP, $mapping->getDuplicatePolicy() );
	}

	/**
	 * Matching handles any-course and event-specific rules.
	 */
	public function test_matching(): void {
		$specific = Mapping::fromArray(
			array(
				'integration'    => 'custom',
				'event'          => 'membership_expired',
				'course_id'      => 42,
				'certificate_id' => 'cert-1',
			)
		);

		$this->assertTrue( $specific->matches( 'custom', 42, 'membership_expired' ) );
		$this->assertFalse( $specific->matches( 'custom', 43, 'membership_expired' ) );
		$this->assertFalse( $specific->matches( 'custom', 42, 'course_completed' ) );

		$any = Mapping::fromArray(
			array(
				'integration'    => 'learndash',
				'course_id'      => 0,
				'certificate_id' => 'cert-1',
			)
		);

		$this->assertTrue( $any->matches( 'learndash', 99, 'course_completed' ) );
		$this->assertFalse( $any->matches( 'learnpress', 99, 'course_completed' ) );
	}

	/**
	 * Trigger fields round-trip and restrict matching.
	 */
	public function test_trigger_fields(): void {
		$mapping = Mapping::fromArray(
			array(
				'integration'    => 'learndash',
				'event'          => Mapping::EVENT_QUIZ_PASSED,
				'course_id'      => 42,
				'trigger_id'     => 99,
				'score_min'      => 80,
				'certificate_id' => 'cert-1',
			)
		);

		$this->assertSame( 99, $mapping->getTriggerId() );
		$this->assertSame( 80.0, $mapping->getScoreMin() );
		$this->assertTrue( $mapping->matches( 'learndash', 42, Mapping::EVENT_QUIZ_PASSED, 99 ) );
		$this->assertFalse( $mapping->matches( 'learndash', 42, Mapping::EVENT_QUIZ_PASSED, 100 ) );
		$this->assertSame( 99, $mapping->toArray()['trigger_id'] );
		$this->assertSame( 80.0, $mapping->toArray()['score_min'] );
	}
}
