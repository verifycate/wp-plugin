<?php
/**
 * At-rest encryption for the workspace API key.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Support;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Base64 is used to encode ciphertext, not to obfuscate code.

/**
 * Encrypts the stored API key with a site-derived key.
 *
 * Sodium is preferred, OpenSSL AES-256-GCM is the fallback. If neither
 * extension is available the value is stored base64-encoded and flagged so the
 * settings screen can warn the administrator.
 */
final class Crypto {

	private const PREFIX_SODIUM = 's1:';

	private const PREFIX_OPENSSL = 'o1:';

	private const PREFIX_PLAIN = 'p1:';

	/**
	 * Encrypt a plaintext value.
	 *
	 * @param string $plain Plaintext.
	 * @return string Encoded ciphertext, or an empty string for empty input.
	 */
	public static function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}

		$key = self::key();

		if ( function_exists( 'sodium_crypto_secretbox' ) && defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plain, $nonce, $key );

			return self::PREFIX_SODIUM . base64_encode( $nonce . $cipher );
		}

		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv     = random_bytes( 12 );
			$tag    = '';
			$cipher = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

			if ( is_string( $cipher ) ) {
				return self::PREFIX_OPENSSL . base64_encode( $iv . $tag . $cipher );
			}
		}

		return self::PREFIX_PLAIN . base64_encode( $plain );
	}

	/**
	 * Decrypt a stored value. Legacy plaintext values pass through unchanged.
	 *
	 * @param string $value Stored value.
	 * @return string Plaintext, or an empty string when decryption fails.
	 */
	public static function decrypt( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		if ( 0 === strncmp( $value, self::PREFIX_PLAIN, 3 ) ) {
			$decoded = base64_decode( substr( $value, 3 ), true );

			return is_string( $decoded ) ? $decoded : '';
		}

		if ( 0 === strncmp( $value, self::PREFIX_SODIUM, 3 ) ) {
			return self::decryptSodium( substr( $value, 3 ) );
		}

		if ( 0 === strncmp( $value, self::PREFIX_OPENSSL, 3 ) ) {
			return self::decryptOpenSsl( substr( $value, 3 ) );
		}

		return $value;
	}

	/**
	 * Whether a stored value uses one of the plugin's ciphertext prefixes.
	 *
	 * @param string $value Stored value.
	 * @return bool
	 */
	public static function isEncrypted( string $value ): bool {
		return 0 === strncmp( $value, self::PREFIX_SODIUM, 3 )
			|| 0 === strncmp( $value, self::PREFIX_OPENSSL, 3 );
	}

	/**
	 * Whether the site can encrypt secrets at rest.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return function_exists( 'sodium_crypto_secretbox' ) || function_exists( 'openssl_encrypt' );
	}

	/**
	 * Decrypt a sodium secretbox value.
	 *
	 * @param string $encoded Base64 payload without the prefix.
	 * @return string
	 */
	private static function decryptSodium( string $encoded ): string {
		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) || ! defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' ) ) {
			return '';
		}

		$raw = base64_decode( $encoded, true );
		if ( ! is_string( $raw ) || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}

		$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, self::key() );

		return is_string( $plain ) ? $plain : '';
	}

	/**
	 * Decrypt an OpenSSL AES-256-GCM value.
	 *
	 * @param string $encoded Base64 payload without the prefix.
	 * @return string
	 */
	private static function decryptOpenSsl( string $encoded ): string {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$raw = base64_decode( $encoded, true );
		if ( ! is_string( $raw ) || strlen( $raw ) <= 28 ) {
			return '';
		}

		$iv     = substr( $raw, 0, 12 );
		$tag    = substr( $raw, 12, 16 );
		$cipher = substr( $raw, 28 );
		$plain  = openssl_decrypt( $cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );

		return is_string( $plain ) ? $plain : '';
	}

	/**
	 * Derive a 32-byte key from the site's AUTH_KEY.
	 *
	 * @return string
	 */
	private static function key(): string {
		$salt = defined( 'AUTH_KEY' ) && is_string( AUTH_KEY ) ? AUTH_KEY : 'verifycate';

		return hash( 'sha256', $salt . '|verifycate|api-key', true );
	}
}
