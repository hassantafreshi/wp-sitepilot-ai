<?php
declare(strict_types=1);

namespace AISAAutoPilot\Content;

use AISAAutoPilot\Logging\Logger;

/**
 * Finds the passages that answer a question.
 *
 * Words first: BM25 over the passage index, with Persian, Arabic and English normalised
 * the same way on both sides. When semantic search (embeddings) is on, its ranking is
 * fused in with reciprocal-rank fusion, so a question worded differently from the page
 * still finds it. When nothing on the site shares a meaningful word with the question,
 * the answer is "not found" before any AI request is made.
 */
final class KnowledgeSearch {

	/** Passages handed to the model. */
	public const TOP_K = 8;

	/** At most this many passages from one post, so one long page cannot crowd out the rest. */
	private const PER_POST = 3;

	/** Characters of passage text given to the model (characters, not bytes: Persian is two bytes a letter). */
	public const MAX_CONTEXT_CHARS = 9000;

	/**
	 * Share of the question's weight (by term rarity) the best passage must cover before
	 * the model is asked at all. Low on purpose: the model still says "not found" when the
	 * passages do not answer; this only skips the request when nothing relates.
	 */
	public const MIN_COVERAGE = 0.2;

	private const BM25_K1 = 1.2;
	private const BM25_B  = 0.75;
	private const RRF_K   = 60;

	private KnowledgeIndex $index;
	private KnowledgeBase $kb;
	private Logger $logger;

	public function __construct( ?KnowledgeIndex $index = null, ?KnowledgeBase $kb = null, ?Logger $logger = null ) {
		$this->index  = $index ?? new KnowledgeIndex();
		$this->kb     = $kb ?? new KnowledgeBase();
		$this->logger = $logger ?? new Logger();
	}

	/**
	 * @param string $question   The visitor's question.
	 * @param string $prior_turn The previous user message, so "and how much is it?" keeps its subject.
	 * @return array{
	 *   found: bool,
	 *   context: string,
	 *   map: array<int, array{post_id:int, title:string, chunk_id:int, score:float}>,
	 *   coverage: float,
	 *   reason: string
	 * }
	 */
	public function retrieve( string $question, string $prior_turn = '' ): array {
		$none = array(
			'found'    => false,
			'context'  => '',
			'map'      => array(),
			'coverage' => 0.0,
			'reason'   => 'no_terms',
		);

		if ( ! KnowledgeIndex::ready() ) {
			$none['reason'] = 'not_ready';
			return $none;
		}
		if ( 0 === $this->index->stats()['chunks'] ) {
			$none['reason'] = 'empty_index';
			return $none;
		}

		$query_terms = $this->known_forms( array_values( array_unique( KnowledgeText::tokens( $question ) ) ) );
		$prior_terms = array_values( array_diff( $this->known_forms( array_values( array_unique( KnowledgeText::tokens( $prior_turn ) ) ) ), $query_terms ) );
		if ( array() === $query_terms && array() === $prior_terms ) {
			return $none;
		}

		$ranked   = $this->rank( $query_terms, $prior_terms );
		$coverage = $ranked['coverage'];
		$scores   = $ranked['scores'];

		$semantic = $this->semantic_posts( $question );
		if ( array() === $scores && array() === $semantic ) {
			$none['reason'] = 'no_match';
			return $none;
		}
		if ( $coverage < self::MIN_COVERAGE && array() === $semantic ) {
			$none['reason']   = 'low_coverage';
			$none['coverage'] = $coverage;
			return $none;
		}

		$chunk_ids = $this->fuse( $scores, $semantic );
		$chunks    = $this->index->chunks_by_id( $chunk_ids );
		$titles    = $this->titles( array_map( static fn( $c ) => $c['post_id'], $chunks ) );

		$context  = '';
		$map      = array();
		$per_post = array();
		$n        = 0;
		$top      = array() === $scores ? 1.0 : max( $scores );
		foreach ( $chunk_ids as $chunk_id ) {
			if ( ! isset( $chunks[ $chunk_id ] ) ) {
				continue;
			}
			$chunk   = $chunks[ $chunk_id ];
			$post_id = $chunk['post_id'];
			if ( ( $per_post[ $post_id ] ?? 0 ) >= self::PER_POST || ! $this->still_public( $post_id ) ) {
				continue;
			}
			$title = $titles[ $post_id ] ?? '';
			$label = '' !== $chunk['heading'] ? $title . ' › ' . $chunk['heading'] : $title;
			$entry = sprintf( "[%d] %s\n%s\n\n", $n + 1, $label, $chunk['content'] );
			if ( '' !== $context && mb_strlen( $context ) + mb_strlen( $entry ) > self::MAX_CONTEXT_CHARS ) {
				break;
			}
			++$n;
			$per_post[ $post_id ] = ( $per_post[ $post_id ] ?? 0 ) + 1;
			$context             .= $entry;
			$map[ $n ]            = array(
				'post_id'  => $post_id,
				'title'    => $title,
				'chunk_id' => $chunk_id,
				'score'    => round( ( $scores[ $chunk_id ] ?? 0.0 ) / ( $top > 0 ? $top : 1 ), 2 ),
			);
			if ( $n >= self::TOP_K ) {
				break;
			}
		}

		return array(
			'found'    => '' !== $context,
			'context'  => trim( $context ),
			'map'      => $map,
			'coverage' => $coverage,
			'reason'   => '' !== $context ? 'ok' : 'no_match',
		);
	}

