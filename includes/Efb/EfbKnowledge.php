<?php
declare(strict_types=1);

namespace AISAAutoPilot\Efb;

/**
 * Answers "how do I …?" questions about the Easy Form Builder editor from a
 * curated, grounded knowledge base (no hallucinated UI steps).
 */
final class EfbKnowledge {

	/** @var array<string, mixed>|null */
	private static ?array $data = null;

	/** @return array<string, mixed> */
	public static function data(): array {
		if ( null === self::$data ) {
			$file       = __DIR__ . '/efb-knowledge.json';
			$raw        = is_readable( $file ) ? (string) file_get_contents( $file ) : '{}';
			$decoded    = json_decode( $raw, true );
			self::$data = is_array( $decoded ) ? $decoded : array();
		}

		return self::$data;
	}

	/** @return array<int, array<string, mixed>> */
	public static function entries(): array {
		$e = self::data()['entries'] ?? array();

		return is_array( $e ) ? $e : array();
	}

	/**
	 * Find the best-matching entry for a question and return a full instruction.
	 *
	 * @return array{ok:bool, id?:string, title?:string, answer?:string, suggestions?:array<int,array{id:string,title:string}>}
	 */
	public static function answer( string $question ): array {
		$q = self::normalize( $question );
		if ( '' === $q ) {
			return self::suggestions();
		}
		// Common words match half the entries ("how do I add the form ..."), so only words that say
		// something count; a question that shares none is answered with the topic list, not a wrong article.
		$stop   = array( 'the', 'and', 'for', 'how', 'can', 'you', 'your', 'with', 'this', 'that', 'form', 'forms', 'field', 'fields', 'add', 'set', 'make', 'change', 'want', 'need', 'please', 'into', 'from', 'what', 'where', 'does', 'its', 'are', 'not', 'use' );
		$tokens = array_filter( explode( ' ', $q ), static fn ( $t ) => strlen( $t ) >= 3 && ! in_array( $t, $stop, true ) );

		$best       = null;
		$best_score = 0;

		foreach ( self::entries() as $entry ) {
			$score     = 0;
			$aliases   = (array) ( $entry['aliases'] ?? array() );
			$aliases[] = (string) ( $entry['title'] ?? '' );

			foreach ( $aliases as $alias ) {
				$a = self::normalize( (string) $alias );
				if ( '' === $a ) {
					continue;
				}
				// Whole alias phrase appears in the question (strong signal).
				if ( str_contains( $q, $a ) ) {
					$score += 5 + substr_count( $a, ' ' );
				}
				// Token overlap (weaker signal).
				$alias_words = explode( ' ', $a );
				foreach ( $tokens as $t ) {
					if ( in_array( $t, $alias_words, true ) ) {
						++$score;
					}
				}
			}

			if ( $score > $best_score ) {
				$best_score = $score;
				$best       = $entry;
			}
		}

		if ( null === $best || $best_score < 3 ) {
			return self::suggestions();
		}

		return array(
			'ok'     => true,
			'id'     => (string) $best['id'],
			'title'  => (string) $best['title'],
			'answer' => self::compose( $best ),
		);
	}

	/**
	 * @param array<string, mixed> $entry
	 */
	private static function compose( array $entry ): string {
		$ui    = self::data()['ui_pattern'] ?? array();
		$scope = (string) ( $entry['scope'] ?? 'field' );
		$steps = array();

		if ( 'field' === $scope && ! empty( $ui['open_field_settings'] ) ) {
			$steps[] = (string) $ui['open_field_settings'];
		} elseif ( 'form' === $scope && ! empty( $ui['open_form_settings'] ) ) {
			$steps[] = (string) $ui['open_form_settings'];
		}

		$steps[] = (string) ( $entry['answer'] ?? '' );

		if ( in_array( $scope, array( 'field', 'form' ), true ) && ! empty( $ui['save'] ) ) {
			$steps[] = (string) $ui['save'];
		}
		// What Easy Form Builder's own registry says about this site, so a how-to never promises what is not there.
		$steps[] = self::live_state( $entry );

		return implode( "\n\n", array_filter( $steps ) );
	}

