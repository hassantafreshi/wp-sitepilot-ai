<?php
declare(strict_types=1);

namespace SitePilot\Admin;

use SitePilot\Content\KnowledgeBase;
use SitePilot\Content\ContentIndexer;
use SitePilot\Providers\ProviderManager;
use SitePilot\Usage\UsageTracker;

/**
 * Admin page: Knowledge Base management.
 * Shows KB stats, a "Start Indexing" button with live progress,
 * and a searchable/filterable table of indexed content.
 */
final class KnowledgeBasePage {

	public const SLUG = 'sitepilot-ai-kb';

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sitepilot-ai' ) );
		}

		$kb          = new KnowledgeBase();
		$indexer     = new ContentIndexer();
		$usage       = new UsageTracker();
		$manager     = new ProviderManager();
		$configured  = $manager->is_configured();
		$stats       = $kb->table_exists() ? $kb->get_stats() : array(
			'total'         => 0,
			'indexed'       => 0,
			'pending'       => 0,
			'post_types'    => array(),
			'content_types' => array(),
			'last_indexed'  => null,
		);
		$total_posts = $indexer->get_total_posts();
		$warning     = $usage->get_threshold_warning();

		$semantic_on    = $manager->embeddings_enabled();
		$embedded_count = ( $semantic_on && $kb->table_exists() ) ? $kb->count_with_embeddings( $manager->embedding_model() ) : 0;

		$type_options = $indexer->get_indexable_types_with_counts();
		$categories   = get_categories(
			array(
				'hide_empty' => false,
				'number'     => 200,
			)
		);
		$ct_labels    = self::content_type_labels();
		?>
		<div class="wrap sitepilot-wrap sitepilot-screen sitepilot-screen--kb sitepilot-kb-wrap">
			<nav class="sitepilot-screen__breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'sitepilot-ai' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_HOME ) ); ?>"><?php esc_html_e( 'Home', 'sitepilot-ai' ); ?></a>
				<span aria-hidden="true">/</span>
				<span><?php esc_html_e( 'Knowledge Base', 'sitepilot-ai' ); ?></span>
			</nav>
			<header class="sitepilot-screen__header">
				<div>
					<p class="sitepilot-eyebrow"><?php esc_html_e( 'Site knowledge', 'sitepilot-ai' ); ?></p>
					<h1 class="sitepilot-title"><span class="dashicons dashicons-database" aria-hidden="true"></span><?php esc_html_e( 'Knowledge Base', 'sitepilot-ai' ); ?> <span class="sp-ai-badge">AI</span></h1>
					<p class="sitepilot-subtitle"><?php esc_html_e( 'Index the content SitePilot may use for accurate, source-aware answers. New and changed content can be indexed automatically.', 'sitepilot-ai' ); ?></p>
				</div>
				<div class="sitepilot-screen__status">
					<span class="sitepilot-screen__badge <?php echo $configured ? 'sitepilot-screen__badge--ready' : 'sitepilot-screen__badge--attention'; ?>"><?php echo esc_html( $configured ? __( 'Provider ready', 'sitepilot-ai' ) : __( 'Provider needs setup', 'sitepilot-ai' ) ); ?></span>
					<span class="sitepilot-screen__badge <?php echo $semantic_on ? 'sitepilot-screen__badge--info' : ''; ?>"><?php echo esc_html( $semantic_on ? __( 'Semantic search on', 'sitepilot-ai' ) : __( 'Keyword search', 'sitepilot-ai' ) ); ?></span>
				</div>
			</header>
			<div class="sp-sr-only">
				<span class="dashicons dashicons-database"></span>
				<?php esc_html_e( 'Knowledge Base', 'sitepilot-ai' ); ?>
				<span class="sp-ai-badge">AI</span>
			</div>
			<p class="sitepilot-subtitle sp-sr-only">
				<?php esc_html_e( 'SitePilot reads and understands your site content so it can answer questions accurately. Index your content once — new content is indexed automatically.', 'sitepilot-ai' ); ?>
			</p>
			<?php if ( ! $configured ) : ?>
				<div class="sitepilot-banner sitepilot-banner--warn">
					<span class="dashicons dashicons-warning"></span>
					<span>
						<?php esc_html_e( 'No AI provider is connected.', 'sitepilot-ai' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS ) ); ?>">
							<?php esc_html_e( 'Add your API key in Settings', 'sitepilot-ai' ); ?>
						</a>.
					</span>
				</div>
			<?php endif; ?>

			<?php if ( $warning ) : ?>
				<div class="sitepilot-banner sitepilot-banner--<?php echo esc_attr( $warning['level'] ); ?>">
					<span class="dashicons dashicons-info"></span>
					<span><?php echo esc_html( $warning['message'] ); ?></span>
				</div>
			<?php endif; ?>

			<!-- Stats Cards -->
			<div class="sitepilot-kb-stats">
				<div class="sitepilot-kb-stat">
					<div class="sitepilot-kb-stat__icon">📚</div>
					<span class="sitepilot-kb-stat__number"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></span>
					<span class="sitepilot-kb-stat__label"><?php esc_html_e( 'Indexed Items', 'sitepilot-ai' ); ?></span>
				</div>
				<div class="sitepilot-kb-stat">
					<div class="sitepilot-kb-stat__icon">📄</div>
					<span class="sitepilot-kb-stat__number"><?php echo esc_html( number_format_i18n( $total_posts ) ); ?></span>
					<span class="sitepilot-kb-stat__label"><?php esc_html_e( 'Total Posts & Pages', 'sitepilot-ai' ); ?></span>
				</div>
				<div class="sitepilot-kb-stat">
					<div class="sitepilot-kb-stat__icon">🕐</div>
					<span class="sitepilot-kb-stat__number" style="font-size: 18px;">
						<?php echo $stats['last_indexed'] ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $stats['last_indexed'] ) ) ) : '—'; ?>
					</span>
					<span class="sitepilot-kb-stat__label"><?php esc_html_e( 'Last Updated', 'sitepilot-ai' ); ?></span>
				</div>
			</div>

			<!-- Indexing Control -->
			<div class="sitepilot-card sitepilot-kb-indexer">
				<h2>
					<span class="dashicons dashicons-update" style="color: var(--sp-primary, #4f46e5);"></span>
					<?php esc_html_e( 'Content Indexing', 'sitepilot-ai' ); ?>
				</h2>
				<p class="description" style="max-width: 640px; line-height: 1.6;">
					<?php esc_html_e( 'Click the button below to scan and index all your published posts and pages. SitePilot AI will summarize each one into structured knowledge. You only need to do this once — new content is indexed automatically when you publish or edit.', 'sitepilot-ai' ); ?>
				</p>

				<?php if ( $semantic_on ) : ?>
					<div class="sitepilot-kb-semantic">
						<span class="dashicons dashicons-search"></span>
						<span>
							<strong><?php esc_html_e( 'Semantic search is ON.', 'sitepilot-ai' ); ?></strong>
							<?php
							printf(
								/* translators: %1$d: embedded items, %2$d: total indexed items. */
								esc_html__( '%1$d of %2$d indexed items have an embedding. Run indexing to build any missing vectors.', 'sitepilot-ai' ),
								(int) $embedded_count,
								(int) $stats['total']
							);
							?>
						</span>
					</div>
				<?php endif; ?>

				<div class="sitepilot-kb-indexer__actions">
					<button type="button" id="sp-kb-start-index" class="button button-primary button-hero"
						<?php disabled( ! $configured ); ?>>
						<span class="dashicons dashicons-lightbulb"></span>
						<?php esc_html_e( 'Start Indexing', 'sitepilot-ai' ); ?>
					</button>
					<button type="button" id="sp-kb-reindex" class="button button-secondary"
						<?php disabled( ! $configured ); ?>>
						<span class="dashicons dashicons-image-rotate"></span>
						<?php esc_html_e( 'Full Re-index', 'sitepilot-ai' ); ?>
					</button>
				</div>

				<!-- Progress Area -->
				<div id="sp-kb-progress" class="sitepilot-kb-progress" hidden>
					<div class="sitepilot-kb-progress__bar-wrap">
						<div id="sp-kb-progress-bar" class="sitepilot-kb-progress__bar" style="width: 0%"></div>
					</div>
					<div class="sitepilot-kb-progress__info">
						<span id="sp-kb-progress-text"><?php esc_html_e( 'Preparing…', 'sitepilot-ai' ); ?></span>
						<span id="sp-kb-progress-percent">0%</span>
					</div>
					<div id="sp-kb-progress-log" class="sitepilot-kb-progress__log"></div>
				</div>
			</div>

			<!-- Content Gaps: questions visitors asked that the KB couldn't answer -->
			<div id="sp-kb-gaps" class="sitepilot-card sitepilot-kb-gaps" hidden>
				<div class="sitepilot-kb-gaps__header">
					<h2>
						<span class="dashicons dashicons-lightbulb" style="color: #d97706;"></span>
						<?php esc_html_e( 'Questions Without Answers', 'sitepilot-ai' ); ?>
						<span id="sp-kb-gaps-count" class="sp-kb-gaps-count">0</span>
					</h2>
					<button type="button" id="sp-kb-gaps-clear" class="button button-small">
						<?php esc_html_e( 'Clear all', 'sitepilot-ai' ); ?>
					</button>
				</div>
				<p class="description">
					<?php esc_html_e( 'These are questions visitors asked that your content does not answer yet. Write or index a page covering them to improve future answers.', 'sitepilot-ai' ); ?>
				</p>
				<div id="sp-kb-gaps-list" class="sitepilot-kb-gaps__list"></div>
			</div>

			<!-- KB Table -->
			<div class="sitepilot-card sitepilot-kb-table-wrap">
				<div class="sitepilot-kb-table__header">
					<h2>
						<span class="dashicons dashicons-list-view" style="color: var(--sp-primary, #4f46e5);"></span>
						<?php esc_html_e( 'Indexed Content', 'sitepilot-ai' ); ?>
					</h2>
					<div class="sitepilot-kb-table__filters">
						<input type="search" id="sp-kb-search" class="sitepilot-kb-search"
							placeholder="<?php esc_attr_e( 'Search indexed content…', 'sitepilot-ai' ); ?>" />
						<select id="sp-kb-type-filter" class="sitepilot-kb-type-filter">
							<option value=""><?php esc_html_e( 'All Types', 'sitepilot-ai' ); ?></option>
							<?php foreach ( $stats['post_types'] as $type => $count ) : ?>
								<option value="<?php echo esc_attr( $type ); ?>">
									<?php echo esc_html( $type . ' (' . $count . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( ! empty( $stats['content_types'] ) ) : ?>
							<select id="sp-kb-ctype-filter" class="sitepilot-kb-type-filter">
								<option value=""><?php esc_html_e( 'All Categories', 'sitepilot-ai' ); ?></option>
								<?php foreach ( $stats['content_types'] as $ctype => $count ) : ?>
									<option value="<?php echo esc_attr( $ctype ); ?>">
										<?php echo esc_html( ( $ct_labels[ $ctype ] ?? ucfirst( $ctype ) ) . ' (' . $count . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
					</div>
				</div>

				<table class="wp-list-table widefat fixed striped sitepilot-kb-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Title', 'sitepilot-ai' ); ?></th>
							<th><?php esc_html_e( 'Type', 'sitepilot-ai' ); ?></th>
							<th><?php esc_html_e( 'Summary', 'sitepilot-ai' ); ?></th>
							<th><?php esc_html_e( 'Keywords', 'sitepilot-ai' ); ?></th>
							<th><?php esc_html_e( 'Updated', 'sitepilot-ai' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'sitepilot-ai' ); ?></th>
						</tr>
					</thead>
					<tbody id="sp-kb-table-body">
						<tr>
							<td colspan="6">
								<div class="sp-kb-empty">
									<div class="sp-kb-empty__icon">🔍</div>
									<div class="sp-kb-empty__text"><?php esc_html_e( 'Loading…', 'sitepilot-ai' ); ?></div>
								</div>
							</td>
						</tr>
					</tbody>
				</table>

				<div id="sp-kb-pagination" class="sitepilot-kb-pagination"></div>
			</div>

			<!-- Pre-index picker modal -->
			<div id="sp-kb-modal" class="sitepilot-kb-modal" hidden>
				<div class="sitepilot-kb-modal__backdrop" data-close="1"></div>
				<div class="sitepilot-kb-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sp-kb-modal-title">
					<div class="sitepilot-kb-modal__header">
						<h2 id="sp-kb-modal-title">
							<span class="dashicons dashicons-filter"></span>
							<?php esc_html_e( 'What should SitePilot index?', 'sitepilot-ai' ); ?>
						</h2>
						<button type="button" class="sitepilot-kb-modal__close" data-close="1" aria-label="<?php esc_attr_e( 'Close', 'sitepilot-ai' ); ?>">&times;</button>
					</div>
					<div class="sitepilot-kb-modal__body">
						<p class="description">
							<?php esc_html_e( 'Choose which content to read and learn from. You can re-run this any time.', 'sitepilot-ai' ); ?>
						</p>

						<div class="sitepilot-kb-picker">
							<h3><?php esc_html_e( 'Content types', 'sitepilot-ai' ); ?></h3>
							<div class="sitepilot-kb-picker__grid">
								<?php foreach ( $type_options as $t ) : ?>
									<label class="sitepilot-kb-check">
										<input type="checkbox" class="sp-kb-type-opt" value="<?php echo esc_attr( $t['slug'] ); ?>" checked />
										<span class="sitepilot-kb-check__label"><?php echo esc_html( $t['label'] ); ?></span>
										<span class="sitepilot-kb-check__count"><?php echo esc_html( (string) $t['count'] ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>

						<?php if ( ! empty( $categories ) ) : ?>
							<div class="sitepilot-kb-picker">
								<h3>
									<?php esc_html_e( 'Post categories', 'sitepilot-ai' ); ?>
									<span class="sitepilot-kb-picker__hint"><?php esc_html_e( '(applies to Posts)', 'sitepilot-ai' ); ?></span>
								</h3>
								<label class="sitepilot-kb-check sitepilot-kb-check--master">
									<input type="checkbox" id="sp-kb-cat-all" checked />
									<span class="sitepilot-kb-check__label"><?php esc_html_e( 'All categories', 'sitepilot-ai' ); ?></span>
								</label>
								<div class="sitepilot-kb-picker__grid sitepilot-kb-picker__grid--cats" id="sp-kb-cat-list" hidden>
									<?php foreach ( $categories as $cat ) : ?>
										<label class="sitepilot-kb-check">
											<input type="checkbox" class="sp-kb-cat-opt" value="<?php echo esc_attr( (string) $cat->term_id ); ?>" />
											<span class="sitepilot-kb-check__label"><?php echo esc_html( $cat->name ); ?></span>
											<span class="sitepilot-kb-check__count"><?php echo esc_html( (string) $cat->count ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endif; ?>
					</div>
					<div class="sitepilot-kb-modal__footer">
						<button type="button" class="button button-secondary" data-close="1"><?php esc_html_e( 'Cancel', 'sitepilot-ai' ); ?></button>
						<button type="button" id="sp-kb-modal-start" class="button button-primary">
							<span class="dashicons dashicons-lightbulb"></span>
							<?php esc_html_e( 'Start Indexing', 'sitepilot-ai' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<script type="application/json" id="sp-kb-ctype-labels"><?php echo wp_json_encode( $ct_labels ); ?></script>
		<?php
	}

	/**
	 * Friendly, translatable labels for the automatic content-type slugs.
	 *
	 * @return array<string, string>
	 */
	private static function content_type_labels(): array {
		return array(
			'tutorial' => __( 'Tutorial / Guide', 'sitepilot-ai' ),
			'product'  => __( 'Product', 'sitepilot-ai' ),
			'service'  => __( 'Service', 'sitepilot-ai' ),
			'article'  => __( 'Article', 'sitepilot-ai' ),
			'page'     => __( 'Page', 'sitepilot-ai' ),
			'faq'      => __( 'FAQ', 'sitepilot-ai' ),
			'news'     => __( 'News', 'sitepilot-ai' ),
			'other'    => __( 'Other', 'sitepilot-ai' ),
		);
	}
}
