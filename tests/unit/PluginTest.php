<?php
/**
 * Tests for the plugin bootstrap class.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Verifycate\Plugin;

/**
 * @covers \Verifycate\Plugin
 */
final class PluginTest extends TestCase {

	/**
	 * Set up WordPress function mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'get_option' )->justReturn( array() );
	}

	/**
	 * Tear down mocks.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * The plugin class is a singleton.
	 */
	public function test_instance_is_a_singleton(): void {
		$this->assertSame( Plugin::instance(), Plugin::instance() );
	}
}
