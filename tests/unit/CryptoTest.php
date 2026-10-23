<?php
/**
 * Tests for at-rest encryption.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Verifycate\Support\Crypto;

/**
 * @covers \Verifycate\Support\Crypto
 */
final class CryptoTest extends TestCase {

	/**
	 * Ciphertext round-trips.
	 */
	public function test_round_trip(): void {
		$ciphertext = Crypto::encrypt( 'vc_live_secret_value' );

		$this->assertNotSame( 'vc_live_secret_value', $ciphertext );
		$this->assertSame( 'vc_live_secret_value', Crypto::decrypt( $ciphertext ) );
	}

	/**
	 * Each encryption uses a fresh nonce.
	 */
	public function test_ciphertext_is_randomised(): void {
		$this->assertNotSame( Crypto::encrypt( 'same' ), Crypto::encrypt( 'same' ) );
	}

	/**
	 * Empty values stay empty.
	 */
	public function test_empty_values(): void {
		$this->assertSame( '', Crypto::encrypt( '' ) );
		$this->assertSame( '', Crypto::decrypt( '' ) );
	}

	/**
	 * Legacy plaintext values pass through.
	 */
	public function test_legacy_plaintext_passes_through(): void {
		$this->assertSame( 'vc_legacy', Crypto::decrypt( 'vc_legacy' ) );
		$this->assertFalse( Crypto::isEncrypted( 'vc_legacy' ) );
	}

	/**
	 * Corrupt ciphertext decrypts to an empty string.
	 */
	public function test_corrupt_ciphertext(): void {
		$this->assertSame( '', Crypto::decrypt( 's1:not-base64' ) );
	}
}
