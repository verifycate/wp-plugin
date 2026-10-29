<?php
/**
 * Tests for settings.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Verifycate\Sdk\Config;
use Verifycate\Support\Crypto;
use Verifycate\Support\Settings;

/**
 * @covers \Verifycate\Support\Settings
 */
final class SettingsTest extends TestCase {

	/**
	 * Set up mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'sanitize_title' )->alias(
			static function ( string $value ): string {
				return strtolower( trim( $value ) );
			}
		);
		Functions\when( 'esc_url_raw' )->alias(
			static function ( string $value ): string {
				return trim( $value );
			}
		);
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ) {
				return parse_url( $url, $component );
			}
		);
		Functions\when( '__' )->alias(
			static function ( string $text ): string {
				return $text;
			}
		);
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
	 * Reads and decrypts stored values.
	 */
	public function test_reads_encrypted_values(): void {
		$settings = new Settings(
			array(
				'workspace' => 'acme',
				'api_key'   => Crypto::encrypt( 'vc_secret' ),
			)
		);

		$this->assertSame( 'acme', $settings->getWorkspace() );
		$this->assertSame( 'vc_secret', $settings->getApiKey() );
		$this->assertSame( '••••••••cret', $settings->getMaskedApiKey() );
		$this->assertTrue( $settings->isConnected() );
	}

	/**
	 * Saving a blank key keeps the stored key.
	 */
	public function test_blank_key_keeps_the_stored_one(): void {
		$stored = array(
			'workspace' => 'acme',
			'api_key'   => Crypto::encrypt( 'vc_secret' ),
		);

		Functions\expect( 'update_option' )->once();

		$settings = new Settings( $stored );
		$settings->save(
			array(
				'workspace'  => 'Acme Corp',
				'api_key'    => '',
				'base_url'   => '',
				'portal_url' => '',
			)
		);

		$this->assertSame( 'acme corp', $settings->getWorkspace() );
		$this->assertSame( 'vc_secret', $settings->getApiKey() );
		$this->assertSame( Config::DEFAULT_BASE_URL, $settings->getBaseUrl() );
		$this->assertSame( Settings::DEFAULT_PORTAL_URL, $settings->getPortalUrl() );
	}

	/**
	 * Saving a new key encrypts it.
	 */
	public function test_new_key_is_encrypted(): void {
		$captured = array();

		Functions\expect( 'update_option' )->once()->andReturnUsing(
			static function ( string $name, array $value ) use ( &$captured ): bool {
				$captured = $value;

				return true;
			}
		);

		$settings = new Settings( array() );
		$settings->save(
			array(
				'workspace' => 'acme',
				'api_key'   => 'vc_new_secret',
			)
		);

		$this->assertArrayHasKey( 'api_key', $captured );
		$this->assertNotSame( 'vc_new_secret', $captured['api_key'] );
		$this->assertSame( 'vc_new_secret', Crypto::decrypt( (string) $captured['api_key'] ) );
	}

	/**
	 * Disconnect removes the key.
	 */
	public function test_disconnect_removes_the_key(): void {
		Functions\expect( 'update_option' )->once();

		$settings = new Settings(
			array(
				'workspace' => 'acme',
				'api_key'   => Crypto::encrypt( 'vc_secret' ),
			)
		);
		$settings->disconnect();

		$this->assertFalse( $settings->isConnected() );
		$this->assertSame( 'acme', $settings->getWorkspace() );
	}

	/**
	 * Workspace slugs can be pasted as dashboard URLs.
	 */
	public function test_normalizes_workspace_urls(): void {
		$this->assertSame( 'acme', Settings::normalizeWorkspace( 'acme' ) );
		$this->assertSame( 'acme', Settings::normalizeWorkspace( 'https://app.verifycate.com/acme' ) );
		$this->assertSame( 'acme', Settings::normalizeWorkspace( 'https://app.verifycate.com/acme/api-keys' ) );
		$this->assertSame( 'acme', Settings::normalizeWorkspace( 'app.verifycate.com/acme' ) );
		$this->assertSame( '', Settings::normalizeWorkspace( 'https://app.verifycate.com' ) );
	}

	/**
	 * Resolved API-key metadata drives the readiness checks.
	 */
	public function test_resolved_workspace_tracks_scopes(): void {
		Functions\expect( 'update_option' )->once();

		$settings = new Settings( array( 'api_key' => Crypto::encrypt( 'vc_key' ) ) );
		$settings->setResolvedWorkspace(
			'acme',
			array( 'certificates:read', 'certificates:write' ),
			false,
			true
		);

		$this->assertSame( 'acme', $settings->getWorkspace() );
		$this->assertTrue( $settings->canWriteCertificates() );
		$this->assertTrue( $settings->isReadyToIssue() );
	}

	/**
	 * A read-only key is connected but cannot issue.
	 */
	public function test_read_only_key_is_not_ready(): void {
		Functions\expect( 'update_option' )->once();

		$settings = new Settings( array( 'api_key' => Crypto::encrypt( 'vc_key' ) ) );
		$settings->setResolvedWorkspace( 'acme', array( 'certificates:read' ), false, true );

		$this->assertFalse( $settings->canWriteCertificates() );
		$this->assertFalse( $settings->isReadyToIssue() );
		$this->assertNotSame( '', $settings->connectionIssue() );
	}

	/**
	 * API access does not depend on a Pro seat.
	 */
	public function test_unseated_workspace_can_still_issue(): void {
		Functions\expect( 'update_option' )->once();

		$settings = new Settings( array( 'api_key' => Crypto::encrypt( 'vc_key' ) ) );
		$settings->setResolvedWorkspace(
			'acme',
			array( 'certificates:read', 'certificates:write' ),
			false,
			false
		);

		$this->assertFalse( $settings->isSeatLive() );
		$this->assertTrue( $settings->isReadyToIssue() );
		$this->assertSame( '', $settings->connectionIssue() );
	}
}
