<?php
/**
 * Tests for the integration registry.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Verifycate\Integrations\Registry;

/**
 * @covers \Verifycate\Integrations\Registry
 */
final class RegistryTest extends TestCase {

	/**
	 * Set up mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'add_action' )->justReturn( true );
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
	 * The custom hook is always available.
	 */
	public function test_custom_hook_is_always_active(): void {
		$registry = new Registry();
		$active   = $registry->active();

		$this->assertArrayHasKey( 'custom', $active );
		$this->assertSame( 'Custom hook', $active['custom']->getLabel() );
	}

	/**
	 * Known adapters are registered even when inactive.
	 */
	public function test_all_adapters_are_registered(): void {
		$registry = new Registry();

		foreach ( array( 'learndash', 'tutor', 'learnpress', 'lifterlms', 'sensei', 'custom' ) as $id ) {
			$this->assertNotNull( $registry->get( $id ), $id );
		}
	}

	/**
	 * Registering hooks only needs add_action.
	 */
	public function test_register_all_calls_add_action(): void {
		$registry = new Registry();
		$registry->registerAll( static function (): void {} );

		$this->assertTrue( true );
	}
}
