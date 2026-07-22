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
		$configured  = ( new ProviderManager() )->is_configured();
		$stats       = $kb->table_exists() ? $kb->get_stats() : array( 'total' => 0, 'indexed' => 0, 'pending' => 0, 'post_types' => array(), 'last_indexed' => null );
		$total_posts = $indexer->get_total_posts();
		$warning     = $usage->get_threshold_warning();
		?>
		<div class="wrap sitepilot-wrap sitepilot-kb-wrap">
			<h1 class="sitepilot-title">
				<span class="dashicons dashicons-database"></span>
				<?php esc_html_e( 'SitePilot AI — Knowledge Base', 'sitepilot-ai' ); ?>
			</h1>
			<p class="sitepilot-subtitle">
				<?php esc_html_e( 'SitePilot reads and understands your site content so it can answer questions accurately. Index your content once, and it stays updated automatically.', 'sitepilot-ai' ); ?>
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
					<span class="sitepilot-kb-stat__number"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></span>
					<span class="sitepilot-kb-stat__label"><?php esc_html_e( 'Indexed Items', 'sitepilot-ai' ); ?></span>
				</div>
				<div class="sitepilot-kb-stat">
					<span class="sitepilot-kb-stat__number"><?php echo esc_html( number_format_i18n( $total_posts ) ); ?></span>
					<span class="sitepilot-kb-stat__label"><?php esc_html_e( 'Total Posts/Pages', 'sitepilot-ai' ); ?></span>
				</div>
				<div class="sitepilot-kb-stat">
					<span class="sitepilot-kb-stat__number">
						<?php echo $stats['last_indexed'] ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $stats['last_indexed'] ) ) ) : '—'; ?>
					</span>
					<span class="sitepilot-kb-stat__label"><?php esc_html_e( 'Last Updated', 'sitepilot-ai' ); ?></span>
				</div>
			</div>

			<!-- Indexing Control -->
			<div class="sitepilot-card sitepilot-kb-indexer">
				<h2><?php esc_html_e( 'Content Indexing', 'sitepilot-ai' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Click the button below to scan and index all your published posts and pages. SitePilot will summarize each one using AI. You only need to do this once — new content is indexed automatically.', 'sitepilot-ai' ); ?>
				</p>

				<div class="sitepilot-kb-indexer__actions">
					<button type="button" id="sp-kb-start-index" class="button button-primary button-hero"
						<?php disabled( ! $configured ); ?>>
						<span class="dashicons dashicons-update"></span>
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

			<!-- KB Table -->
			<div class="sitepilot-card sitepilot-kb-table-wrap">
				<div class="sitepilot-kb-table__header">
					<h2><?php esc_html_e( 'Indexed Content', 'sitepilot-ai' ); ?></h2>
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
						<tr><td colspan="6" class="text-center"><?php esc_html_e( 'Loading…', 'sitepilot-ai' ); ?></td></tr>
					</tbody>
				</table>

				<div id="sp-kb-pagination" class="sitepilot-kb-pagination"></div>
			</div>
		</div>
		<?php
	}
}
