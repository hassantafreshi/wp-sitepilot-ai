<?php
/**
 * Knowledge-base retrieval benchmark on a real WordPress install.
 *
 * Usage (AISAAUTOPILOT_WP_ROOT points at the site):
 *   php tests/kb_benchmark.php setup small|medium     create the fixture site (fact pages, products, filler blog)
 *   php tests/kb_benchmark.php index                  index it with the plugin's current indexer
 *   php tests/kb_benchmark.php measure <label> <out.json>
 *       For every question, check whether its fact reaches the context the model is given,
 *       and whether unanswerable questions stop before the model is called. No AI request.
 *   php tests/kb_benchmark.php answer <label> <out.json> [n]   ask n questions end to end (uses the AI provider)
 *   php tests/kb_benchmark.php teardown                remove the fixture posts and empty the knowledge base
 *
 * The "fact reached" check compares normalised text (digits, Arabic/Persian letter forms,
 * spacing), the same normalisation for every method, so only retrieval is measured.
 */
declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\xampp\htdocs\wp';
require $wp_root . '/wp-load.php';
require_once __DIR__ . '/kb_benchmark_corpus.php';
require_once ABSPATH . 'wp-admin/includes/post.php';

use AISAAutoPilot\Content\ContentIndexer;
use AISAAutoPilot\Content\KnowledgeBase;

const AISA_BENCH_IDS      = 'aisaautopilot_kb_bench_ids';
const AISA_BENCH_SETTINGS = 'aisaautopilot_kb_bench_settings_backup';

$mode = $argv[1] ?? '';

/** Text normalisation used only to decide whether a fact string is present. */
function aisa_bench_norm( string $text ): string {
	$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
	$text = strtr(
		$text,
		array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', '٫' => '.', "\u{200C}" => ' ', '‌' => ' ',
		)
	);
	$text = mb_strtolower( $text );
	return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
}

function aisa_bench_create( array $doc ): int {
	if ( 'product' === $doc['type'] && function_exists( 'wc_get_product' ) ) {
		$product = new WC_Product_Simple();
		$product->set_name( $doc['title'] );
		$product->set_description( $doc['content'] );
		$product->set_regular_price( $doc['price'] );
		$product->set_stock_status( $doc['stock'] );
		$product->set_status( 'publish' );
		$attributes = array();
		foreach ( (array) ( $doc['attributes'] ?? array() ) as $name => $value ) {
			$attribute = new WC_Product_Attribute();
			$attribute->set_name( $name );
			$attribute->set_options( array( $value ) );
			$attribute->set_visible( true );
			$attributes[] = $attribute;
		}
		$product->set_attributes( $attributes );
		$id = (int) $product->save();
	} else {
		$id = (int) wp_insert_post(
			array(
				'post_type'     => $doc['type'],
				'post_status'   => 'publish',
				'post_title'    => $doc['title'],
				'post_content'  => $doc['content'],
				'post_password' => (string) ( $doc['password'] ?? '' ),
			)
		);
	}
	update_post_meta( $id, '_aisaautopilot_kb_bench', $doc['key'] );
	return $id;
}

function aisa_bench_ids(): array {
	return (array) get_option( AISA_BENCH_IDS, array() );
}

/** @return array<int, array<string, mixed>> */
function aisa_bench_all_questions(): array {
	$corpus = aisaautopilot_kb_benchmark_corpus();
	$out    = array();
	foreach ( $corpus['questions'] as $q ) {
		$q['answerable'] = true;
		$out[]           = $q;
	}
	foreach ( $corpus['unanswerable'] as $q ) {
		$q['answerable'] = false;
		$q['doc']        = '';
		$q['fact']       = '';
		$out[]           = $q;
	}
	return $out;
}

/**
 * The context the plugin gives the model for a question, and whether it would stop
 * before calling the model. Uses the new retrieval when it exists, else the old path.
 *
 * @return array{context:string, stop:bool, method:string}
 */
function aisa_bench_context( string $question ): array {
	if ( class_exists( '\AISAAutoPilot\Content\KnowledgeSearch' ) ) {
		$result = ( new \AISAAutoPilot\Content\KnowledgeSearch() )->retrieve( $question );
		return array(
			'context' => (string) $result['context'],
			'stop'    => ! $result['found'],
			'method'  => 'chunks',
		);
	}
	// Old engine with semantic search off (the default): recency, 40 entries, 12,000 bytes.
	$built = ( new KnowledgeBase() )->get_context_with_refs( 40, 12000 );
	return array(
		'context' => (string) $built['context'],
		'stop'    => '' === trim( (string) $built['context'] ),
		'method'  => 'recency',
	);
}

