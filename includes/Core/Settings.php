<?php
declare(strict_types=1);

namespace SitePilot\Core;

use SitePilot\Security\KeyVault;

/**
 * Central settings store. Non-secret settings live in one option; the provider
 * API key is stored separately and encrypted (see KeyVault).
 */
final class Settings {

	public const OPTION     = 'sitepilot_ai_settings';
	public const KEY_OPTION = 'sitepilot_ai_provider_key';

	/**
	 * Provider presets (OpenAI-compatible Chat Completions APIs).
	 *
	 * @return array<string, array{label:string, base_url:string, model:string, key_url:string}>
	 */
	public static function presets(): array {
		return array(
			'deepseek'   => array(
				'label'    => 'DeepSeek',
				'base_url' => 'https://api.deepseek.com/v1',
				'model'    => 'deepseek-chat',
				'key_url'  => 'https://platform.deepseek.com/api_keys',
			),
			'openai'     => array(
				'label'    => 'OpenAI',
				'base_url' => 'https://api.openai.com/v1',
				'model'    => 'gpt-4o-mini',
				'key_url'  => 'https://platform.openai.com/api-keys',
			),
			'openrouter' => array(
				'label'    => 'OpenRouter',
				'base_url' => 'https://openrouter.ai/api/v1',
				'model'    => 'deepseek/deepseek-chat',
				'key_url'  => 'https://openrouter.ai/keys',
			),
			'groq'       => array(
				'label'    => 'Groq',
				'base_url' => 'https://api.groq.com/openai/v1',
				'model'    => 'llama-3.3-70b-versatile',
				'key_url'  => 'https://console.groq.com/keys',
			),
			'custom'     => array(
				'label'    => 'Custom (OpenAI-compatible)',
				'base_url' => '',
				'model'    => '',
				'key_url'  => '',
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		$preset = self::presets()['deepseek'];

		return array(
			'provider'            => 'deepseek',
			'base_url'            => $preset['base_url'],
			'model'               => $preset['model'],
			'temperature'         => 0.2,
			'default_form_status' => 'draft',
			'prefer_efb'          => true,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array_merge( self::defaults(), $saved );
	}

	public static function get( string $key, mixed $default = null ): mixed {
		$all = self::all();

		return $all[ $key ] ?? $default;
	}

	/**
	 * @param array<string, mixed> $values
	 */
	public static function update( array $values ): void {
		$current = self::all();
		update_option( self::OPTION, array_merge( $current, $values ), false );
	}

	/**
	 * Store the provider API key encrypted. Empty string clears it.
	 */
	public static function set_api_key( string $plaintext ): void {
		if ( '' === trim( $plaintext ) ) {
			delete_option( self::KEY_OPTION );

			return;
		}

		$vault = KeyVault::fromWordPress();
		update_option( self::KEY_OPTION, $vault->encrypt( $plaintext ), false );
	}

	/**
	 * Decrypt and return the provider API key, or '' if none/failed.
	 */
	public static function get_api_key(): string {
		$stored = get_option( self::KEY_OPTION, '' );
		if ( ! is_string( $stored ) || '' === $stored ) {
			return '';
		}

		try {
			return KeyVault::fromWordPress()->decrypt( $stored );
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	public static function has_api_key(): bool {
		$stored = get_option( self::KEY_OPTION, '' );

		return is_string( $stored ) && '' !== $stored;
	}
}
