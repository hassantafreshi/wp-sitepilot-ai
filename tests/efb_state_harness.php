<?php
/**
 * Not a test. Runs a local WordPress test of the Easy Form Builder copilot in parts, each on one EFB plan and set of
 * add-ons, the way EFB itself has them (the registry contract):
 *
 *   - Easy Form Builder loads its add-ons once per request, from its own settings. So the plan and the add-on switches
 *     of a part are written first in a process of their own, through EFB's own settings function
 *     (`efbFunction::set_setting_Emsfb()`, never the `emsfb_settings` option itself) and EFB's licence flag
 *     (`emsfb_pro`, as EFB's own end-to-end suite does), and the part then runs in a fresh process that sees them only
 *     through EFB's registry (`capabilities_efb()`), the same as the copilot does.
 *   - Afterwards the add-on switches and the plan the site had are put back the same way, also after a failure.
 *
 *   $parts = array( 'name' => array( 'plan' => 1, 'addons' => array( 'AdnSPF' => 1 ) ), ... );
 *   aisa_efb_parts( __FILE__, $parts, static function ( string $part ) { ... checks ... } );
 *
 * A part callback returns its checks as array( array( 'ok' => bool, 'name' => string ), ... ). The parent prints one
 * PASS / FAIL line per check and "<n> checks, <f> failed", and exits 1 on any failure.
 * The WordPress folder comes from tests/test-env.php (AISAAUTOPILOT_WP_ROOT, the local copy, the owner's XAMPP).
 */

require_once __DIR__ . '/test-env.php';