	/**
	 * BM25 scores per passage, plus how much of the question the best passage covers.
	 *
	 * @param array<int, string> $query_terms
	 * @param array<int, string> $prior_terms Terms from the previous turn, weighted lower.
	 * @return array{scores: array<int, float>, coverage: float}
	 */
	private function rank( array $query_terms, array $prior_terms ): array {
		$stats = $this->index->stats();
		$n     = max( 1, $stats['chunks'] );
		$all   = array_values( array_unique( array_merge( $query_terms, $prior_terms ) ) );
		$df    = $this->index->document_frequencies( $all );

		$idf = array();
		foreach ( $all as $term ) {
			$d            = $df[ $term ] ?? 0;
			$idf[ $term ] = log( 1 + ( $n - $d + 0.5 ) / ( $d + 0.5 ) );
		}
		$weight = array();
		foreach ( $query_terms as $term ) {
			$weight[ $term ] = 1.0;
		}
		foreach ( $prior_terms as $term ) {
			$weight[ $term ] = 0.5;
		}

		$scores  = array();
		$matched = array();
		foreach ( $this->index->postings( array_keys( array_filter( $df ) ) ) as $row ) {
			$term = $row['term'];
			$tf   = $row['tf'];
			$norm = $tf + self::BM25_K1 * ( 1 - self::BM25_B + self::BM25_B * $row['len'] / $stats['avg_len'] );
			$part = ( $weight[ $term ] ?? 0 ) * $idf[ $term ] * ( $tf * ( self::BM25_K1 + 1 ) ) / $norm;

			$scores[ $row['chunk_id'] ]           = ( $scores[ $row['chunk_id'] ] ?? 0 ) + $part;
			$matched[ $row['chunk_id'] ][ $term ] = true;
		}
		arsort( $scores );

		$top      = array_slice( array_keys( $scores ), 0, self::TOP_K );
		$coverage = self::coverage( $query_terms, $top, $matched, $idf );
		if ( array() === $query_terms && array() !== $scores ) {
			// Only the previous turn matched: a follow-up such as "and the price?".
			$coverage = 1.0;
		} elseif ( array() !== $prior_terms ) {
			// A follow-up ("قیمتش چنده؟") leans on the previous turn's subject. Letting it through
			// costs at most one request; a wrong "not found" mid-conversation costs the visitor.
			$coverage = max( $coverage, 0.5 * self::coverage( $prior_terms, $top, $matched, $idf ) );
		}

		return array(
			'scores'   => $scores,
			'coverage' => round( $coverage, 3 ),
		);
	}

	/**
	 * Share of the terms' weight (by rarity) found in the best of the given passages.
	 * Words no page uses count against it.
	 *
	 * @param array<int, string>              $terms
	 * @param array<int, int>                 $chunk_ids
	 * @param array<int, array<string, bool>> $matched
	 * @param array<string, float>            $idf
	 */
	private static function coverage( array $terms, array $chunk_ids, array $matched, array $idf ): float {
		$total = 0.0;
		foreach ( $terms as $term ) {
			$total += $idf[ $term ] ?? 0.0;
		}
		if ( $total <= 0 ) {
			return 0.0;
		}
		$best = 0.0;
		foreach ( $chunk_ids as $chunk_id ) {
			$covered = 0.0;
			foreach ( $terms as $term ) {
				if ( isset( $matched[ $chunk_id ][ $term ] ) ) {
					$covered += $idf[ $term ] ?? 0.0;
				}
			}
			$best = max( $best, $covered / $total );
		}

		return $best;
	}

