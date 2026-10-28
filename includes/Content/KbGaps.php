<?php
declare(strict_types=1);

namespace AISAAutoPilot\Content;

use AISAAutoPilot\Support\OptionLock;

/**
 * Records questions the Knowledge Base could NOT answer, so the site owner
 * can review them in the admin dashboard, write a saved answer, or draft new content.
 *
 * Stored in a single wp_option as a compact list keyed by a hash of the
 * normalised question. Repeated questions bump a counter instead of piling up.
 * Writes run under a named lock because concurrent visitor requests record gaps.
 */
final class KbGaps {

	private const OPTION   = 'aisaautopilot_kb_gaps';
	private const MAX_KEEP = 200;

	/** Why a question counted as unanswered, set where ChatEngine records it. */
	public const REASONS = array( 'no_context', 'model_not_found', 'empty_answer', 'no_citation' );

	/** Windows the gap list can be filtered by. */
	public const WINDOWS = array( 'all', '7d' );

	/**
	 * Record an unanswered question (or bump its counter if already logged).
	 */
	public function record( string $question, string $language = '', string $reason = '' ): void {
		$question = trim( wp_strip_all_tags( $question ) );
		if ( '' === $question || mb_strlen( $question ) < 3 ) {
			return;
		}
		$reason = in_array( $reason, self::REASONS, true ) ? $reason : '';
		$key    = self::key( $question );
		if ( '' === $key ) {
			return;
		}
		$now = gmdate( 'c' );

		OptionLock::mutate(
			self::OPTION,
			static function ( array $gaps ) use ( $key, $question, $language, $reason, $now ): array {
				if ( isset( $gaps[ $key ] ) && is_array( $gaps[ $key ] ) ) {
					$entry              = self::normalize_entry( $gaps[ $key ] );
					$entry['count']     = $entry['count'] + 1;
					$entry['last_seen'] = $now;
					$entry['status']    = 'open';
					if ( '' !== $reason ) {
						$entry['reason'] = $reason;
					}
					$gaps[ $key ] = $entry;
				} else {
					$gaps[ $key ] = array(
						'question'      => mb_substr( $question, 0, 300 ),
						'language'      => $language,
						'count'         => 1,
						'first_seen'    => $now,
						'last_seen'     => $now,
						'status'        => 'open',
						'reason'        => $reason,
						'draft_post_id' => 0,
					);
				}

				// Keep the list bounded: drop the least-recently-seen entries.
				if ( count( $gaps ) > self::MAX_KEEP ) {
					$gaps = array_map( array( self::class, 'normalize_entry' ), $gaps );
					uasort(
						$gaps,
						static function ( array $a, array $b ): int {
							return strcmp( $b['last_seen'], $a['last_seen'] );
						}
					);
					$gaps = array_slice( $gaps, 0, self::MAX_KEEP, true );
				}
				return $gaps;
			}
		);
	}

	/**
	 * All logged gaps, keyed by hash, in the current shape (older entries are read with defaults).
	 *
	 * @return array<string, array{question:string,language:string,count:int,first_seen:string,last_seen:string,status:string,reason:string,draft_post_id:int}>
	 */
	public function all(): array {
		$gaps = get_option( self::OPTION, array() );
		if ( ! is_array( $gaps ) ) {
			return array();
		}
		$out = array();
		foreach ( $gaps as $hash => $gap ) {
			if ( is_array( $gap ) ) {
				$out[ (string) $hash ] = self::normalize_entry( $gap );
			}
		}
		return $out;
	}

	/**
	 * One open gap, or null when it is gone or dismissed.
	 *
	 * @return array{question:string,language:string,count:int,first_seen:string,last_seen:string,status:string,reason:string,draft_post_id:int,hash:string}|null
	 */
	public function get( string $hash ): ?array {
		if ( 1 !== preg_match( '/^[a-f0-9]{32}$/', $hash ) ) {
			return null;
		}
		$gaps = $this->all();
		if ( ! isset( $gaps[ $hash ] ) || 'open' !== $gaps[ $hash ]['status'] ) {
			return null;
		}
		return $gaps[ $hash ] + array( 'hash' => $hash );
	}

