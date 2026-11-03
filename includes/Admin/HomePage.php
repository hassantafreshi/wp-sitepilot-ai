<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Chat\VisitorConversationStore;
use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Connectors\WooCommerceConnector;
use AISAAutoPilot\Content\KbGaps;
use AISAAutoPilot\Content\KnowledgeBase;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Forms\FormOutbox;
use AISAAutoPilot\Providers\ProviderManager;
use AISAAutoPilot\Usage\UsageTracker;

/**
 * Overview — the mockup's home screen.
 *
 * Four stat cards, a derived "Needs you" column and a live "Running now"
 * integration list. Every number traces to a real data source; the first-day
 * variant renders when the site has no indexed content and no activity yet.
 */
final class HomePage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$settings       = Settings::all();
		$provider_ready = ( new ProviderManager() )->is_configured();
		$knowledge      = new KnowledgeBase();
		$kb_total       = $knowledge->table_exists() ? (int) ( $knowledge->get_stats()['total'] ?? 0 ) : 0;
		$gaps           = ( new KbGaps() )->open_count();
		$tracker        = new UsageTracker();
		$report         = $tracker->get_report();
		$budget         = (int) $report['budget'];
		$used_percent   = (float) $report['used_percent'];
		$tokens_used    = (int) ( $report['totals']['total_tokens'] ?? 0 );

		$questions_answered = $this->answered_question_count();
		$drafts             = $this->count_generated_posts_by_status( 'draft' );
		$published          = $this->count_generated_posts_by_status( 'publish' );
		$waiting            = $drafts;

		$first_day = 0 === $kb_total && 0 === $questions_answered && 0 === $tokens_used && 0 === $drafts && 0 === $published;

		$stats   = $this->stats( $first_day, $questions_answered, $drafts, $published, $waiting, $tokens_used, $budget );
		$efb     = ( new EasyFormBuilderConnector() )->get_health();
		$needs   = $this->needs_you( $first_day, $provider_ready, $kb_total, $gaps, $waiting, $budget, $used_percent, (bool) $efb['active'], (bool) $efb['available'], ( new FormOutbox() )->count() );
		$running = $this->running_now( $settings );

		Ui::grad_defs();
		?>
		<div class="wrap sp-app">
			<section class="sp-screen" data-sp-overview>
				<?php
				Ui::page_header(
					__( 'Overview', 'ai-site-assistant-autopilot' ),
					__( 'What AutoPilot did on your site, and what needs you.', 'ai-site-assistant-autopilot' ),
					array( 'chip' => ConnectionChip::derive() )
				);
				OverviewNavigation::render( OverviewNavigation::TAB_OVERVIEW );
				?>
				<div class="sp-pagebody">
					<?php if ( ! $provider_ready ) : ?>
						<?php
						Ui::banner(
							'home:noservice',
							'bad',
							__( 'No AI service is connected yet', 'ai-site-assistant-autopilot' ),
							__( 'Nothing can be written or answered until a key is in place. Setup takes about a minute.', 'ai-site-assistant-autopilot' ),
							__( 'Connect a service', 'ai-site-assistant-autopilot' ),
							admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETUP )
						);
						?>
					<?php elseif ( $budget > 0 && $used_percent >= 100.0 ) : ?>
						<?php
						Ui::banner(
							'home:paused',
							'warn',
							__( 'Cap reached — AI generation is paused', 'ai-site-assistant-autopilot' ),
							__( 'Nothing is broken. Raise the cap or wait for the next monthly period; text generation and embeddings resume automatically.', 'ai-site-assistant-autopilot' ),
							__( 'Raise the cap', 'ai-site-assistant-autopilot' ),
							admin_url( 'admin.php?page=' . AdminMenu::SLUG_USAGE )
						);
						?>
					<?php endif; ?>

					<div class="sp-weekstats" data-sp-overview-stats>
						<?php foreach ( $stats as $stat ) : ?>
							<div class="sp-weekstat" data-sp-overview-stat="<?php echo esc_attr( $stat['id'] ); ?>">
								<div class="sp-weekstat__label"><?php echo esc_html( $stat['label'] ); ?></div>
								<div class="sp-weekstat__value"><?php echo esc_html( $stat['value'] ); ?></div>
								<div class="sp-weekstat__foot"><?php echo esc_html( $stat['foot'] ); ?></div>
							</div>
						<?php endforeach; ?>
					</div>


					<div class="sp-home-cols">
						<div>
							<div class="sp-home-colhead"><?php esc_html_e( 'Needs you', 'ai-site-assistant-autopilot' ); ?></div>
							<div class="sp-home-stack">
								<?php if ( array() === $needs ) : ?>
									<div class="sp-home-card">
										<div class="sp-home-card__body-wrap">
											<div class="sp-home-card__title"><?php esc_html_e( 'Nothing needs you right now', 'ai-site-assistant-autopilot' ); ?></div>
											<p class="sp-home-card__body"><?php esc_html_e( 'AutoPilot is running with the settings you chose. This list fills up when something needs a decision.', 'ai-site-assistant-autopilot' ); ?></p>
										</div>
									</div>
								<?php else : ?>
									<?php foreach ( $needs as $item ) : ?>
										<div class="sp-home-card">
											<span class="sp-home-card__icon" aria-hidden="true"><?php echo Ui::icon( $item['icon'], 16, '#5c5f80', 2.0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
											<div class="sp-home-card__body-wrap">
												<div class="sp-home-card__title"><?php echo esc_html( $item['title'] ); ?></div>
												<p class="sp-home-card__body"><?php echo esc_html( $item['body'] ); ?></p>
											</div>
											<a class="sp-home-card__cta" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['cta'] ); ?></a>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>
						<div>
							<div class="sp-home-colhead"><?php esc_html_e( 'Running now', 'ai-site-assistant-autopilot' ); ?></div>
							<div class="sp-home-running">
								<?php foreach ( $running as $row ) : ?>
									<div class="sp-home-running__row" data-sp-overview-running="<?php echo esc_attr( $row['id'] ); ?>">
										<span class="sp-home-running__icon" aria-hidden="true"><?php echo Ui::icon( $row['icon'], 16, $row['on'] ? '#202a8d' : '#9a9dbb', 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
										<span class="sp-home-running__name"><?php echo esc_html( $row['name'] ); ?></span>
										<span class="sp-home-running__state">
											<span class="sp-home-running__dot" style="background:<?php echo esc_attr( $row['on'] ? '#198754' : '#d3d5e4' ); ?>"></span>
											<span style="color:<?php echo esc_attr( $row['on'] ? '#166a45' : '#686b8a' ); ?>"><?php echo esc_html( $row['state'] ); ?></span>
										</span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
	}

	/**
	 * The four stat cards. Values come from the conversation store, the post
	 * tables and UsageTracker — never mocked.
	 *
	 * @return array<int, array{id:string,label:string,value:string,foot:string}>
	 */
	private function stats( bool $first_day, int $questions_answered, int $drafts, int $published, int $waiting, int $tokens_used, int $budget ): array {
		$cap_foot = $budget > 0
			? sprintf(
				/* translators: %s: formatted token budget. */
				__( 'of the %s token cap', 'ai-site-assistant-autopilot' ),
				number_format_i18n( $budget )
			)
			: __( 'no cap set', 'ai-site-assistant-autopilot' );

		if ( $first_day ) {
			return array(
				array(
					'id'    => 'questions',
					'label' => __( 'Questions answered', 'ai-site-assistant-autopilot' ),
					'value' => '0',
					'foot'  => __( 'Nothing asked yet', 'ai-site-assistant-autopilot' ),
				),
				array(
					'id'    => 'drafts-written',
					'label' => __( 'Drafts written', 'ai-site-assistant-autopilot' ),
					'value' => '0',
					'foot'  => __( 'Nothing written yet', 'ai-site-assistant-autopilot' ),
				),
				array(
					'id'    => 'drafts-created',
					'label' => __( 'Changes applied', 'ai-site-assistant-autopilot' ),
					'value' => '0',
					'foot'  => __( 'Nothing changed yet', 'ai-site-assistant-autopilot' ),
				),
				array(
					'id'    => 'tokens',
					'label' => __( 'Tokens used', 'ai-site-assistant-autopilot' ),
					'value' => '0',
					'foot'  => $cap_foot,
				),
			);
		}

		return array(
			array(
				'id'    => 'questions',
				'label' => __( 'Questions answered', 'ai-site-assistant-autopilot' ),
				'value' => number_format_i18n( $questions_answered ),
				'foot'  => __( 'Recorded Answer Desk questions', 'ai-site-assistant-autopilot' ),
			),
			array(
				'id'    => 'drafts-written',
				'label' => __( 'Drafts written', 'ai-site-assistant-autopilot' ),
				'value' => number_format_i18n( $drafts + $published ),
				'foot'  => __( 'Content Studio and Store Copilot', 'ai-site-assistant-autopilot' ),
			),
			array(
				'id'    => 'drafts-created',
				'label' => __( 'Drafts created', 'ai-site-assistant-autopilot' ),
				'value' => number_format_i18n( $waiting ),
				'foot'  => $waiting > 0
					? sprintf(
						/* translators: %s: number of drafts waiting for review. */
						__( '%s still waiting for you', 'ai-site-assistant-autopilot' ),
						number_format_i18n( $waiting )
					)
					: __( 'Nothing is waiting', 'ai-site-assistant-autopilot' ),
			),
			array(
				'id'    => 'tokens',
				'label' => __( 'Tokens used', 'ai-site-assistant-autopilot' ),
				'value' => number_format_i18n( $tokens_used ),
				'foot'  => $cap_foot,
			),
		);
	}


	/**
	 * Derived "Needs you" cards. Each card maps to a real condition and a real
	 * destination; when the condition is fixed the card disappears on reload.
	 *
	 * @return array<int, array{title:string,body:string,cta:string,url:string,icon:string}>
	 */
	private function needs_you( bool $first_day, bool $provider_ready, int $kb_total, int $gaps, int $waiting, int $budget, float $used_percent, bool $efb_active, bool $efb_available, int $undelivered = 0 ): array {
		$items = array();

		// First: visitors' answers that never reached anyone.
		if ( $undelivered > 0 ) {
			$items[] = array(
				'title' => sprintf(
					/* translators: %s: number of form submissions whose email failed. */
					_n( '%s form submission could not be emailed', '%s form submissions could not be emailed', $undelivered, 'ai-site-assistant-autopilot' ),
					number_format_i18n( $undelivered )
				),
				'body'  => __( 'The answers are kept on this site. Check the site\'s email setup, then resend them.', 'ai-site-assistant-autopilot' ),
				'cta'   => __( 'Open Your forms', 'ai-site-assistant-autopilot' ),
				'url'   => admin_url( 'admin.php?page=' . AdminMenu::SLUG_FORMS ),
				'icon'  => 'mail',
			);
		}

		if ( ! $provider_ready ) {
			$items[] = array(
				'title' => __( 'Connect your AI service', 'ai-site-assistant-autopilot' ),
				'body'  => __( 'Add an API key before any plan can be generated. Both key options keep the key on your own server.', 'ai-site-assistant-autopilot' ),
				'cta'   => __( 'Open Setup', 'ai-site-assistant-autopilot' ),
				'url'   => admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETUP ),
				'icon'  => 'plug',
			);
		}

		if ( $provider_ready && 0 === $kb_total ) {
			$items[] = array(
				'title' => __( 'Read your site first', 'ai-site-assistant-autopilot' ),
				'body'  => __( 'Site Knowledge has not run a full pass yet. Until it does, answers come only from the page a visitor is on.', 'ai-site-assistant-autopilot' ),
				'cta'   => __( 'Start the first pass', 'ai-site-assistant-autopilot' ),
				'url'   => admin_url( 'admin.php?page=' . AdminMenu::SLUG_KB ),
				'icon'  => 'book',
			);
		}

		if ( $first_day && $provider_ready ) {
			$items[] = array(
				'title' => __( 'Try a question yourself', 'ai-site-assistant-autopilot' ),
				'body'  => __( 'Ask Answer Desk something a customer would ask. You can inspect the answer and the source it used.', 'ai-site-assistant-autopilot' ),
				'cta'   => __( 'Open Answer Desk', 'ai-site-assistant-autopilot' ),
				'url'   => admin_url( 'admin.php?page=' . AdminMenu::SLUG_CONVERSATIONS ),
				'icon'  => 'chat',
			);
		}

		if ( $waiting > 0 ) {
			$items[] = array(
				'title' => sprintf(
					/* translators: %s: number of drafts waiting for review. */
					_n( '%s draft is waiting for you', '%s drafts are waiting for you', $waiting, 'ai-site-assistant-autopilot' ),
					number_format_i18n( $waiting )
				),
				'body'  => __( 'They stay drafts until you approve them. Nothing here is live on your site.', 'ai-site-assistant-autopilot' ),
				'cta'   => __( 'Review them', 'ai-site-assistant-autopilot' ),
				'url'   => admin_url( 'admin.php?page=' . AdminMenu::SLUG_LOGS ),
				'icon'  => 'doc',
			);
		}

		if ( $gaps > 0 ) {
			$items[] = array(
				'title' => sprintf(
					/* translators: %s: number of unanswered visitor questions. */
					_n( 'Visitors asked %s thing your site does not answer', 'Visitors asked %s things your site does not answer', $gaps, 'ai-site-assistant-autopilot' ),
					number_format_i18n( $gaps )
				),
				'body'  => __( 'Add the missing content and Answer Desk picks it up automatically.', 'ai-site-assistant-autopilot' ),
				'cta'   => __( 'See the questions', 'ai-site-assistant-autopilot' ),
				'url'   => admin_url( 'admin.php?page=' . AdminMenu::SLUG_KB ),
				'icon'  => 'chat',
			);
		}

		if ( $budget > 0 && $used_percent >= 75.0 && $used_percent < 100.0 ) {
			$items[] = array(
				'title' => __( 'You are close to your monthly cap', 'ai-site-assistant-autopilot' ),
				'body'  => sprintf(
					/* translators: %s: percent of the budget used. */
					__( '%s%% of the token budget is used this month. At 100%% text generation and embeddings pause until the next monthly period.', 'ai-site-assistant-autopilot' ),
					number_format_i18n( round( $used_percent ) )
				),
				'cta'   => __( 'Review the cap', 'ai-site-assistant-autopilot' ),
				'url'   => admin_url( 'admin.php?page=' . AdminMenu::SLUG_USAGE ),
				'icon'  => 'money',
			);
		}

		if ( $efb_active && ! $efb_available ) {
			$items[] = array(
				'title' => __( 'Easy Form Builder Copilot is idle', 'ai-site-assistant-autopilot' ),
				'body'  => __( 'The plugin is installed but not compatible right now, so the builder has no AI panel.', 'ai-site-assistant-autopilot' ),
				'cta'   => __( 'Review it', 'ai-site-assistant-autopilot' ),
				'url'   => admin_url( 'admin.php?page=' . AdminMenu::SLUG_INTEGRATIONS ),
				'icon'  => 'form',
			);
		}

		return $items;
	}

	/**
	 * Live integration states — the mockup's "Running now" column.
	 *
	 * @param array<string, mixed> $settings
	 * @return array<int, array{id:string,name:string,state:string,on:bool,icon:string}>
	 */
	private function running_now( array $settings ): array {
		// Same predicates as the Integrations cards, so both screens always agree.
		$woo        = (array) ( $settings['woocommerce'] ?? array() );
		$woo_on     = WooCommerceConnector::is_active() && ! empty( $woo['enabled'] );
		$efb_on     = ( new EasyFormBuilderConnector() )->is_available() && ! empty( $settings['efb_copilot_enabled'] );
		$answer_on  = ! empty( $settings['public_assistant_enabled'] );
		$form_on    = ! empty( $settings['form_composer_enabled'] );
		$content_on = ! empty( $settings['content_studio_enabled'] );

		return array(
			array(
				'id'    => 'knowledge',
				'name'  => __( 'Site Knowledge', 'ai-site-assistant-autopilot' ),
				'state' => __( 'Always on', 'ai-site-assistant-autopilot' ),
				'on'    => true,
				'icon'  => 'book-open',
			),
			array(
				'id'    => 'answer-desk',
				'name'  => __( 'Answer Desk', 'ai-site-assistant-autopilot' ),
				'state' => $answer_on ? __( 'On', 'ai-site-assistant-autopilot' ) : __( 'Off', 'ai-site-assistant-autopilot' ),
				'on'    => $answer_on,
				'icon'  => 'bubble',
			),
			array(
				'id'    => 'store-copilot',
				'name'  => __( 'Store Copilot', 'ai-site-assistant-autopilot' ),
				'state' => $woo_on ? __( 'On', 'ai-site-assistant-autopilot' ) : __( 'Off', 'ai-site-assistant-autopilot' ),
				'on'    => $woo_on,
				'icon'  => 'cart',
			),
			array(
				'id'    => 'form-composer',
				'name'  => __( 'Form Studio', 'ai-site-assistant-autopilot' ),
				'state' => $form_on ? __( 'On', 'ai-site-assistant-autopilot' ) : __( 'Off', 'ai-site-assistant-autopilot' ),
				'on'    => $form_on,
				'icon'  => 'form-lines',
			),
			array(
				'id'    => 'easy-form-builder',
				'name'  => __( 'Easy Form Builder Copilot', 'ai-site-assistant-autopilot' ),
				'state' => $efb_on ? __( 'On', 'ai-site-assistant-autopilot' ) : __( 'Off', 'ai-site-assistant-autopilot' ),
				'on'    => $efb_on,
				'icon'  => 'layers',
			),
			array(
				'id'    => 'content-studio',
				'name'  => __( 'Content Studio', 'ai-site-assistant-autopilot' ),
				'state' => $content_on ? __( 'On', 'ai-site-assistant-autopilot' ) : __( 'Off', 'ai-site-assistant-autopilot' ),
				'on'    => $content_on,
				'icon'  => 'pencil',
			),
		);
	}

	/** Count logged visitor questions; zero when the opt-in table does not exist. */
	private function answered_question_count(): int {
		global $wpdb;
		$store  = new VisitorConversationStore();
		$table  = $store->table();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- schema probe only.
		if ( $table !== $exists ) {
			return 0;
		}
		$count = $wpdb->get_var( "SELECT COALESCE(SUM(FLOOR(message_count / 2)), 0) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix plus a class constant, never user input; line 387 already proved it exists via a prepared probe.
		return (int) $count;
	}

	/** Count drafts and published items that the plugin itself marked as generated. */
	private function count_generated_posts_by_status( string $status ): int {
		$query = new \WP_Query(
			array(
				'post_type'              => 'any',
				'post_status'            => $status,
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded dashboard count over plugin-owned marker.
					array(
						'key'   => '_aisaautopilot_generated',
						'value' => '1',
					),
				),
			)
		);

		return max( 0, (int) $query->found_posts );
	}
}
