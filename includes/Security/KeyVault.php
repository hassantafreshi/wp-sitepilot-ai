<?php
declare(strict_types=1);

namespace SitePilot\Security;

/**
 * Encrypts/decrypts the local BYOK provider key at rest.
 *
 * Uses libsodium (secretbox / XSalsa20-Poly1305) when available, otherwise falls
 * back to OpenSSL AES-256-GCM. The 32-byte key is injected so this class is unit
 * testable without WordPress; fromWordPress() derives it from the site salts.
 */
final class KeyVault {

	private const MAGIC_SODIUM  = 'sp1';
	private const MAGIC_OPENSSL = 'sp2';

	/** @var string 32 raw bytes. */
	private string $key;

	public function __construct( string $key ) {
		if ( 32 !== strlen( $key ) ) {
			throw new \InvalidArgumentException( 'KeyVault key must be exactly 32 bytes.' );
		}
		$this->key = $key;
	}

	/**
	 * Derive a stable 32-byte key from WordPress salts.
	 */
	public static function fromWordPress(): self {
		$material = '';
		if ( function_exists( 'wp_salt' ) ) {
			$material = wp_salt( 'secure_auth' ) . wp_salt( 'auth' );
		}
		if ( '' === $material ) {
			$material = defined( 'AUTH_KEY' ) ? (string) AUTH_KEY : __DIR__;
		}

		$key = hash( 'sha256', 'sitepilot-ai|' . $material, true );

		return new self( $key );
	}

	/**
	 * @return string Base64 token safe to store in an option. Never the plaintext.
	 */
	public function encrypt( string $plaintext ): string {
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plaintext, $nonce, $this->key );

			return self::MAGIC_SODIUM . '.' . base64_encode( $nonce . $cipher );
		}

		$iv     = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $plaintext, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $cipher ) {
			throw new \RuntimeException( 'Encryption failed.' );
		}

		return self::MAGIC_OPENSSL . '.' . base64_encode( $iv . $tag . $cipher );
	}

	public function decrypt( string $token ): string {
		$parts = explode( '.', $token, 2 );
		if ( 2 !== count( $parts ) ) {
			throw new \RuntimeException( 'Malformed ciphertext.' );
		}

		[ $magic, $payload ] = $parts;
		$raw                 = base64_decode( $payload, true );
		if ( false === $raw ) {
			throw new \RuntimeException( 'Invalid ciphertext encoding.' );
		}

		if ( self::MAGIC_SODIUM === $magic ) {
			if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
				throw new \RuntimeException( 'libsodium unavailable to decrypt.' );
			}
			if ( strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				throw new \RuntimeException( 'Malformed ciphertext.' );
			}
			$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $this->key );
			if ( false === $plain ) {
				throw new \RuntimeException( 'Decryption failed.' );
			}

			return $plain;
		}

		if ( self::MAGIC_OPENSSL === $magic ) {
			if ( strlen( $raw ) <= 28 ) {
				throw new \RuntimeException( 'Malformed ciphertext.' );
			}
			$iv     = substr( $raw, 0, 12 );
			$tag    = substr( $raw, 12, 16 );
			$cipher = substr( $raw, 28 );
			$plain  = openssl_decrypt( $cipher, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag );
			if ( false === $plain ) {
				throw new \RuntimeException( 'Decryption failed.' );
			}

			return $plain;
		}

		throw new \RuntimeException( 'Unknown ciphertext version.' );
	}
}
