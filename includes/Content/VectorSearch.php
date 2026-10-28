<?php
declare(strict_types=1);

namespace AISAAutoPilot\Content;

use AISAAutoPilot\Logging\Logger;
use AISAAutoPilot\Providers\ProviderManager;

/**
 * Semantic (embeddings) retrieval over the Knowledge Base.
 *
 * The question is embedded once, then compared to every stored entry vector
 * with cosine similarity in PHP, a page at a time. This is language-agnostic — a Persian question
 * finds the right English page and vice-versa — and ranks by meaning rather
 * than keyword overlap. Falls back cleanly to keyword/recency retrieval upstream.
 */
final class VectorSearch {

	/** Vectors read per query; each is a few kilobytes of JSON. */
	private const PAGE_SIZE = 200;

	private KnowledgeBase $kb;
	private ProviderManager $manager;
	private Logger $logger;

	public function __construct( ?KnowledgeBase $kb = null, ?ProviderManager $manager = null, ?Logger $logger = null ) {
		$this->kb      = $kb ?? new KnowledgeBase();
		$this->manager = $manager ?? new ProviderManager();
		$this->logger  = $logger ?? new Logger();
	}

	/**
	 * Whether semantic search is usable right now (enabled + vectors present).
	 */
	public function is_ready(): bool {
		if ( ! $this->manager->embeddings_enabled() ) {
			return false;
		}

		return $this->kb->has_embeddings( $this->manager->embedding_model() );
	}

	/**
	 * Rank KB entries by semantic similarity to the query.
	 *
	 * @return array<int, array<string, mixed>> Entries (with a 'score'), best first. Empty on failure.
	 */
	public function search( string $query, int $limit = 8 ): array {
		$query = trim( $query );
		if ( '' === $query ) {
			return array();
		}

		$model = $this->manager->embedding_model();

		try {
			$provider = $this->manager->make();
			$embedded = $provider->generate_embedding( $query );
			$q        = $embedded['embedding'];
		} catch ( \Throwable $e ) {
			$this->logger->error( 'vector_search', 'Query embedding failed: ' . $e->getMessage() );
			return array();
		}

		$q_norm = $this->norm( $q );
		if ( 0.0 === $q_norm ) {
			return array();
		}

		// Page through the vectors and keep only the best few, so memory stays the same
		// for a hundred posts or fifty thousand.
		$limit = max( 1, $limit );
		$best  = array();
		$after = 0;
		do {
			$page = $this->kb->vector_page( $model, $after, self::PAGE_SIZE );
			foreach ( $page as $entry ) {
				$after = max( $after, (int) $entry['id'] );
				$vec   = $entry['vector'];
				if ( count( $vec ) !== count( $q ) ) {
					continue; // Dimension mismatch (model changed) — skip.
				}
				unset( $entry['vector'], $entry['id'] );
				$entry['score'] = $this->cosine( $q, $vec, $q_norm );
				$best[]         = $entry;
				if ( count( $best ) > $limit ) {
					usort( $best, static fn( $a, $b ) => $b['score'] <=> $a['score'] );
					array_pop( $best );
				}
			}
		} while ( count( $page ) === self::PAGE_SIZE );

		usort( $best, static fn( $a, $b ) => $b['score'] <=> $a['score'] );

		return $best;
	}

	/**
	 * Cosine similarity given the pre-computed norm of $a.
	 *
	 * @param array<int, float> $a
	 * @param array<int, float> $b
	 */
	private function cosine( array $a, array $b, float $a_norm ): float {
		$dot    = 0.0;
		$b_norm = 0.0;
		$n      = count( $a );

		for ( $i = 0; $i < $n; $i++ ) {
			$av      = (float) $a[ $i ];
			$bv      = (float) $b[ $i ];
			$dot    += $av * $bv;
			$b_norm += $bv * $bv;
		}

		$b_norm = sqrt( $b_norm );
		if ( 0.0 === $b_norm || 0.0 === $a_norm ) {
			return 0.0;
		}

		return $dot / ( $a_norm * $b_norm );
	}

	/**
	 * @param array<int, float> $v
	 */
	private function norm( array $v ): float {
		$sum = 0.0;
		foreach ( $v as $x ) {
			$sum += (float) $x * (float) $x;
		}

		return sqrt( $sum );
	}
}