	/**
	 * Swap each word no page uses for a form some page does ("قیمتش" → "قیمت"), when there is one.
	 *
	 * @param array<int, string> $terms
	 * @return array<int, string>
	 */
	private function known_forms( array $terms ): array {
		$tries = array();
		foreach ( $terms as $term ) {
			$variants = KnowledgeText::variants( $term );
			if ( array() !== $variants ) {
				$tries[ $term ] = $variants;
			}
		}
		if ( array() === $tries ) {
			return $terms;
		}
		$lookup = array_keys( $tries );
		foreach ( $tries as $variants ) {
			$lookup = array_merge( $lookup, $variants );
		}
		$df  = $this->index->document_frequencies( array_values( array_unique( $lookup ) ) );
		$out = array();
		foreach ( $terms as $term ) {
			if ( empty( $df[ $term ] ) ) {
				foreach ( $tries[ $term ] ?? array() as $variant ) {
					if ( ! empty( $df[ $variant ] ) ) {
						$term = $variant;
						break;
					}
				}
			}
			$out[] = $term;
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Posts ranked by meaning, when semantic search is switched on and ready.
	 *
	 * @return array<int, int> Post ids, best first.
	 */
	private function semantic_posts( string $question ): array {
		try {
			$vector = new VectorSearch( $this->kb );
			if ( ! $vector->is_ready() ) {
				return array();
			}
			$out = array();
			foreach ( $vector->search( $question, self::TOP_K ) as $entry ) {
				if ( (float) ( $entry['score'] ?? 0 ) >= 0.3 ) {
					$out[] = (int) $entry['post_id'];
				}
			}
			return $out;
		} catch ( \Throwable $e ) {
			$this->logger->error( 'kb_semantic', $e->getMessage() );
			return array();
		}
	}

	/**
	 * Reciprocal-rank fusion of the word ranking (passages) and the meaning ranking (posts).
	 *
	 * @param array<int, float> $scores   chunk_id => BM25 score, best first.
	 * @param array<int, int>   $semantic Post ids, best first.
	 * @return array<int, int> Chunk ids, best first.
	 */
	private function fuse( array $scores, array $semantic ): array {
		$ranked = array_slice( array_keys( $scores ), 0, self::TOP_K * 4 );
		if ( array() === $semantic ) {
			return $ranked;
		}

		$fused = array();
		foreach ( $ranked as $rank => $chunk_id ) {
			$fused[ $chunk_id ] = 1 / ( self::RRF_K + $rank + 1 );
		}
		// A semantic hit counts for its best word-matched passage, or its first passage.
		$rows         = $this->index->chunks_by_id( $ranked );
		$best_of_post = array();
		foreach ( $ranked as $chunk_id ) {
			$post_id = (int) ( $rows[ $chunk_id ]['post_id'] ?? 0 );
			if ( $post_id > 0 && ! isset( $best_of_post[ $post_id ] ) ) {
				$best_of_post[ $post_id ] = $chunk_id;
			}
		}
		$first = $this->index->first_chunk_ids( array_diff( $semantic, array_keys( $best_of_post ) ) );
		foreach ( $semantic as $rank => $post_id ) {
			$chunk_id = $best_of_post[ $post_id ] ?? ( $first[ $post_id ] ?? 0 );
			if ( $chunk_id > 0 ) {
				$fused[ $chunk_id ] = ( $fused[ $chunk_id ] ?? 0 ) + 1 / ( self::RRF_K + $rank + 1 );
			}
		}
		arsort( $fused );

		return array_keys( $fused );
	}

	/**
	 * Index changes follow post saves, but a post protected or unpublished a moment ago
	 * must not be quoted before its index row is gone.
	 */
	private function still_public( int $post_id ): bool {
		$post = get_post( $post_id );

		return $post instanceof \WP_Post && KnowledgeExtractor::is_indexable( $post );
	}

	/**
	 * @param array<int, int> $post_ids
	 * @return array<int, string>
	 */
	private function titles( array $post_ids ): array {
		$out = array();
		foreach ( array_unique( $post_ids ) as $post_id ) {
			$out[ $post_id ] = html_entity_decode( (string) get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
		}

		return $out;
	}
}
