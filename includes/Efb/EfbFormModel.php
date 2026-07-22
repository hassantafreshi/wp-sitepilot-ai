<?php
declare(strict_types=1);

namespace SitePilot\Efb;

/**
 * Loads the compact EFB form model (field capabilities + default templates).
 *
 * `ai_capabilities` is the small catalog sent to the AI. `templates` hold the
 * full EFB default property maps applied locally by the converter and are never
 * sent to the AI — this keeps the model payload tiny.
 */
final class EfbFormModel {

	/** @var array<string, mixed>|null */
	private static ?array $data = null;

	/**
	 * @return array<string, mixed>
	 */
	public static function data(): array {
		if ( null === self::$data ) {
			$file = __DIR__ . '/efb-form-model.json';
			$raw  = is_readable( $file ) ? (string) file_get_contents( $file ) : '{}';
			$decoded = json_decode( $raw, true );
			self::$data = is_array( $decoded ) ? $decoded : array();
		}

		return self::$data;
	}

	/** @return array<int, array<string, mixed>> */
	public static function capabilities(): array {
		$caps = self::data()['ai_capabilities'] ?? array();

		return is_array( $caps ) ? $caps : array();
	}

	/** @return string[] */
	public static function allowed_types(): array {
		return array_values(
			array_filter(
				array_map(
					static fn ( $c ) => is_array( $c ) ? (string) ( $c['type'] ?? '' ) : '',
					self::capabilities()
				)
			)
		);
	}

	/** @return string[] Types that require an options array. */
	public static function option_types(): array {
		$out = array();
		foreach ( self::capabilities() as $c ) {
			if ( is_array( $c ) && ! empty( $c['needs_options'] ) ) {
				$out[] = (string) $c['type'];
			}
		}

		return $out;
	}

	/** @return array<string, string> */
	public static function aliases(): array {
		$a = self::data()['type_aliases'] ?? array();

		return is_array( $a ) ? $a : array();
	}

	/** @return string[] */
	public static function forbidden_types(): array {
		$f = self::data()['forbidden_types'] ?? array();

		return is_array( $f ) ? $f : array();
	}

	/** @return array<string, int> */
	public static function limits(): array {
		$l = self::data()['limits'] ?? array();

		return is_array( $l ) ? $l : array();
	}

	/** @return array<string, string> Width keyword => EFB size percent. */
	public static function width_map(): array {
		$w = self::data()['width_map'] ?? array();

		return is_array( $w ) && array() !== $w ? $w : array( 'full' => '100', 'half' => '50', 'third' => '33', 'quarter' => '25' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function template( string $key ): array {
		$t = self::data()['templates'][ $key ] ?? array();

		return is_array( $t ) ? $t : array();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function overrides( string $type ): array {
		$o = self::data()['templates']['field_overrides'][ $type ] ?? array();

		return is_array( $o ) ? $o : array();
	}

	/**
	 * Compact capability list rendered for the AI prompt.
	 */
	public static function capabilities_for_prompt(): string {
		$lines = array();
		foreach ( self::capabilities() as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$lines[] = sprintf(
				'- %s: %s%s',
				(string) $c['type'],
				(string) ( $c['label'] ?? '' ),
				! empty( $c['needs_options'] ) ? ' (requires "options")' : ''
			);
		}

		return implode( "\n", $lines );
	}
}
