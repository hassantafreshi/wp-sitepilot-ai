<?php
declare(strict_types=1);

namespace AISAAutoPilot\Content;

use AISAAutoPilot\Logging\Logger;
use AISAAutoPilot\Providers\ProviderManager;

/**
 * Semantic (embeddings) retrieval over the Knowledge Base.
 *
 * The question is embedded once, then compared to every stored entry vector
 * with cosine similarity in PHP. This is language-agnostic — a Persian question
 * finds the right English page and vice-versa — and ranks by meaning rather
 * than keyword overlap. Falls back cleanly to keyword/recency retrieval upstream.
 */
final class VectorSearch {

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

		return $this->kb->count_with_embeddings( $this->manager->embedding_model() ) > 0;
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

		$entries = $this->kb->get_vectors( $model );
		if ( empty( $entries ) ) {
			return array();
		}

		$scored = array();
		foreach ( $entries as $entry ) {
			$vec = $entry['vector'];
			if ( count( $vec ) !== count( $q ) ) {
				continue; // Dimension mismatch (model changed) — skip.
			}
			$score = $this->cosine( $q, $vec, $q_norm );
			unset( $entry['vector'] );
			$entry['score'] = $score;
			$scored[]       = $entry;
		}

		usort(
			$scored,
			static function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		return array_slice( $scored, 0, max( 1, $limit ) );
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