switch ( $mode ) {
	case 'setup':
		$size   = $argv[2] ?? 'small';
		$corpus = aisaautopilot_kb_benchmark_corpus();
		$ids    = aisa_bench_ids();
		if ( empty( $ids ) ) {
			// Settings the benchmark changes, restored by teardown.
			$settings = (array) get_option( 'aisaautopilot_settings', array() );
			update_option( AISA_BENCH_SETTINGS, array( 'kb_post_types' => $settings['kb_post_types'] ?? null ), false );
			$settings['kb_post_types'] = array_values( array_unique( array_merge( (array) ( $settings['kb_post_types'] ?? array() ), array( 'product' ) ) ) );
			update_option( 'aisaautopilot_settings', $settings );
			// Site launch: the fact pages come first, then the blog grows.
			foreach ( $corpus['docs'] as $doc ) {
				$ids[ $doc['key'] ] = aisa_bench_create( $doc );
			}
			foreach ( aisaautopilot_kb_benchmark_filler( 10, 7 ) as $doc ) {
				$ids[ $doc['key'] ] = aisa_bench_create( $doc );
			}
		}
		if ( 'medium' === $size && ! isset( $ids['filler2_0'] ) ) {
			foreach ( aisaautopilot_kb_benchmark_filler( 120, 11 ) as $doc ) {
				$doc['key']         = 'filler2_' . substr( $doc['key'], 7 );
				$ids[ $doc['key'] ] = aisa_bench_create( $doc );
			}
		}
		update_option( AISA_BENCH_IDS, $ids, false );
		echo wp_json_encode( array( 'docs' => count( $ids ) ) ) . "\n";
		break;

	case 'index':
		$indexer = new ContentIndexer();
		$offset  = 0;
		$summary = array( 'indexed' => 0, 'skipped' => 0, 'error' => 0 );
		$start   = microtime( true );
		for ( $guard = 0; $guard < 400; $guard++ ) {
			$batch = $indexer->process_batch( $offset, array() );
			foreach ( (array) $batch['items'] as $item ) {
				$summary[ $item['status'] ] = ( $summary[ $item['status'] ] ?? 0 ) + 1;
				if ( 'error' === $item['status'] ) {
					fwrite( STDERR, $item['message'] . "\n" );
				}
			}
			if ( ! empty( $batch['error'] ) || ! empty( $batch['paused'] ) ) {
				$summary['stopped'] = (string) ( $batch['error'] ?? $batch['message'] ?? '' );
				break;
			}
			$offset = (int) $batch['offset'];
			if ( ! empty( $batch['done'] ) ) {
				break;
			}
		}
		$summary['seconds'] = round( microtime( true ) - $start, 1 );
		echo wp_json_encode( $summary ) . "\n";
		break;

	case 'measure':
		$label = $argv[2] ?? 'run';
		$out   = $argv[3] ?? '';
		$rows  = array();
		foreach ( aisa_bench_all_questions() as $q ) {
			$started = microtime( true );
			$ctx     = aisa_bench_context( $q['q'] );
			$norm    = aisa_bench_norm( $ctx['context'] );
			$rows[]  = array(
				'q'          => $q['q'],
				'lang'       => $q['lang'],
				'answerable' => $q['answerable'],
				'doc'        => $q['doc'],
				'fact'       => $q['fact'],
				'reached'    => $q['answerable'] ? ( '' !== $q['fact'] && false !== mb_strpos( $norm, aisa_bench_norm( $q['fact'] ) ) ) : null,
				'stop'       => $ctx['stop'],
				'leak'       => false !== mb_strpos( $norm, 'beans50' ),
				'context_kb' => round( strlen( $ctx['context'] ) / 1024, 1 ),
				'ms'         => (int) round( ( microtime( true ) - $started ) * 1000 ),
				'method'     => $ctx['method'],
			);
		}
		$answerable = array_values( array_filter( $rows, static fn( $r ) => $r['answerable'] ) );
		$rate       = static function ( array $set, callable $test ): string {
			$hit = count( array_filter( $set, $test ) );
			return $hit . '/' . count( $set );
		};
		$kb      = new KnowledgeBase();
		$summary = array(
			'label'                  => $label,
			'kb_entries'             => (int) ( $kb->get_stats()['total'] ?? 0 ),
			'fact_reached_all'       => $rate( $answerable, static fn( $r ) => $r['reached'] ),
			'fact_reached_en'        => $rate( array_filter( $answerable, static fn( $r ) => 'en' === $r['lang'] ), static fn( $r ) => $r['reached'] ),
			'fact_reached_fa'        => $rate( array_filter( $answerable, static fn( $r ) => 'fa' === $r['lang'] ), static fn( $r ) => $r['reached'] ),
			'unanswerable_stopped'   => $rate( array_filter( $rows, static fn( $r ) => ! $r['answerable'] ), static fn( $r ) => $r['stop'] ),
			'answerable_stopped'     => $rate( $answerable, static fn( $r ) => $r['stop'] ),
			'password_text_reachable' => count( array_filter( $rows, static fn( $r ) => $r['leak'] ) ) > 0,
			'avg_context_kb'         => round( array_sum( array_column( $rows, 'context_kb' ) ) / max( 1, count( $rows ) ), 1 ),
			'avg_ms'                 => (int) round( array_sum( array_column( $rows, 'ms' ) ) / max( 1, count( $rows ) ) ),
		);
		$report = array(
			'summary' => $summary,
			'rows'    => $rows,
		);
		if ( '' !== $out ) {
			file_put_contents( $out, wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		}
		echo wp_json_encode( $summary, JSON_UNESCAPED_UNICODE ) . "\n";
		break;

	case 'answer':
		$label = $argv[2] ?? 'run';
		$out   = $argv[3] ?? '';
		$limit = (int) ( $argv[4] ?? 6 );
		$pick  = array();
		foreach ( aisa_bench_all_questions() as $q ) {
			$pick[] = $q;
		}
		// A spread of languages and outcomes, the same questions for every method.
		$keys = array( 'What time do you open on Saturday?', 'What is the price of the Ethiopia Yirgacheffe?', 'How much coffee should I use for a French press?', 'هزینه ارسال چقدر است؟', 'قيمت قهوه كلمبيا چقدر است؟', 'Do you sell pizza?', 'Is there a staff discount code?', 'میتونم اشتراکم رو موقتا متوقف کنم؟' );
		$pick = array_values( array_filter( $pick, static fn( $q ) => in_array( $q['q'], $keys, true ) ) );
		$pick = array_slice( $pick, 0, $limit );
		$rows = array();
		foreach ( $pick as $q ) {
			$result = ( new \AISAAutoPilot\Chat\ChatEngine( new KnowledgeBase() ) )->ask( $q['q'] );
			$rows[] = array(
				'q'       => $q['q'],
				'fact'    => $q['fact'],
				'status'  => $result['status'],
				'answer'  => $result['answer'],
				'correct' => $q['answerable'] ? false !== mb_strpos( aisa_bench_norm( (string) $result['answer'] ), aisa_bench_norm( $q['fact'] ) ) : ( 'not_found' === $result['status'] && false === mb_strpos( aisa_bench_norm( (string) $result['answer'] ), 'beans50' ) ),
				'sources' => array_column( (array) $result['sources'], 'title' ),
			);
		}
		if ( '' !== $out ) {
			file_put_contents( $out, wp_json_encode( array( 'label' => $label, 'rows' => $rows ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		}
		echo wp_json_encode( array( 'label' => $label, 'correct' => count( array_filter( $rows, static fn( $r ) => $r['correct'] ) ) . '/' . count( $rows ) ) ) . "\n";
		break;

	case 'teardown':
		foreach ( aisa_bench_ids() as $id ) {
			wp_delete_post( (int) $id, true );
		}
		delete_option( AISA_BENCH_IDS );
		$backup = get_option( AISA_BENCH_SETTINGS, null );
		if ( is_array( $backup ) ) {
			$settings = (array) get_option( 'aisaautopilot_settings', array() );
			if ( null === $backup['kb_post_types'] ) {
				unset( $settings['kb_post_types'] );
			} else {
				$settings['kb_post_types'] = $backup['kb_post_types'];
			}
			update_option( 'aisaautopilot_settings', $settings );
			delete_option( AISA_BENCH_SETTINGS );
		}
		( new KnowledgeBase() )->truncate();
		if ( class_exists( '\AISAAutoPilot\Content\KnowledgeIndex' ) ) {
			( new \AISAAutoPilot\Content\KnowledgeIndex() )->truncate();
		}
		echo "Benchmark fixture removed.\n";
		break;

	default:
		fwrite( STDERR, "Usage: php tests/kb_benchmark.php setup|index|measure|answer|teardown\n" );
		exit( 64 );
}
