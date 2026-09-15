<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Content\ContentIndexer;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Providers\ProviderManager;

/**
 * Knowledge Base — index the site content AISAAutoPilot may quote from.
 */
final class KnowledgeBasePage {


	public const SLUG = 'aisaautopilot-kb';

	/**
	 * Grid template for the indexed-content table.
	 */
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

		$total_posts  = $indexer->get_total_posts();
		$type_options = $indexer->get_indexable_types_with_counts();
		$categories   = get_categories(
			array(
				'hide_empty' => false,
				'number'     => 200,
			)
		);
		$ct_labels    = self::content_type_labels();
		$settings_url = admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETUP );
		$overview_url = admin_url( 'admin.php?page=' . AdminMenu::SLUG_KB );
		$auto_index   = (bool) Settings::get( 'kb_auto_index', true );
		$tab          = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection.
		if ( ! in_array( $tab, array( 'overview', 'collections', 'queue', 'sources', 'ask', 'entries' ), true ) ) {
			$tab = 'overview';
		}

		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-sp-kb>
			<section class="sp-screen">
		<?php
		Ui::page_header(
			__( 'Site Knowledge', 'ai-site-assistant-autopilot' ),
			__( 'Everything the AI is allowed to read when it answers.', 'ai-site-assistant-autopilot' ),
			array(
				'chip'    => ConnectionChip::derive(),
				'action'  => array(
					'label' => __( 'Re-index everything', 'ai-site-assistant-autopilot' ),
					'url'   => '#',
					'id'    => 'sp-kb-header-reindex',
				),
				'subtabs' => $this->subtabs( $tab ),
			),
		);
		?>
		<div class="sp-pagebody">

		<?php if ( ! $configured ) : ?>
			<?php
			Ui::alert(
				'warn',
				'warning',
				esc_html__( 'No AI provider is connected.', 'ai-site-assistant-autopilot' ) . ' <a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Add your API key in Settings', 'ai-site-assistant-autopilot' ) . '</a>.'
			);
			?>
		<?php endif; ?>

		<?php $this->render_session_progress(); ?>

		<?php if ( 'overview' === $tab ) : ?>
				<section class="sp-kb-overview" data-sp-kb-overview>
			<?php if ( 0 === (int) $stats['total'] ) : ?>
					<div class="sp-kb-empty" data-sp-kb-empty>
						<div class="sp-kb-empty__icon">
				<?php echo Ui::icon( 'book', 22, '#5c5f80', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
						</div>
						<h2 class="sp-kb-empty__title"><?php esc_html_e( 'Nothing has been read yet', 'ai-site-assistant-autopilot' ); ?></h2>
						<p class="sp-kb-empty__copy">
				<?php
				if ( $total_posts > 0 ) {
					printf(
						/* translators: %s: published post count. */
						esc_html__( 'A first pass can read the %s published items currently available to Site Knowledge. While it runs, keep this browser tab open.', 'ai-site-assistant-autopilot' ),
						esc_html( number_format_i18n( $total_posts ) )
					);
				} else {
					esc_html_e( 'There are no published posts or pages available to read yet. Publish content, then start the first pass.', 'ai-site-assistant-autopilot' );
				}
				?>
						</p>
						<button type="button" id="sp-kb-start-index" class="sp-btn sp-btn--gradient sp-btn--r8 sp-kb-empty__action" <?php disabled( ! $configured || 0 === $total_posts ); ?>>
				<?php esc_html_e( 'Start the first pass', 'ai-site-assistant-autopilot' ); ?>
						</button>
					</div>
			<?php else : ?>
					<div class="sp-kb-overview__grid">
						<section class="sp-kb-card" data-sp-kb-coverage>
							<h2 class="sp-kb-card__title"><?php esc_html_e( 'What has been read', 'ai-site-assistant-autopilot' ); ?></h2>
							<div class="sp-kb-coverage">
				<?php foreach ( $type_options as $type ) : ?>
					<?php
					$slug      = (string) $type['slug'];
					$indexed   = (int) ( $stats['post_types'][ $slug ] ?? 0 );
					$available = (int) $type['count'];
					$percent   = $available > 0 ? min( 100, (int) round( ( $indexed / $available ) * 100 ) ) : 0;
					?>
								<div class="sp-kb-coverage__row" data-sp-kb-coverage-row="<?php echo esc_attr( $slug ); ?>">
									<div class="sp-kb-coverage__head">
										<span><?php echo esc_html( (string) $type['label'] ); ?></span>
										<span dir="ltr"><?php echo esc_html( sprintf( '%1$s / %2$s', number_format_i18n( $indexed ), number_format_i18n( $available ) ) ); ?></span>
									</div>
									<div class="sp-kb-coverage__track" aria-hidden="true">
										<span class="sp-kb-coverage__fill" style="width:<?php echo esc_attr( (string) $percent ); ?>%"></span>
									</div>
								</div>
				<?php endforeach; ?>
				<?php if ( empty( $type_options ) ) : ?>
								<p class="sp-kb-card__empty"><?php esc_html_e( 'No readable content types are currently available.', 'ai-site-assistant-autopilot' ); ?></p>
			<?php endif; ?>
							</div>
						</section>

						<section class="sp-kb-card sp-kb-card--sources" data-sp-kb-sources>
							<h2 class="sp-kb-card__title"><?php esc_html_e( 'Where it reads from', 'ai-site-assistant-autopilot' ); ?></h2>
				<?php foreach ( $type_options as $type ) : ?>
					<?php
					$slug    = (string) $type['slug'];
					$indexed = (int) ( $stats['post_types'][ $slug ] ?? 0 );
					?>
								<div class="sp-kb-source-row" data-sp-kb-source="<?php echo esc_attr( $slug ); ?>">
									<div class="sp-kb-source-row__body">
										<strong><?php echo esc_html( (string) $type['label'] ); ?></strong>
										<span><bdi>
					<?php
					printf(
						/* translators: 1: published item count, 2: indexed item count. */
						esc_html__( '%1$s published · %2$s indexed', 'ai-site-assistant-autopilot' ),
						esc_html( number_format_i18n( (int) $type['count'] ) ),
						esc_html( number_format_i18n( $indexed ) )
					);
					?>
										</bdi></span>
									</div>
									<span class="sp-kb-source-row__state <?php echo esc_attr( $auto_index ? 'is-on' : 'is-manual' ); ?>">
					<bdi><?php echo esc_html( $auto_index ? __( 'Auto-sync on publish', 'ai-site-assistant-autopilot' ) : __( 'Manual sync', 'ai-site-assistant-autopilot' ) ); ?></bdi>
									</span>
									<button type="button" class="sp-kb-source-row__action" data-sp-kb-open-picker="<?php echo esc_attr( $slug ); ?>" <?php disabled( ! $configured ); ?>><bdi><?php esc_html_e( 'Index', 'ai-site-assistant-autopilot' ); ?></bdi></button>
								</div>
				<?php endforeach; ?>
				<?php if ( empty( $type_options ) ) : ?>
								<p class="sp-kb-card__empty"><?php esc_html_e( 'No sources are available yet.', 'ai-site-assistant-autopilot' ); ?></p>
			<?php endif; ?>
						</section>
					</div>

					<section id="sp-kb-gaps" class="sp-kb-gaps" data-sp-kb-gaps hidden>
						<div class="sp-kb-gaps__head">
							<div>
								<div class="sp-kb-gaps__title-row">
									<h2 class="sp-kb-card__title"><?php esc_html_e( 'Questions your site could not answer', 'ai-site-assistant-autopilot' ); ?></h2>
									<span id="sp-kb-gaps-count" class="sp-kb-gaps__count">0</span>
								</div>
								<p><?php esc_html_e( 'These are recorded content gaps. Dismiss one after you have published coverage for it.', 'ai-site-assistant-autopilot' ); ?></p>
							</div>
							<button type="button" id="sp-kb-gaps-clear" class="sp-btn sp-btn--ghost sp-btn--xs"><?php esc_html_e( 'Clear all', 'ai-site-assistant-autopilot' ); ?></button>
						</div>
						<div id="sp-kb-gaps-list" class="sp-kb-gaps__list"></div>
					</section>
			<?php endif; ?>
				</section>
		<?php endif; ?>

		<?php if ( 'collections' === $tab ) : ?>
					<section class="sp-kb-panel" data-sp-kb-collections>
						<header class="sp-kb-panel__head">
							<div>
								<h2><?php esc_html_e( 'Collections', 'ai-site-assistant-autopilot' ); ?></h2>
								<p><?php esc_html_e( 'Groups of content the AI is allowed to read.', 'ai-site-assistant-autopilot' ); ?></p>
							</div>
							<span class="sp-kb-panel__note"><?php esc_html_e( 'Derived from the index', 'ai-site-assistant-autopilot' ); ?></span>
						</header>
			<?php foreach ( $type_options as $type ) : ?>
				<?php
				$slug    = (string) $type['slug'];
				$indexed = (int) ( $stats['post_types'][ $slug ] ?? 0 );
				?>
						<div class="sp-kb-collection-row" data-sp-kb-collection="<?php echo esc_attr( $slug ); ?>">
							<span class="sp-kb-collection-row__marker" aria-hidden="true"></span>
							<div class="sp-kb-collection-row__body">
								<strong><?php echo esc_html( (string) $type['label'] ); ?></strong>
								<span>
				<?php
				printf(
					/* translators: 1: published content count, 2: indexed content count. */
					esc_html__( '%1$s published · %2$s indexed', 'ai-site-assistant-autopilot' ),
					esc_html( number_format_i18n( (int) $type['count'] ) ),
					esc_html( number_format_i18n( $indexed ) )
				);
				?>
								</span>
							</div>
							<span class="sp-kb-collection-row__state <?php echo esc_attr( $indexed > 0 ? 'is-indexed' : 'is-empty' ); ?>">
				<?php echo esc_html( $indexed > 0 ? __( 'Indexed', 'ai-site-assistant-autopilot' ) : __( 'Not read yet', 'ai-site-assistant-autopilot' ) ); ?>
							</span>
							<span class="sp-kb-collection-row__lock"><?php esc_html_e( 'Managed by index scope', 'ai-site-assistant-autopilot' ); ?></span>
						</div>
			<?php endforeach; ?>
			<?php if ( empty( $type_options ) ) : ?>
						<div class="sp-kb-panel__empty"><?php esc_html_e( 'No readable collections are available yet.', 'ai-site-assistant-autopilot' ); ?></div>
			<?php endif; ?>
					</section>
		<?php endif; ?>

		<?php if ( 'queue' === $tab ) : ?>
					<section class="sp-kb-panel" data-sp-kb-queue>
						<header class="sp-kb-panel__head">
							<div>
								<h2><?php esc_html_e( 'Indexing queue', 'ai-site-assistant-autopilot' ); ?></h2>
								<p><?php esc_html_e( 'The current indexing pass appears here while this browser tab remains open.', 'ai-site-assistant-autopilot' ); ?></p>
							</div>
							<span class="sp-kb-panel__note"><?php esc_html_e( 'Browser session only', 'ai-site-assistant-autopilot' ); ?></span>
						</header>
						<div class="sp-kb-queue-empty" data-sp-kb-queue-empty>
							<div class="sp-kb-queue-empty__icon">
				<?php echo Ui::icon( 'refresh', 18, '#5c5f80', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
							</div>
							<div>
								<strong><?php esc_html_e( 'No active indexing pass', 'ai-site-assistant-autopilot' ); ?></strong>
								<p><?php esc_html_e( 'This version does not keep a persistent queue, retry list, or resume cursor after you leave the page.', 'ai-site-assistant-autopilot' ); ?></p>
								<a href="<?php echo esc_url( $overview_url ); ?>"><?php esc_html_e( 'Open Overview to start indexing', 'ai-site-assistant-autopilot' ); ?></a>
							</div>
						</div>
					</section>
		<?php endif; ?>

		<?php if ( 'sources' === $tab ) : ?>
					<section class="sp-kb-panel sp-kb-panel--sources" data-sp-kb-sync-sources>
						<header class="sp-kb-panel__head">
							<div>
								<h2><?php esc_html_e( 'Sync sources', 'ai-site-assistant-autopilot' ); ?></h2>
								<p><?php esc_html_e( 'Where content comes from, and how it is re-read.', 'ai-site-assistant-autopilot' ); ?></p>
							</div>
						</header>
			<?php foreach ( $type_options as $type ) : ?>
				<?php
				$slug    = (string) $type['slug'];
				$indexed = (int) ( $stats['post_types'][ $slug ] ?? 0 );
				?>
						<div class="sp-kb-source-row" data-sp-kb-source="<?php echo esc_attr( $slug ); ?>">
							<div class="sp-kb-source-row__body">
								<strong><?php echo esc_html( (string) $type['label'] ); ?></strong>
								<span><bdi>
				<?php
				printf(
					/* translators: 1: published content count, 2: indexed content count. */
					esc_html__( '%1$s published · %2$s indexed', 'ai-site-assistant-autopilot' ),
					esc_html( number_format_i18n( (int) $type['count'] ) ),
					esc_html( number_format_i18n( $indexed ) )
				);
				?>
								</bdi></span>
							</div>
							<span class="sp-kb-source-row__state <?php echo esc_attr( $auto_index ? 'is-on' : 'is-manual' ); ?>">
				<bdi><?php echo esc_html( $auto_index ? __( 'Auto-sync on publish', 'ai-site-assistant-autopilot' ) : __( 'Manual sync', 'ai-site-assistant-autopilot' ) ); ?></bdi>
							</span>
							<button type="button" class="sp-kb-source-row__action" data-sp-kb-open-picker="<?php echo esc_attr( $slug ); ?>" <?php disabled( ! $configured ); ?>><bdi><?php esc_html_e( 'Index', 'ai-site-assistant-autopilot' ); ?></bdi></button>
						</div>
			<?php endforeach; ?>
			<?php if ( empty( $type_options ) ) : ?>
						<div class="sp-kb-panel__empty"><?php esc_html_e( 'No sources are available yet.', 'ai-site-assistant-autopilot' ); ?></div>
			<?php endif; ?>
						<p class="sp-kb-panel__foot"><?php esc_html_e( 'Only the WordPress post types listed above are connected in this version.', 'ai-site-assistant-autopilot' ); ?></p>
					</section>
		<?php endif; ?>

		<?php if ( 'ask' === $tab ) : ?>
					<div class="sp-card sp-card--24" style="margin-bottom:16px">
						<h2 class="sp-h2 sp-h2--md"><?php esc_html_e( 'Ask your knowledge base', 'ai-site-assistant-autopilot' ); ?></h2>
						<p class="sp-sub"><?php esc_html_e( 'Use the internal chat console to test how AutoPilot answers from indexed site knowledge.', 'ai-site-assistant-autopilot' ); ?></p>
						<a class="sp-btn sp-btn--r8" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_CHAT ) ); ?>"><?php esc_html_e( 'Open admin chat console', 'ai-site-assistant-autopilot' ); ?></a>
					</div>
		<?php endif; ?>

		<?php if ( 'entries' === $tab ) : ?>
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
		<?php endif; ?>

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
		</div>
			</section>
		<?php Ui::overlays(); ?>
		</div>
		<?php
		Ui::json_script( 'sp-kb-ctype-labels', $ct_labels );
		Ui::json_script(
			'sp-kb-i18n',
			array(
				'indexed'        => __( 'Indexed', 'ai-site-assistant-autopilot' ),
				'pending'        => __( 'Pending', 'ai-site-assistant-autopilot' ),
				'loading'        => __( 'Loading…', 'ai-site-assistant-autopilot' ),
				'empty'          => __( 'No indexed content yet.', 'ai-site-assistant-autopilot' ),
				'emptyHint'      => __( 'Use “Start Indexing” above to build your knowledge base.', 'ai-site-assistant-autopilot' ),
				'loadFailed'     => __( 'Failed to load data.', 'ai-site-assistant-autopilot' ),
				'remove'         => __( 'Remove from knowledge base', 'ai-site-assistant-autopilot' ),
				'confirmOne'     => __( 'Remove this item from the knowledge base?', 'ai-site-assistant-autopilot' ),
				'confirmAll'     => __( 'This will clear all indexed data and re-index everything. Continue?', 'ai-site-assistant-autopilot' ),
				'confirmGaps'    => __( 'Clear all logged questions?', 'ai-site-assistant-autopilot' ),
				'pickOneType'    => __( 'Please select at least one content type to index.', 'ai-site-assistant-autopilot' ),
				/* translators: 1: current page number, 2: total number of pages. */
				'page'           => __( 'Page %1$d of %2$d', 'ai-site-assistant-autopilot' ),
				'reading'        => __( 'Reading your content…', 'ai-site-assistant-autopilot' ),
				'analysing'      => __( 'Analysing the text…', 'ai-site-assistant-autopilot' ),
				'summarising'    => __( 'Summarising key points…', 'ai-site-assistant-autopilot' ),
				'keywords'       => __( 'Extracting keywords…', 'ai-site-assistant-autopilot' ),
				'organising'     => __( 'Organising information…', 'ai-site-assistant-autopilot' ),
				'done'           => __( 'Indexing complete. Your knowledge base is ready.', 'ai-site-assistant-autopilot' ),
				'doneWithErrors' => __( 'The indexing pass finished, but some items could not be read. Review the messages above before leaving this page.', 'ai-site-assistant-autopilot' ),
				'failed'         => __( 'Indexing failed.', 'ai-site-assistant-autopilot' ),
				'paused'         => __( 'Indexing paused.', 'ai-site-assistant-autopilot' ),
				'resuming'       => __( 'Resuming the indexing pass…', 'ai-site-assistant-autopilot' ),
				'dismiss'        => __( 'Dismiss', 'ai-site-assistant-autopilot' ),
			)
		);
	}

	/**
	 * Render the only progress surface backed by the current batch API.
	 *
	 * The batch remains browser-session work: only the current tab retains its
	 * cursor. A token-cap pause can be resumed in that tab after the cap changes;
	 * there is no persistent job or cross-session retry state.
	 */
	private function render_session_progress(): void {
		?>
		<section id="sp-kb-progress" class="sp-kb-session" data-sp-kb-session hidden aria-live="polite">
			<div class="sp-kb-session__head">
				<div>
					<strong id="sp-kb-progress-text"><?php esc_html_e( 'Preparing the indexing pass…', 'ai-site-assistant-autopilot' ); ?></strong>
					<span><?php esc_html_e( 'This pass continues only while this browser tab stays open. If the local token cap pauses it, raise or reset the cap in Usage & Cost, then resume here.', 'ai-site-assistant-autopilot' ); ?></span>
				</div>
				<span id="sp-kb-progress-percent" class="sp-kb-session__percent">0%</span>
			</div>
			<div class="sp-kb-session__track" aria-hidden="true"><span id="sp-kb-progress-bar" class="sp-kb-session__fill" style="width:0%"></span></div>
			<div id="sp-kb-progress-log" class="sp-kb-session__log"></div>
			<div class="sp-kb-session__actions">
				<button type="button" id="sp-kb-resume" class="sp-btn sp-btn--ghost" data-sp-kb-resume hidden disabled><?php esc_html_e( 'Resume indexing', 'ai-site-assistant-autopilot' ); ?></button>
			</div>
		</section>
		<?php
	}

	/**
	 * Sub-tab definitions for the header bar (mockup order, G-20 + G-04).
	 *
	 * @return array<int, array{id:string,label:string,url:string,active:bool}>
	 */
	private function subtabs( string $current ): array {
		$base  = admin_url( 'admin.php?page=' . AdminMenu::SLUG_KB );
		$items = array(
			'overview'    => __( 'Overview', 'ai-site-assistant-autopilot' ),
			'collections' => __( 'Collections', 'ai-site-assistant-autopilot' ),
			'queue'       => __( 'Indexing Queue', 'ai-site-assistant-autopilot' ),
			'sources'     => __( 'Sync Sources', 'ai-site-assistant-autopilot' ),
		);
		$tabs  = array();
		foreach ( $items as $key => $label ) {
			$tabs[] = array(
				'id'     => $key,
				'label'  => $label,
				'url'    => add_query_arg( 'tab', $key, $base ),
				'active' => $current === $key,
			);
		}
		return $tabs;
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
