<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Logging\Logger;
use AISAAutoPilot\Providers\ProviderManager;
use AISAAutoPilot\Usage\UsageTracker;

/**
 * WP-01 — Home.
 *
 * A live version of the wireframe landing page: start a task, see what needs
 * attention, review pending approvals, and glance at usage.
 */
final class HomePage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$provider_ready = ( new ProviderManager() )->is_configured();
		$efb_ready      = ( new EasyFormBuilderConnector() )->is_available();
		$recipient      = Settings::form_notification_email();
		$knowledge      = new KnowledgeBase();
		$kb_total       = $knowledge->table_exists() ? (int) ( $knowledge->get_stats()['total'] ?? 0 ) : 0;
		$logs           = ( new Logger() )->recent( 4 );
		$report         = ( new UsageTracker() )->get_report();
		$healthy        = $provider_ready && '' !== $recipient;

		$host         = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$compose_url  = admin_url( 'admin.php?page=' . AdminMenu::SLUG_FORM_BUILDER );
		$history_url  = admin_url( 'admin.php?page=' . AdminMenu::SLUG_HISTORY );
		$settings_url = admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETUP );
		$kb_url      = admin_url( 'admin.php?page=' . AdminMenu::SLUG_KB );
		$chat_url    = admin_url( 'admin.php?page=' . AdminMenu::SLUG_CHAT );
		$widget_url  = admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIDGET );
		$usage_url   = admin_url( 'admin.php?page=' . AdminMenu::SLUG_USAGE );
		$conn_url    = admin_url( 'admin.php?page=' . AdminMenu::SLUG_CONNECTIONS );

		$budget    = (int) $report['budget'];
		$used_pct  = (float) $report['used_percent'];
		$attention = $this->attention_items( $provider_ready, $recipient, $efb_ready, $kb_total, $settings_url, $kb_url, $conn_url );

		Ui::grad_defs();
		?>
		<div class="wrap sp-app">
			<section class="sp-screen">
				<div class="sp-page-head" style="margin-bottom:20px">
					<div>
						<?php Ui::title( __( 'Home', 'ai-site-assistant-autopilot' ) ); ?>
						<div class="sp-meta-row">
							<span class="sp-mono"><?php echo esc_html( $host ); ?></span>
							<?php echo Ui::pill( $healthy ? __( 'All systems healthy', 'ai-site-assistant-autopilot' ) : __( 'Setup needs attention', 'ai-site-assistant-autopilot' ), $healthy ? 'ok' : 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in pill(). ?>
							<span><?php echo esc_html( $this->last_activity_label( $logs ) ); ?></span>
						</div>
					</div>
					<a class="sp-btn" href="<?php echo esc_url( $compose_url ); ?>">
						<?php echo Ui::icon( 'spark', 16, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
						<span><?php esc_html_e( 'New AI task', 'ai-site-assistant-autopilot' ); ?></span>
					</a>
				</div>

				<div class="sp-cols">
					<div class="sp-col">

						<div class="sp-card">
							<h2 class="sp-h2"><?php esc_html_e( 'Start a task with AI', 'ai-site-assistant-autopilot' ); ?></h2>
							<p class="sp-sub" style="margin-bottom:12px !important"><?php esc_html_e( 'Describe what you need. We build a reviewable plan first — nothing changes until you approve.', 'ai-site-assistant-autopilot' ); ?></p>
							<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
								<input type="hidden" name="page" value="<?php echo esc_attr( AdminMenu::SLUG_FORM_BUILDER ); ?>" />
								<textarea name="prompt" class="sp-textarea" style="margin-bottom:12px" placeholder="<?php esc_attr_e( 'e.g. Build a contact form with name, email and message', 'ai-site-assistant-autopilot' ); ?>"></textarea>
								<div class="sp-row-wrap">
									<button type="submit" class="sp-btn" style="padding:10px 20px"><?php esc_html_e( 'Start', 'ai-site-assistant-autopilot' ); ?></button>
									<span class="sp-help sp-help--12">
										<?php
										printf(
											/* translators: %s: connector name. */
											esc_html__( 'Connector: %s · saves as draft', 'ai-site-assistant-autopilot' ),
											esc_html( $efb_ready ? 'Easy Form Builder' : 'AISAAutoPilot Forms' )
										);
										?>
									</span>
								</div>
							</form>
						</div>

						<?php if ( array() !== $attention ) : ?>
							<div class="sp-card sp-card--18">
								<h2 class="sp-h2 sp-h2--sm" style="display:flex;align-items:center;gap:8px;margin-bottom:12px !important">
									<?php echo Ui::icon( 'warning', 16, '#d97706', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
									<span><?php esc_html_e( 'Needs your attention', 'ai-site-assistant-autopilot' ); ?></span>
								</h2>
								<div class="sp-stack">
									<?php foreach ( $attention as $item ) : ?>
										<div class="sp-notice sp-notice--<?php echo esc_attr( $item['tone'] ); ?>">
											<span class="sp-notice__dot"></span>
											<div class="sp-notice__body">
												<div class="sp-notice__title"><?php echo esc_html( $item['title'] ); ?></div>
												<div class="sp-notice__text"><?php echo esc_html( $item['body'] ); ?></div>
											</div>
											<a class="sp-btn sp-btn--ghost sp-btn--xs" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['cta'] ); ?></a>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endif; ?>

						<div class="sp-card sp-card--18">
							<div class="sp-card__split">
								<h2 class="sp-h2 sp-h2--sm sp-mb0"><?php esc_html_e( 'Waiting for your approval', 'ai-site-assistant-autopilot' ); ?></h2>
								<a class="sp-btn sp-btn--plain" href="<?php echo esc_url( $history_url ); ?>"><?php esc_html_e( 'Review actions', 'ai-site-assistant-autopilot' ); ?></a>
							</div>
							<a class="sp-pending" href="<?php echo esc_url( $compose_url ); ?>">
								<span class="sp-pending__icon"><?php echo Ui::icon( 'form', 16, '#4f46e5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
								<span class="sp-pending__body">
									<span class="sp-pending__title"><?php esc_html_e( 'No proposal is waiting', 'ai-site-assistant-autopilot' ); ?></span>
									<span class="sp-pending__meta"><?php esc_html_e( 'Describe a form to create the next reviewable plan.', 'ai-site-assistant-autopilot' ); ?></span>
								</span>
								<span class="sp-pill sp-pill--info"><?php esc_html_e( 'Start', 'ai-site-assistant-autopilot' ); ?></span>
							</a>
						</div>

						<div class="sp-card sp-card--18">
							<h2 class="sp-h2 sp-h2--sm" style="margin-bottom:12px !important"><?php esc_html_e( 'Recent activity', 'ai-site-assistant-autopilot' ); ?></h2>
							<?php if ( array() === $logs ) : ?>
								<p class="sp-empty"><?php esc_html_e( 'No activity has been recorded yet.', 'ai-site-assistant-autopilot' ); ?></p>
							<?php else : ?>
								<div class="sp-list">
									<?php foreach ( $logs as $log ) : ?>
										<?php $tone = $this->level_tone( (string) ( $log['level'] ?? 'info' ) ); ?>
										<div class="sp-list__row">
											<?php echo Ui::pill( (string) ( $log['event'] ?? 'event' ), $tone, false, 'sp-pill--sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in pill(). ?>
											<span class="sp-list__title"><?php echo esc_html( (string) ( $log['message'] ?? '' ) ); ?></span>
											<span class="sp-list__time"><?php echo esc_html( $this->ago( (string) ( $log['time'] ?? '' ) ) ); ?></span>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<aside class="sp-side">
						<div class="sp-card sp-card--16 sp-card--flat">
							<div class="sp-card__split">
								<span class="sp-h3 sp-mb0"><?php esc_html_e( 'Usage this period', 'ai-site-assistant-autopilot' ); ?></span>
								<span class="sp-help">Local BYOK</span>
							</div>
							<div style="display:flex;align-items:baseline;gap:6px;margin-bottom:8px">
								<span style="font-size:24px;font-weight:800"><?php echo esc_html( $budget > 0 ? number_format_i18n( $used_pct, 0 ) . '%' : number_format_i18n( (int) $report['totals']['total_tokens'] ) ); ?></span>
								<span style="font-size:13px;color:#646970">
									<?php
									echo $budget > 0
										? esc_html( sprintf( /* translators: %s: monthly token budget. */ __( 'of the %s monthly token budget', 'ai-site-assistant-autopilot' ), number_format_i18n( $budget ) ) )
										: esc_html__( 'tokens used · no budget set', 'ai-site-assistant-autopilot' );
									?>
								</span>
							</div>
							<?php Ui::bar( $budget > 0 ? $used_pct : 0.0, '', $used_pct >= 75 ? 'sp-bar__fill--warn' : '' ); ?>
							<p class="sp-help sp-help--12" style="margin:9px 0 12px">
								<?php
								echo $report['warning']
									? esc_html( (string) $report['warning']['message'] )
									: esc_html__( 'Resets at the start of next month.', 'ai-site-assistant-autopilot' );
								?>
							</p>
							<a class="sp-btn sp-btn--ghost sp-btn--block" style="padding:9px" href="<?php echo esc_url( $usage_url ); ?>"><?php esc_html_e( 'Usage details', 'ai-site-assistant-autopilot' ); ?></a>
						</div>

						<div class="sp-card sp-card--16 sp-card--flat">
							<div class="sp-h3" style="margin-bottom:10px"><?php esc_html_e( 'Quick actions', 'ai-site-assistant-autopilot' ); ?></div>
							<div class="sp-stack sp-stack--sm">
								<a class="sp-shortcut" href="<?php echo esc_url( $compose_url ); ?>"><?php echo Ui::icon( 'form', 15, '#4f46e5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><span><?php esc_html_e( 'Build a form', 'ai-site-assistant-autopilot' ); ?></span></a>
								<a class="sp-shortcut" href="<?php echo esc_url( $chat_url ); ?>"><?php echo Ui::icon( 'chat', 15, '#4f46e5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><span><?php esc_html_e( 'Ask your site', 'ai-site-assistant-autopilot' ); ?></span></a>
								<a class="sp-shortcut" href="<?php echo esc_url( $kb_url ); ?>"><?php echo Ui::icon( 'book', 15, '#4f46e5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><span><?php esc_html_e( 'Index content', 'ai-site-assistant-autopilot' ); ?></span></a>
								<a class="sp-shortcut" href="<?php echo esc_url( $widget_url ); ?>"><?php echo Ui::icon( 'globe', 15, '#4f46e5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><span><?php esc_html_e( 'Set up the chat widget', 'ai-site-assistant-autopilot' ); ?></span></a>
							</div>
						</div>
					</aside>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
	}

	/**
	 * The "needs your attention" list, derived from real dependency state.
	 *
	 * @return array<int, array{tone:string,title:string,body:string,cta:string,url:string}>
	 */
	private function attention_items( bool $provider_ready, string $recipient, bool $efb_ready, int $kb_total, string $settings_url, string $kb_url, string $conn_url ): array {
		$items = array();

		if ( ! $provider_ready ) {
			$items[] = array(
				'tone'  => 'err',
				'title' => __( 'AI provider is not connected', 'ai-site-assistant-autopilot' ),
				'body'  => __( 'Add an API key before any plan can be generated.', 'ai-site-assistant-autopilot' ),
				'cta'   => __( 'Fix it', 'ai-site-assistant-autopilot' ),
				'url'   => $settings_url,
			);
		}

		if ( '' === $recipient ) {
			$items[] = array(
				'tone'  => 'warn',
				'title' => __( 'No form notification email', 'ai-site-assistant-autopilot' ),
				'body'  => __( 'AISAAutoPilot Forms need one address to deliver submissions to.', 'ai-site-assistant-autopilot' ),
				'cta'   => __( 'Set address', 'ai-site-assistant-autopilot' ),
				'url'   => $settings_url,
			);
		}

		if ( 0 === $kb_total ) {
			$items[] = array(
				'tone'  => 'warn',
				'title' => __( 'Knowledge base is empty', 'ai-site-assistant-autopilot' ),
				'body'  => __( 'For accurate answers, index your public content first.', 'ai-site-assistant-autopilot' ),
				'cta'   => __( 'Index now', 'ai-site-assistant-autopilot' ),
				'url'   => $kb_url,
			);
		}

		if ( ! $efb_ready ) {
			$items[] = array(
				'tone'  => 'off',
				'title' => __( 'Easy Form Builder is not available', 'ai-site-assistant-autopilot' ),
				'body'  => __( 'Advanced forms fall back to the built-in AISAAutoPilot Forms connector.', 'ai-site-assistant-autopilot' ),
				'cta'   => __( 'Review', 'ai-site-assistant-autopilot' ),
				'url'   => $conn_url,
			);
		}

		return $items;
	}

	/** Map a log level to a pill tone. */
	private function level_tone( string $level ): string {
		switch ( $level ) {
			case 'error':
				return 'err';
			case 'warning':
				return 'warn';
			case 'debug':
				return 'off';
			default:
				return 'info';
		}
	}

	/** @param array<int, array<string, mixed>> $logs */
	private function last_activity_label( array $logs ): string {
		if ( array() === $logs ) {
			return __( 'No activity yet', 'ai-site-assistant-autopilot' );
		}

		return sprintf(
			/* translators: %s: human-readable time difference, e.g. "4 mins". */
			__( 'Last activity %s', 'ai-site-assistant-autopilot' ),
			$this->ago( (string) ( $logs[0]['time'] ?? '' ) )
		);
	}

	/** Human "x ago" for a stored UTC timestamp. */
	private function ago( string $stamp ): string {
		$time = strtotime( $stamp );
		if ( ! $time ) {
			return '—';
		}

		return sprintf(
			/* translators: %s: human-readable time difference, e.g. "4 mins". */
			__( '%s ago', 'ai-site-assistant-autopilot' ),
			human_time_diff( $time, time() )
		);
	}
}
