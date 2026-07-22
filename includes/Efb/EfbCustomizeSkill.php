<?php
declare(strict_types=1);

namespace SitePilot\Efb;

use SitePilot\Providers\ProviderInterface;

/**
 * Translates a plain-language customization command ("make phone required",
 * "reduce the width of email", "rename the form to Contact us") into a small,
 * strictly-validated set of edit operations the browser applies to the live
 * Easy Form Builder structure.
 */
final class EfbCustomizeSkill {

	private const OPS   = array( 'set_form_name', 'set_confirmation', 'set_field', 'remove_field', 'add_option', 'remove_option' );
	private const PROPS = array( 'label', 'required', 'placeholder', 'width', 'help' );
	private const WIDTHS = array( 'full', 'half', 'third', 'quarter' );

	private ProviderInterface $provider;

	public function __construct( ProviderInterface $provider ) {
		$this->provider = $provider;
	}

	/**
	 * @param array<int, array{id:string,label:string,type:string}> $fields
	 * @return array<int, array<string, mixed>> Validated operations.
	 * @throws \SitePilot\Providers\ProviderException
	 */
	public function plan( string $command, array $fields ): array {
		$raw = $this->provider->generate_structured_output(
			$this->system_prompt(),
			$this->user_prompt( $command, $fields )
		);

		$ops = $raw['operations'] ?? array();

		return is_array( $ops ) ? $this->validate( $ops ) : array();
	}

	/**
	 * @param array<mixed> $ops
	 * @return array<int, array<string, mixed>>
	 */
	private function validate( array $ops ): array {
		$out = array();

		foreach ( $ops as $op ) {
			if ( ! is_array( $op ) ) {
				continue;
			}
			$name = (string) ( $op['op'] ?? '' );
			if ( ! in_array( $name, self::OPS, true ) ) {
				continue;
			}

			switch ( $name ) {
				case 'set_form_name':
				case 'set_confirmation':
					$value = $this->clean( (string) ( $op['value'] ?? '' ), 300 );
					if ( '' !== $value ) {
						$out[] = array( 'op' => $name, 'value' => $value );
					}
					break;

				case 'set_field':
					$target = $this->clean( (string) ( $op['target'] ?? '' ), 120 );
					$prop   = strtolower( (string) ( $op['prop'] ?? '' ) );
					if ( '' === $target || ! in_array( $prop, self::PROPS, true ) ) {
						break;
					}
					$value = $op['value'] ?? '';
					if ( 'required' === $prop ) {
						$value = $this->to_bool( $value );
					} elseif ( 'width' === $prop ) {
						$value = strtolower( (string) $value );
						if ( ! in_array( $value, self::WIDTHS, true ) ) {
							break;
						}
					} else {
						$value = $this->clean( (string) $value, 200 );
					}
					$out[] = array( 'op' => 'set_field', 'target' => $target, 'prop' => $prop, 'value' => $value );
					break;

				case 'remove_field':
					$target = $this->clean( (string) ( $op['target'] ?? '' ), 120 );
					if ( '' !== $target ) {
						$out[] = array( 'op' => 'remove_field', 'target' => $target );
					}
					break;

				case 'add_option':
				case 'remove_option':
					$target = $this->clean( (string) ( $op['target'] ?? '' ), 120 );
					$value  = $this->clean( (string) ( $op['value'] ?? '' ), 120 );
					if ( '' !== $target && '' !== $value ) {
						$out[] = array( 'op' => $name, 'target' => $target, 'value' => $value );
					}
					break;
			}

			if ( count( $out ) >= 20 ) {
				break;
			}
		}

		return $out;
	}

	private function system_prompt(): string {
		return <<<PROMPT
You convert a WordPress admin's plain-language request into edit operations for an existing form.
Respond with ONLY a JSON object: { "operations": [ ... ] }. No prose, no code fences.

Allowed operations (use the field's exact label or id from the provided list as "target"):
- { "op": "set_form_name", "value": "New form title" }
- { "op": "set_confirmation", "value": "Thank-you message shown after submit" }
- { "op": "set_field", "target": "<field label or id>", "prop": "label|required|placeholder|width|help", "value": <string, or true/false for required, or one of full|half|third|quarter for width> }
- { "op": "remove_field", "target": "<field label or id>" }
- { "op": "add_option", "target": "<choice field label or id>", "value": "Option text" }
- { "op": "remove_option", "target": "<choice field label or id>", "value": "Option text" }

Rules:
- Only emit operations that clearly match the request. If nothing matches, return {"operations":[]}.
- Never invent fields that are not in the provided list (except values for set_field/add_option).
- "width": full=100%, half=50%, third=33%, quarter=25%.
PROMPT;
	}

	/**
	 * @param array<int, array{id:string,label:string,type:string}> $fields
	 */
	private function user_prompt( string $command, array $fields ): string {
		$list = array();
		foreach ( $fields as $f ) {
			$list[] = sprintf( '- id="%s" label="%s" type=%s', $f['id'] ?? '', $f['label'] ?? '', $f['type'] ?? '' );
		}
		$fields_text = implode( "\n", $list );
		$command     = trim( $command );

		return "Current fields:\n{$fields_text}\n\nAdmin request (untrusted):\n<<<REQ\n{$command}\nREQ;";
	}

	private function clean( string $v, int $max ): string {
		$v = trim( (string) preg_replace( '/\s+/', ' ', strip_tags( $v ) ) );

		return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, $max ) : substr( $v, 0, $max );
	}

	private function to_bool( $v ): bool {
		if ( is_bool( $v ) ) {
			return $v;
		}

		return in_array( strtolower( (string) $v ), array( 'true', '1', 'yes', 'required' ), true );
	}
}
