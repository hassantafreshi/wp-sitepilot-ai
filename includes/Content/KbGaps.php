<?php
declare(strict_types=1);

namespace SitePilot\Content;

/**
 * Records questions the Knowledge Base could NOT answer, so the site owner
 * can review them in the admin dashboard and write / index new content.
 *
 * Stored in a single wp_option as a compact list keyed by a hash of the
 * normalised question. Repeated questions bump a counter instead of piling up.
 */
final class KbGaps {

	private const OPTION   = 'sitepilot_kb_gaps';
	private const MAX_KEEP = 200;

	/**
	 * Record an unanswered question (or bump its counter if already logged).
	 */
	public function record( string $question, string $language = '' ): void {
		$question = trim( wp_strip_all_tags( $question ) );
		if ( '' === $question || mb_strlen( $question ) < 3 ) {
			return;
		}

		$gaps = $this->all();
		$key  = md5( $this->normalise( $question ) );
		$now  = current_time( 'mysql', true );

		if ( isset( $gaps[ $key ] ) ) {
			$gaps[ $key ]['count']   = (int) $gaps[ $key ]['count'] + 1;
			$gaps[ $key ]['last']    = $now;
			$gaps[ $key ]['status']  = 'open';
		} else {
			$gaps[ $key ] = array(
				'question' => mb_substr( $question, 0, 300 ),
				'language' => $language,
				'count'    => 1,
				'first'    => $now,
				'last'     => $now,
				'status'   => 'open',
			);
		}

		// Keep the list bounded: drop the least-recently-seen entries.
		if ( count( $gaps ) > self::MAX_KEEP ) {
			uasort( $gaps, static function ( $a, $b ) {
				return strcmp( (string) $b['last'], (string) $a['last'] );
			} );
			$gaps = array_slice( $gaps, 0, self::MAX_KEEP, true );
		}

		update_option( self::OPTION, $gaps, false );
	}

	/**
	 * All logged gaps, keyed by hash.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		$gaps = get_option( self::OPTION, array() );

		return is_array( $gaps ) ? $gaps : array();
	}

	/**
	 * Open gaps as a flat list, most-asked first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function open_list( int $limit = 50 ): array {
		$gaps = array_filter( $this->all(), static function ( $g ) {
			return ( $g['status'] ?? 'open' ) === 'open';
		} );

		uasort( $gaps, static function ( $a, $b ) {
			$c = ( (int) $b['count'] ) <=> ( (int) $a['count'] );
			return 0 !== $c ? $c : strcmp( (string) $b['last'], (string) $a['last'] );
		} );

		$out = array();
		foreach ( $gaps as $hash => $g ) {
			$g['hash'] = $hash;
			$out[]     = $g;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return $out;
	}

	public function open_count(): int {
		return count( array_filter( $this->all(), static function ( $g ) {
			return ( $g['status'] ?? 'open' ) === 'open';
		} ) );
	}

	/**
	 * Dismiss a single gap (mark resolved/ignored).
	 */
	public function dismiss( string $hash ): void {
		$gaps = $this->all();
		if ( isset( $gaps[ $hash ] ) ) {
			unset( $gaps[ $hash ] );
			update_option( self::OPTION, $gaps, false );
		}
	}

	public function clear(): void {
		delete_option( self::OPTION );
	}

	private function normalise( string $q ): string {
		$q = mb_strtolower( $q );
		$q = (string) preg_replace( '/\s+/u', ' ', $q );

		return trim( $q );
	}
}
