<?php
/**
 * Tests for the plugin bootstrap class.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Tests\Unit;

use Verifycate\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Verifycate\Plugin
 */
final class PluginTest extends TestCase {

	/**
	 * The plugin class is a singleton.
	 */
	public function test_instance_is_a_singleton(): void {
		$this->assertSame( Plugin::instance(), Plugin::instance() );
	}
}
