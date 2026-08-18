<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Content\ContentIndexer;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Providers\ProviderManager;

/**
 * Knowledge Base — index the site content AISAAutoPilot may quote from.
 */
final class KnowledgeBasePage {

	public const SLUG = 'aisaautopilot-kb';

	/** Grid template for the indexed-content table. */
	private const COLS = '2.2fr 1fr 1fr .9fr 40px';

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$kb         = new KnowledgeBase();
		$indexer    = new ContentIndexer();
		$manager    = new ProviderManager();
		$configured = $manager->is_configured();

		$stats = $kb->table_exists()
			? $kb->get_stats()
			: array(
				'total'         => 0,
				'indexed'       => 0,
				'pending'       => 0,
				'post_types'    => array(),
				'content_types' => array(),
				'last_indexed'  => null,
			);

		$total_posts    = $indexer->get_total_posts();
		$semantic_on    = $manager->embeddings_enabled();
		$embedded_count = ( $semantic_on && $kb->table_exists() ) ? $kb->count_with_embeddings( $manager->embedding_model() ) : 0;
		$type_options   = $indexer->get_indexable_types_with_counts();
		$categories     = get_categories(
			array(
				'hide_empty' => false,
				'number'     => 200,
			)
		);
		$ct_labels      = self::content_type_labels();
		$settings_url   = admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETUP );

		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-sp-kb>
			<section class="sp-screen">
				<?php Ui::title( __( 'Knowledge Base', 'ai-site-assistant-autopilot' ), 'database', true ); ?>
				<p class="sp-lead" style="margin-bottom:16px !important"><?php esc_html_e( 'AISAAutoPilot reads and understands your site content so it can answer questions accurately. Index your content once — new content is indexed automatically.', 'ai-site-assistant-autopilot' ); ?></p>

				<?php if ( ! $configured ) : ?>
					<?php
					Ui::alert(
						'warn',
						'warning',
						esc_html__( 'No AI provider is connected.', 'ai-site-assistant-autopilot' ) . ' <a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Add your API key in Settings', 'ai-site-assistant-autopilot' ) . '</a>.'
					);
					?>
				<?php endif; ?>

				<div class="sp-tiles sp-tiles--200" style="margin:24px 0">
					<?php
					Ui::metric( 'book', number_format_i18n( (int) $stats['total'] ), __( 'Indexed Items', 'ai-site-assistant-autopilot' ) );
					Ui::metric( 'doc', number_format_i18n( $total_posts ), __( 'Total Posts & Pages', 'ai-site-assistant-autopilot' ) );
					Ui::metric(
						'clock',
						$stats['last_indexed'] ? date_i18n( (string) get_option( 'date_format' ), (int) strtotime( (string) $stats['last_indexed'] ) ) : '—',
						__( 'Last Updated', 'ai-site-assistant-autopilot' ),
						true
					);
					?>
				</div>

				<div class="sp-card sp-card--24" style="margin-bottom:16px">
					<h2 class="sp-h2 sp-h2--md" style="display:flex;align-items:center;gap:9px;margin-bottom:8px !important">
						<?php echo Ui::icon( 'refresh', 18, '#4f46e5', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
						<span><?php esc_html_e( 'Content Indexing', 'ai-site-assistant-autopilot' ); ?></span>
					</h2>
					<p class="sp-sub" style="max-width:640px;line-height:1.6"><?php esc_html_e( 'Click the button below to scan and index all your published posts and pages. AI Site Assistant AutoPilot will summarize each one into structured knowledge. You only need to do this once — new content is indexed automatically when you publish or edit.', 'ai-site-assistant-autopilot' ); ?></p>

					<?php if ( $semantic_on ) : ?>
						<div class="sp-alert sp-alert--soft">
							<?php echo Ui::icon( 'search', 16, '#4f46e5', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							<span>
								<strong><?php esc_html_e( 'Semantic search is ON.', 'ai-site-assistant-autopilot' ); ?></strong>
								<?php
								printf(
									/* translators: %1$d: embedded items, %2$d: total indexed items. */
									esc_html__( '%1$d of %2$d indexed items have an embedding. Run indexing to build any missing vectors.', 'ai-site-assistant-autopilot' ),
									(int) $embedded_count,
									(int) $stats['total']
								);
								?>
							</span>
						</div>
					<?php endif; ?>

					<div id="sp-kb-progress" hidden style="margin-bottom:16px">
						<div class="sp-bar-head">
							<span id="sp-kb-progress-text" style="font-weight:600"><?php esc_html_e( 'Indexing…', 'ai-site-assistant-autopilot' ); ?></span>
							<span id="sp-kb-progress-percent" class="sp-td--muted">0%</span>
						</div>
						<div class="sp-bar"><div id="sp-kb-progress-bar" class="sp-bar__fill sp-bar__fill--grad" style="width:0%"></div></div>
						<div id="sp-kb-progress-log" style="max-height:180px;overflow-y:auto;margin-top:10px;font-size:12.5px;color:#646970;display:flex;flex-direction:column;gap:4px"></div>
					</div>

					<div class="sp-btn-group">
						<button type="button" id="sp-kb-start-index" class="sp-btn sp-btn--gradient sp-btn--r8" style="padding:12px 22px" <?php disabled( ! $configured ); ?>>
							<?php echo esc_html( (int) $stats['total'] > 0 ? __( 'Index new items', 'ai-site-assistant-autopilot' ) : __( 'Start Indexing', 'ai-site-assistant-autopilot' ) ); ?>
						</button>
						<button type="button" id="sp-kb-reindex" class="sp-btn sp-btn--ghost sp-btn--r8" style="padding:12px 18px" <?php disabled( ! $configured ); ?>>
							<?php esc_html_e( 'Full Re-index', 'ai-site-assistant-autopilot' ); ?>
						</button>
					</div>
				</div>

				<div id="sp-kb-gaps" class="sp-card sp-card--24" hidden style="margin-bottom:16px">
					<div class="sp-card__split">
						<h2 class="sp-h2 sp-h2--md sp-mb0" style="display:flex;align-items:center;gap:9px">
							<?php echo Ui::icon( 'warning', 18, '#d97706', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							<span><?php esc_html_e( 'Questions Without Answers', 'ai-site-assistant-autopilot' ); ?></span>
							<span id="sp-kb-gaps-count" class="sp-pill sp-pill--warn">0</span>
						</h2>
						<button type="button" id="sp-kb-gaps-clear" class="sp-btn sp-btn--ghost sp-btn--xs"><?php esc_html_e( 'Clear all', 'ai-site-assistant-autopilot' ); ?></button>
					</div>
					<p class="sp-sub sp-sub--sm"><?php esc_html_e( 'These are questions visitors asked that your content does not answer yet. Write or index a page covering them to improve future answers.', 'ai-site-assistant-autopilot' ); ?></p>
					<div id="sp-kb-gaps-list" class="sp-stack sp-stack--sm"></div>
				</div>

				<div class="sp-card sp-card--bare sp-card--flat">
					<div class="sp-filters">
						<input type="search" id="sp-kb-search" class="sp-input sp-input--filter sp-input--grow" placeholder="<?php esc_attr_e( 'Search indexed content', 'ai-site-assistant-autopilot' ); ?>" aria-label="<?php esc_attr_e( 'Search indexed content', 'ai-site-assistant-autopilot' ); ?>" />
						<select id="sp-kb-type-filter" class="sp-select sp-input--filter" style="width:auto" aria-label="<?php esc_attr_e( 'Filter by type', 'ai-site-assistant-autopilot' ); ?>">
							<option value=""><?php esc_html_e( 'All types', 'ai-site-assistant-autopilot' ); ?></option>
							<?php foreach ( (array) $stats['post_types'] as $type => $count ) : ?>
								<option value="<?php echo esc_attr( (string) $type ); ?>"><?php echo esc_html( $type . ' (' . $count . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php if ( ! empty( $stats['content_types'] ) ) : ?>
							<select id="sp-kb-ctype-filter" class="sp-select sp-input--filter" style="width:auto" aria-label="<?php esc_attr_e( 'Filter by category', 'ai-site-assistant-autopilot' ); ?>">
								<option value=""><?php esc_html_e( 'All categories', 'ai-site-assistant-autopilot' ); ?></option>
								<?php foreach ( (array) $stats['content_types'] as $ctype => $count ) : ?>
									<option value="<?php echo esc_attr( (string) $ctype ); ?>"><?php echo esc_html( ( $ct_labels[ $ctype ] ?? ucfirst( (string) $ctype ) ) . ' (' . $count . ')' ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
					</div>

					<div class="sp-tr sp-tr--head" style="--sp-cols:<?php echo esc_attr( self::COLS ); ?>">
						<span><?php esc_html_e( 'Title', 'ai-site-assistant-autopilot' ); ?></span>
						<span><?php esc_html_e( 'Type', 'ai-site-assistant-autopilot' ); ?></span>
						<span><?php esc_html_e( 'Status', 'ai-site-assistant-autopilot' ); ?></span>
						<span><?php esc_html_e( 'Indexed', 'ai-site-assistant-autopilot' ); ?></span>
						<span class="sp-sr"><?php esc_html_e( 'Actions', 'ai-site-assistant-autopilot' ); ?></span>
					</div>
					<div id="sp-kb-rows" data-cols="<?php echo esc_attr( self::COLS ); ?>">
						<div class="sp-empty"><?php esc_html_e( 'Loading…', 'ai-site-assistant-autopilot' ); ?></div>
					</div>
					<div id="sp-kb-pagination" class="sp-row-wrap" style="padding:12px 18px;justify-content:center"></div>
				</div>

				<!-- Pre-index picker -->
				<div id="sp-kb-modal" class="sp-modal" hidden>
					<div class="sp-modal__dialog sp-modal__dialog--lg" role="dialog" aria-modal="true" aria-labelledby="sp-kb-modal-title">
						<h2 class="sp-modal__title" id="sp-kb-modal-title"><?php esc_html_e( 'What should AISAAutoPilot index?', 'ai-site-assistant-autopilot' ); ?></h2>
						<p class="sp-modal__text"><?php esc_html_e( 'Choose which content to read and learn from. You can re-run this any time.', 'ai-site-assistant-autopilot' ); ?></p>

						<div style="max-height:46vh;overflow-y:auto;margin-bottom:16px">
							<div class="sp-h3" style="font-size:13px"><?php esc_html_e( 'Content types', 'ai-site-assistant-autopilot' ); ?></div>
							<div class="sp-tiles sp-tiles--250" style="margin:8px 0 18px">
								<?php foreach ( $type_options as $type ) : ?>
									<label class="sp-check sp-check--card">
										<input type="checkbox" class="sp-kb-type-opt" value="<?php echo esc_attr( (string) $type['slug'] ); ?>" checked />
										<span class="sp-check__body" style="flex:1"><?php echo esc_html( (string) $type['label'] ); ?></span>
										<span class="sp-tag"><?php echo esc_html( (string) $type['count'] ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>

							<?php if ( ! empty( $categories ) ) : ?>
								<div class="sp-h3" style="font-size:13px"><?php esc_html_e( 'Post categories', 'ai-site-assistant-autopilot' ); ?></div>
								<label class="sp-check" style="margin:8px 0">
									<input type="checkbox" id="sp-kb-cat-all" checked />
									<span><?php esc_html_e( 'All categories', 'ai-site-assistant-autopilot' ); ?></span>
								</label>
								<div class="sp-tiles sp-tiles--250" id="sp-kb-cat-list" hidden>
									<?php foreach ( $categories as $cat ) : ?>
										<label class="sp-check sp-check--card">
											<input type="checkbox" class="sp-kb-cat-opt" value="<?php echo esc_attr( (string) $cat->term_id ); ?>" />
											<span class="sp-check__body" style="flex:1"><?php echo esc_html( $cat->name ); ?></span>
											<span class="sp-tag"><?php echo esc_html( (string) $cat->count ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>

						<div class="sp-modal__foot">
							<button type="button" class="sp-btn sp-btn--ghost" data-close="1"><?php esc_html_e( 'Cancel', 'ai-site-assistant-autopilot' ); ?></button>
							<button type="button" id="sp-kb-modal-start" class="sp-btn"><?php esc_html_e( 'Start Indexing', 'ai-site-assistant-autopilot' ); ?></button>
						</div>
					</div>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
		Ui::json_script( 'sp-kb-ctype-labels', $ct_labels );
		Ui::json_script(
			'sp-kb-i18n',
			array(
				'indexed'     => __( 'Indexed', 'ai-site-assistant-autopilot' ),
				'pending'     => __( 'Pending', 'ai-site-assistant-autopilot' ),
				'loading'     => __( 'Loading…', 'ai-site-assistant-autopilot' ),
				'empty'       => __( 'No indexed content yet.', 'ai-site-assistant-autopilot' ),
				'emptyHint'   => __( 'Use “Start Indexing” above to build your knowledge base.', 'ai-site-assistant-autopilot' ),
				'loadFailed'  => __( 'Failed to load data.', 'ai-site-assistant-autopilot' ),
				'remove'      => __( 'Remove from knowledge base', 'ai-site-assistant-autopilot' ),
				'confirmOne'  => __( 'Remove this item from the knowledge base?', 'ai-site-assistant-autopilot' ),
				'confirmAll'  => __( 'This will clear all indexed data and re-index everything. Continue?', 'ai-site-assistant-autopilot' ),
				'confirmGaps' => __( 'Clear all logged questions?', 'ai-site-assistant-autopilot' ),
				'pickOneType' => __( 'Please select at least one content type to index.', 'ai-site-assistant-autopilot' ),
				/* translators: 1: current page number, 2: total number of pages. */
				'page'        => __( 'Page %1$d of %2$d', 'ai-site-assistant-autopilot' ),
				'reading'     => __( 'Reading your content…', 'ai-site-assistant-autopilot' ),
				'analysing'   => __( 'Analysing the text…', 'ai-site-assistant-autopilot' ),
				'summarising' => __( 'Summarising key points…', 'ai-site-assistant-autopilot' ),
				'keywords'    => __( 'Extracting keywords…', 'ai-site-assistant-autopilot' ),
				'organising'  => __( 'Organising information…', 'ai-site-assistant-autopilot' ),
				'done'        => __( 'Indexing complete. Your knowledge base is ready.', 'ai-site-assistant-autopilot' ),
				'failed'      => __( 'Indexing failed.', 'ai-site-assistant-autopilot' ),
				'dismiss'     => __( 'Dismiss', 'ai-site-assistant-autopilot' ),
			)
		);
	}

	/**
	 * Friendly, translatable labels for the automatic content-type slugs.
	 *
	 * @return array<string, string>
	 */
	private static function content_type_labels(): array {
		return array(
			'tutorial' => __( 'Tutorial / Guide', 'ai-site-assistant-autopilot' ),
			'product'  => __( 'Product', 'ai-site-assistant-autopilot' ),
			'service'  => __( 'Service', 'ai-site-assistant-autopilot' ),
			'article'  => __( 'Article', 'ai-site-assistant-autopilot' ),
			'page'     => __( 'Page', 'ai-site-assistant-autopilot' ),
			'faq'      => __( 'FAQ', 'ai-site-assistant-autopilot' ),
			'news'     => __( 'News', 'ai-site-assistant-autopilot' ),
			'other'    => __( 'Other', 'ai-site-assistant-autopilot' ),
		);
	}
}
