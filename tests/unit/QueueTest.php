<?php
/**
 * Tests for queue retry policy.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Verifycate\Issuance\Queue;

/**
 * @covers \Verifycate\Issuance\Queue
 */
final class QueueTest extends TestCase {

	/**
	 * Backoff grows exponentially and is capped.
	 */
	public function test_retry_delay(): void {
		$this->assertSame( 300, Queue::retryDelay( 1 ) );
		$this->assertSame( 600, Queue::retryDelay( 2 ) );
		$this->assertSame( 1200, Queue::retryDelay( 3 ) );
		$this->assertSame( 6 * HOUR_IN_SECONDS, Queue::retryDelay( 20 ) );
	}

	/**
	 * Retry-After wins when the API supplies it.
	 */
	public function test_retry_after_override(): void {
		$this->assertSame( 120, Queue::retryDelay( 1, 120 ) );
		$this->assertSame( 30, Queue::retryDelay( 1, 1 ) );
	}
}