	/**
	 * Open gaps as a flat list, most-asked first, with a readable reason and count.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function open_list( int $limit = 50, string $window = 'all' ): array {
		$now  = time();
		$gaps = array_filter(
			$this->all(),
			static function ( array $g ) use ( $window, $now ): bool {
				return 'open' === $g['status'] && self::in_window( $g['last_seen'], $window, $now );
			}
		);

		uasort(
			$gaps,
			static function ( array $a, array $b ): int {
				$c = $b['count'] <=> $a['count'];
				return 0 !== $c ? $c : strcmp( $b['last_seen'], $a['last_seen'] );
			}
		);

		$out = array();
		foreach ( $gaps as $hash => $g ) {
			$g['hash']         = $hash;
			$g['reason_label'] = self::reason_label( $g['reason'] );
			$g['asked_label']  = sprintf(
				/* translators: %s: how many times visitors asked the question. */
				_n( 'Asked %s time', 'Asked %s times', $g['count'], 'ai-site-assistant-autopilot' ),
				number_format_i18n( $g['count'] )
			);
			$g['draft'] = $this->draft( $g['draft_post_id'] );
			$out[]      = $g;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return $out;
	}

	public function open_count( string $window = 'all' ): int {
		$now = time();
		return count(
			array_filter(
				$this->all(),
				static function ( array $g ) use ( $window, $now ): bool {
					return 'open' === $g['status'] && self::in_window( $g['last_seen'], $window, $now );
				}
			)
		);
	}

	/**
	 * Dismiss a single gap (mark resolved/ignored).
	 */
	public function dismiss( string $hash ): void {
		OptionLock::mutate(
			self::OPTION,
			static function ( array $gaps ) use ( $hash ): array {
				unset( $gaps[ $hash ] );
				return $gaps;
			}
		);
	}

	/**
	 * Links a Content Studio draft to the gap it answers. The gap stays open until it is dismissed.
	 */
	public function link_draft( string $hash, int $post_id ): bool {
		if ( $post_id <= 0 || null === $this->get( $hash ) ) {
			return false;
		}
		OptionLock::mutate(
			self::OPTION,
			static function ( array $gaps ) use ( $hash, $post_id ): array {
				if ( isset( $gaps[ $hash ] ) && is_array( $gaps[ $hash ] ) ) {
					$entry                  = self::normalize_entry( $gaps[ $hash ] );
					$entry['draft_post_id'] = $post_id;
					$gaps[ $hash ]          = $entry;
				}
				return $gaps;
			}
		);
		return true;
	}

	public function clear(): void {
		delete_option( self::OPTION );
	}

	/**
	 * The hash a question is stored under: the shared rule in QuestionKey, so punctuation,
	 * case and Arabic or Persian letter forms do not split one question into several entries.
	 * Entries recorded under the earlier lower-case-only hash keep their key and links.
	 */
	public static function key( string $question ): string {
		return QuestionKey::of( $question );
	}

	/** A short explanation of why the question counted as unanswered. */
	public static function reason_label( string $reason ): string {
		switch ( $reason ) {
			case 'no_context':
				return __( 'Nothing indexed on your site matched this question.', 'ai-site-assistant-autopilot' );
			case 'model_not_found':
				return __( 'Related pages were found, but none of them answered it.', 'ai-site-assistant-autopilot' );
			case 'empty_answer':
				return __( 'The AI service returned an empty answer.', 'ai-site-assistant-autopilot' );
			case 'no_citation':
				return __( 'An answer was given without citing a page, so it may not come from your site.', 'ai-site-assistant-autopilot' );
		}
		return __( 'Recorded before reasons were kept.', 'ai-site-assistant-autopilot' );
	}

	/** Whether a gap last seen at an ISO time falls inside a window ('all' or '7d'). */
	public static function in_window( string $last_seen, string $window, int $now ): bool {
		if ( '7d' !== $window ) {
			return true;
		}
		$seen = strtotime( $last_seen );
		return false !== $seen && $seen >= $now - 7 * DAY_IN_SECONDS;
	}

	/**
	 * An entry in the current shape. Entries written before reasons were kept use 'first' and 'last'
	 * (MySQL GMT) instead of ISO 'first_seen' and 'last_seen'.
	 *
	 * @param array<string,mixed> $gap
	 * @return array{question:string,language:string,count:int,first_seen:string,last_seen:string,status:string,reason:string,draft_post_id:int}
	 */
	public static function normalize_entry( array $gap ): array {
		$iso    = static function ( $value ): string {
			$value = (string) $value;
			if ( '' === $value ) {
				return '';
			}
			if ( false !== strpos( $value, 'T' ) ) {
				return $value;
			}
			$time = strtotime( $value . ' UTC' );
			return false === $time ? '' : gmdate( 'c', $time );
		};
		$reason = (string) ( $gap['reason'] ?? '' );
		$last   = $iso( $gap['last_seen'] ?? ( $gap['last'] ?? '' ) );
		return array(
			'question'      => (string) ( $gap['question'] ?? '' ),
			'language'      => (string) ( $gap['language'] ?? '' ),
			'count'         => max( 1, (int) ( $gap['count'] ?? 1 ) ),
			'first_seen'    => $iso( $gap['first_seen'] ?? ( $gap['first'] ?? '' ) ),
			'last_seen'     => $last,
			'status'        => 'open' === ( $gap['status'] ?? 'open' ) ? 'open' : (string) $gap['status'],
			'reason'        => in_array( $reason, self::REASONS, true ) ? $reason : '',
			'draft_post_id' => max( 0, (int) ( $gap['draft_post_id'] ?? 0 ) ),
		);
	}

	/**
	 * The linked draft when it still exists, came from Content Studio and this user may edit it.
	 *
	 * @return array{id:int,title:string,status:string,url:string}|null
	 */
	private function draft( int $post_id ): ?array {
		if ( $post_id <= 0 || ! function_exists( 'get_post' ) ) {
			return null;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || ! current_user_can( 'edit_post', $post_id ) || '1' !== (string) get_post_meta( $post_id, '_aisaautopilot_generated', true ) ) {
			return null;
		}
		return array(
			'id'     => $post_id,
			'title'  => (string) get_the_title( $post ),
			'status' => (string) $post->post_status,
			'url'    => (string) get_edit_post_link( $post_id, 'raw' ),
		);
	}
}
