<?php
/**
 * Tests for the runtime requirement checks.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Tests\Unit;

use Verifycate\Support\Requirements;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Verifycate\Support\Requirements
 */
final class RequirementsTest extends TestCase {

	/**
	 * The advertised minimum versions are part of the plugin contract.
	 */
	public function test_minimum_versions_are_declared(): void {
		$this->assertSame( '6.2', Requirements::MIN_WP );
		$this->assertSame( '7.4', Requirements::MIN_PHP );
	}
}
