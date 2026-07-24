<?php
declare(strict_types=1);

namespace SitePilot\Content;

/**
 * Lightweight answer cache for the chat engine.
 *
 * When a stand-alone question is answered successfully, the answer + sources
 * are cached keyed by a hash of the normalised question AND the current
 * Knowledge Base version. When content is (re)indexed the KB version bumps,
 * which transparently invalidates every stale answer.
 *
 * This lets repeat questions skip both retrieval and the AI round-trip.
 */
final class KbCache {

	private const OPTION      = 'sitepilot_kb_answer_cache';
	private const VERSION_OPT = 'sitepilot_kb_version';
	private const MAX_KEEP    = 300;

	/**
	 * Current KB content version. Bumped whenever indexed content changes.
	 */
	public static function version(): int {
		return (int) get_option( self::VERSION_OPT, 1 );
	}

	/**
	 * Invalidate the whole cache by advancing the KB version.
	 */
	public static function bump_version(): void {
		update_option( self::VERSION_OPT, self::version() + 1, false );
	}

	/**
	 * Look up a cached answer for a question. Returns null on miss.
	 *
	 * @return array{answer:string, sources:array<int,array<string,mixed>>}|null
	 */
	public function get( string $question ): ?array {
		$store = $this->store();
		$key   = $this->key( $question );

		if ( ! isset( $store[ $key ] ) ) {
			return null;
		}

		$hit = $store[ $key ];
		if ( (int) ( $hit['version'] ?? 0 ) !== self::version() ) {
			return null; // Stale — KB changed since this was cached.
		}

		return array(
			'answer'  => (string) ( $hit['answer'] ?? '' ),
			'sources' => is_array( $hit['sources'] ?? null ) ? $hit['sources'] : array(),
		);
	}

	/**
	 * Store a successful answer.
	 *
	 * @param array<int,array<string,mixed>> $sources
	 */
	public function put( string $question, string $answer, array $sources ): void {
		if ( '' === trim( $answer ) ) {
			return;
		}

		$store = $this->store();
		$key   = $this->key( $question );

		$store[ $key ] = array(
			'question' => mb_substr( trim( wp_strip_all_tags( $question ) ), 0, 300 ),
			'answer'   => $answer,
			'sources'  => $sources,
			'version'  => self::version(),
			'ts'       => time(),
		);

		if ( count( $store ) > self::MAX_KEEP ) {
			uasort( $store, static function ( $a, $b ) {
				return ( (int) ( $b['ts'] ?? 0 ) ) <=> ( (int) ( $a['ts'] ?? 0 ) );
			} );
			$store = array_slice( $store, 0, self::MAX_KEEP, true );
		}

		update_option( self::OPTION, $store, false );
	}

	public function clear(): void {
		delete_option( self::OPTION );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function store(): array {
		$store = get_option( self::OPTION, array() );

		return is_array( $store ) ? $store : array();
	}

	private function key( string $question ): string {
		$q = mb_strtolower( trim( wp_strip_all_tags( $question ) ) );
		$q = (string) preg_replace( '/\s+/u', ' ', $q );

		return md5( $q );
	}
}