	/**
	 * One paragraph on this site's state for an entry: each listed feature (ready, or the add-on / plan it needs) and,
	 * for some topics, the plan, the portal, the renewal, the add-ons or the gateways. Empty without EFB's registry.
	 *
	 * @param array<string, mixed> $entry
	 */
	public static function live_state( array $entry ): string {
		if ( ! EfbCapabilities::available() ) {
			return '';
		}
		$parts = array();
		foreach ( is_array( $entry['features'] ?? null ) ? $entry['features'] : array() as $key => $label ) {
			$state = EfbCapabilities::state( (string) $key );
			if ( ! $state['known'] ) {
				continue;
			}
			if ( $state['available'] ) {
				/* translators: %s: feature name. */
				$parts[] = sprintf( __( '%s: ready', 'ai-site-assistant-autopilot' ), (string) $label );
			} elseif ( in_array( $state['reason'], array( 'addon', 'update' ), true ) && null !== $state['addon'] ) {
				$addon   = EfbCapabilities::addon( $state['addon'] );
				$parts[] = 'update' === $state['reason']
					/* translators: 1: feature name, 2: add-on name. */
					? sprintf( __( '%1$s: update the %2$s add-on', 'ai-site-assistant-autopilot' ), (string) $label, $addon['name'] )
					/* translators: 1: feature name, 2: add-on name, 3: plan name. */
					: sprintf( __( '%1$s: needs the %2$s add-on (%3$s)', 'ai-site-assistant-autopilot' ), (string) $label, $addon['name'], EfbCapabilities::plan_label( $addon['package'] ) );
			} else {
				/* translators: 1: feature name, 2: plan name. */
				$parts[] = sprintf( __( '%1$s: needs %2$s', 'ai-site-assistant-autopilot' ), (string) $label, EfbCapabilities::plan_label( $state['package'] ) );
			}
		}
		$live = (string) ( $entry['live'] ?? '' );
		$plan = EfbCapabilities::plan();
		if ( 'plan' === $live ) {
			/* translators: %s: plan name. */
			$parts[] = sprintf( __( 'this site is on %s', 'ai-site-assistant-autopilot' ), EfbCapabilities::plan_label( 'pro_pending' === $plan ? 'free' : $plan ) );
			$note    = EfbCapabilities::plan_note( 'pro' );
			if ( '' !== $note ) {
				$parts[] = rtrim( $note, '.' );
			}
		} elseif ( 'portal' === $live ) {
			$portal  = EfbCapabilities::portal();
			$parts[] = null === $portal
				? __( 'this Easy Form Builder has no portal contract yet', 'ai-site-assistant-autopilot' )
				: ( ! empty( $portal['ready'] ) ? __( 'the customer portal is ready', 'ai-site-assistant-autopilot' ) : __( 'the customer portal is not ready: it needs the Customer Portal add-on on Pro', 'ai-site-assistant-autopilot' ) );
		} elseif ( 'renewal' === $live ) {
			$renewal = is_array( EfbCapabilities::block( 'plan' )['renewal'] ?? null ) ? EfbCapabilities::block( 'plan' )['renewal'] : array();
			if ( EfbCapabilities::one_time_purchase() ) {
				$parts[] = __( 'this is a Persian site: Pro is a one-time purchase and nothing is renewed', 'ai-site-assistant-autopilot' );
			} elseif ( ! empty( $renewal['known'] ) && '' !== (string) ( $renewal['ends_on'] ?? '' ) ) {
				/* translators: %s: date. */
				$parts[] = sprintf( __( 'Pro ends on %s', 'ai-site-assistant-autopilot' ), (string) $renewal['ends_on'] );
			} else {
				$parts[] = __( 'Easy Form Builder does not know an end date for this site', 'ai-site-assistant-autopilot' );
			}
		} elseif ( 'addons' === $live ) {
			foreach ( array_keys( EfbCapabilities::block( 'split' )['addons'] ?? array() ) as $code ) {
				$addon   = EfbCapabilities::addon( (string) $code );
				$parts[] = sprintf(
					/* translators: 1: add-on name, 2: plan name, 3: state. */
					__( '%1$s (%2$s): %3$s', 'ai-site-assistant-autopilot' ),
					$addon['name'],
					EfbCapabilities::plan_label( $addon['package'] ),
					$addon['active'] ? __( 'active', 'ai-site-assistant-autopilot' ) : ( 'update' === $addon['state'] ? __( 'needs an update', 'ai-site-assistant-autopilot' ) : ( 'plan' === $addon['state'] ? __( 'needs a higher plan', 'ai-site-assistant-autopilot' ) : __( 'not installed', 'ai-site-assistant-autopilot' ) ) )
				);
			}
		} elseif ( 'gateways' === $live ) {
			foreach ( EfbPayments::registry_status() as $row ) {
				if ( ! empty( $row['addon_active'] ) ) {
					$parts[] = sprintf(
						/* translators: 1: gateway name, 2: whether its keys are saved. */
						__( '%1$s: active, %2$s', 'ai-site-assistant-autopilot' ),
						(string) $row['label'],
						true === $row['keys_saved'] ? __( 'keys saved', 'ai-site-assistant-autopilot' ) : __( 'keys not saved yet', 'ai-site-assistant-autopilot' )
					);
				}
			}
			if ( array() === $parts ) {
				$parts[] = __( 'no payment gateway add-on is active', 'ai-site-assistant-autopilot' );
			}
		} elseif ( 'captcha' === $live ) {
			foreach ( EfbCapabilities::captcha_providers() as $provider ) {
				if ( ! empty( $provider['available'] ) ) {
					$parts[] = sprintf(
						/* translators: 1: CAPTCHA service, 2: whether its keys are set. */
						__( '%1$s: %2$s', 'ai-site-assistant-autopilot' ),
						(string) ( $provider['label'] ?? '' ),
						! empty( $provider['ready'] ) ? __( 'keys set', 'ai-site-assistant-autopilot' ) : __( 'keys not set', 'ai-site-assistant-autopilot' )
					);
				}
			}
		} elseif ( 'email_confirmation' === $live ) {
			$parts[] = EfbCapabilities::email_confirmation_default_on()
				? __( 'this Easy Form Builder confirms to the visitor by default', 'ai-site-assistant-autopilot' )
				: __( 'this Easy Form Builder leaves the visitor confirmation off until it is switched on', 'ai-site-assistant-autopilot' );
		} elseif ( 'data_tools' === $live ) {
			$tools   = EfbCapabilities::block( 'split' )['data_tools'] ?? null;
			$parts[] = is_array( $tools ) && ! empty( $tools['active'] )
				? __( 'Easy Form Builder Data Tools is active on this site', 'ai-site-assistant-autopilot' )
				: __( 'Easy Form Builder Data Tools is not active on this site', 'ai-site-assistant-autopilot' );
		}
		if ( array() === $parts ) {
			return '';
		}

		/* translators: %s: semicolon-separated states. */
		return sprintf( __( 'On this site: %s.', 'ai-site-assistant-autopilot' ), implode( '; ', $parts ) );
	}

	/**
	 * @return array{ok:bool, suggestions:array<int,array{id:string,title:string}>}
	 */
	private static function suggestions(): array {
		$out = array();
		foreach ( self::entries() as $entry ) {
			$out[] = array(
				'id'    => (string) $entry['id'],
				'title' => (string) $entry['title'],
			);
		}

		return array(
			'ok'          => false,
			'suggestions' => $out,
		);
	}

	private static function normalize( string $s ): string {
		$s = function_exists( 'mb_strtolower' ) ? mb_strtolower( $s ) : strtolower( $s );
		$s = (string) preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $s );

		return trim( (string) preg_replace( '/\s+/u', ' ', $s ) );
	}
}