if ( ! function_exists( 'aisa_efb_parts' ) ) {

	/**
	 * @param array<string,array{plan:int,addons:array<string,int>}> $parts
	 * @param callable(string):array<int,array{ok:bool,name:string}>  $run
	 */
	function aisa_efb_parts( string $file, array $parts, callable $run ): void {
		global $argv;
		$mode = '';
		$arg  = '';
		foreach ( array_slice( (array) $argv, 1 ) as $a ) {
			if ( 1 === preg_match( '/^--(state|part)=(.*)$/s', (string) $a, $m ) ) {
				$mode = $m[1];
				$arg  = $m[2];
			}
		}
		if ( 'state' === $mode ) {
			aisa_efb_child_state( (array) json_decode( $arg, true ) );
			return;
		}
		if ( '' !== $mode ) {
			register_shutdown_function(
				static function (): void {
					$e = error_get_last();
					if ( is_array( $e ) && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) {
						echo "\nFATAL: " . $e['message'] . ' @' . $e['file'] . ':' . $e['line'] . "\n";
					}
				}
			);
		}
		if ( 'part' === $mode ) {
			$checks = aisa_efb_child_boot( static fn (): array => $run( $arg ) );
			echo "\n" . 'AISA_PART_RESULT ' . wp_json_encode( $checks ) . "\n";
			return;
		}
		aisa_efb_parent( $file, $parts );
	}

	/** Loads WordPress (the child processes only). */
	function aisa_efb_child_boot( callable $then ) {
		if ( ! defined( 'WP_DISABLE_FATAL_ERROR_HANDLER' ) ) {
			define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true ); // A fatal error is printed as it is, not as WordPress's error page.
		}
		require_once aisa_test_wp_root() . '/wp-load.php';
		return $then();
	}

	/** Child: writes the plan and the add-on switches through EFB, prints the ones it replaced. */
	function aisa_efb_child_state( array $want ): void {
		aisa_efb_child_boot(
			static function () use ( $want ): void {
				if ( ! function_exists( 'get_setting_Emsfb' ) || ! class_exists( '\\efbFunction' ) || ! method_exists( '\\efbFunction', 'set_setting_Emsfb' ) ) {
					echo "\nAISA_STATE " . wp_json_encode( array( 'error' => 'Easy Form Builder (with its settings API) is not active on this site' ) ) . "\n";
					return;
				}
				$before   = array(
					'plan'   => (int) get_option( 'emsfb_pro', 2 ),
					'addons' => array(),
				);
				$settings = get_setting_Emsfb( 'decoded' );
				$settings = is_object( $settings ) ? $settings : new stdClass();
				foreach ( (array) ( $want['addons'] ?? array() ) as $code => $on ) {
					$code                       = preg_replace( '/[^A-Za-z]/', '', (string) $code );
					$before['addons'][ $code ] = (int) ( $settings->$code ?? 0 );
					$settings->$code            = (int) $on ? 1 : 0;
				}
				if ( isset( $want['plan'] ) ) {
					// The settings row's package_type too, as EFB's own e2e suite does: a later settings write syncs
					// emsfb_pro from that row, so a plan written only to the option comes back on the next write.
					$settings->package_type = (int) $want['plan'];
				}
				\efbFunction::set_setting_Emsfb( $settings );
				if ( isset( $want['plan'] ) ) {
					update_option( 'emsfb_pro', (int) $want['plan'] );
				}
				$after = get_setting_Emsfb( 'decoded' );
				$ok    = true;
				foreach ( (array) ( $want['addons'] ?? array() ) as $code => $on ) {
					$ok = $ok && (int) ( $after->$code ?? 0 ) === ( (int) $on ? 1 : 0 );
				}
				echo "\nAISA_STATE " . wp_json_encode( array( 'before' => $before, 'ok' => $ok ) ) . "\n";
			}
		);
	}

	/** Runs a child process of this test; returns its output. */
	function aisa_efb_child( string $file, string $arg ): string {
		$php = getenv( 'AISAAUTOPILOT_PHP' ) ?: PHP_BINARY;
		$cmd = escapeshellarg( $php ) . ' ' . escapeshellarg( $file ) . ' ' . escapeshellarg( $arg ) . ' 2>&1';
		$env = 'AISAAUTOPILOT_WP_ROOT=' . aisa_test_wp_root();
		putenv( $env );
		return (string) shell_exec( $cmd );
	}

	/** The JSON a child printed after $marker. */
	function aisa_efb_marker( string $out, string $marker ) {
		if ( 1 !== preg_match( '/^' . preg_quote( $marker, '/' ) . ' (.*)$/m', $out, $m ) ) {
			return null;
		}
		return json_decode( $m[1], true );
	}

	function aisa_efb_parent( string $file, array $parts ): void {
		$checks  = array();
		$restore = array(
			'plan'   => null,
			'addons' => array(),
		);
		$set     = static function ( array $want ) use ( $file, &$restore ): ?string {
			$out   = aisa_efb_child( $file, '--state=' . wp_json_encode_lite( $want ) );
			$state = aisa_efb_marker( $out, 'AISA_STATE' );
			if ( ! is_array( $state ) || ! empty( $state['error'] ) || empty( $state['ok'] ) ) {
				return is_array( $state ) && ! empty( $state['error'] ) ? (string) $state['error'] : 'the plan and add-ons could not be set: ' . substr( trim( $out ), -400 );
			}
			// The first value seen of each switch is the site's own: that is what is put back.
			if ( null === $restore['plan'] ) {
				$restore['plan'] = (int) $state['before']['plan'];
			}
			foreach ( (array) $state['before']['addons'] as $code => $on ) {
				if ( ! array_key_exists( $code, $restore['addons'] ) ) {
					$restore['addons'][ $code ] = (int) $on;
				}
			}
			return null;
		};
		try {
			foreach ( $parts as $name => $state ) {
				$error = $set( $state );
				if ( null !== $error ) {
					$checks[] = array( 'ok' => false, 'name' => "[$name] setup: $error" );
					continue;
				}
				$out  = aisa_efb_child( $file, '--part=' . $name );
				$part = aisa_efb_marker( $out, 'AISA_PART_RESULT' );
				if ( ! is_array( $part ) ) {
					$checks[] = array( 'ok' => false, 'name' => "[$name] the part did not finish: " . substr( trim( $out ), -600 ) );
					continue;
				}
				foreach ( $part as $c ) {
					$checks[] = array( 'ok' => ! empty( $c['ok'] ), 'name' => "[$name] " . (string) ( $c['name'] ?? '' ) );
				}
			}
		} finally {
			if ( null !== $restore['plan'] ) {
				$error = $set( $restore );
				if ( null !== $error ) {
					$checks[] = array( 'ok' => false, 'name' => 'The site\'s own plan and add-ons are put back: ' . $error );
				}
			}
		}
		$failed = 0;
		foreach ( $checks as $c ) {
			echo ( $c['ok'] ? 'PASS ' : 'FAIL ' ) . $c['name'] . "\n";
			$failed += $c['ok'] ? 0 : 1;
		}
		echo count( $checks ) . " checks, {$failed} failed\n";
		exit( $failed > 0 ? 1 : 0 );
	}

	/** json_encode without WordPress (the parent never loads it). */
	function wp_json_encode_lite( $data ): string {
		return (string) json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
}
