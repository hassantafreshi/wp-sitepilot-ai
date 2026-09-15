<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Logging\Logger;

/**
 * "Edit in EFB" — the AISAAutoPilot side of the Easy Form Builder integration.
 *
 * AISAAutoPilot proposes operations from inside EFB's own editor; this screen shows
 * what is allowed, what the last proposal did, and how to get back into EFB.
 * Advanced form features stay owned by EFB itself.
 */
final class EasyFormBuilderPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$connector = new EasyFormBuilderConnector();
		$health    = $connector->get_health();
		$ready     = ! empty( $health['available'] );
		$writable  = $this->ai_writable_count();
		$last      = $this->last_apply();

		$home_url   = admin_url( 'admin.php?page=' . AdminMenu::SLUG_HOME );
		$list_url   = admin_url( 'admin.php?page=Emsfb' );
		$create_url = admin_url( 'admin.php?page=Emsfb_create' );
		$logs_url   = admin_url( 'admin.php?page=' . AdminMenu::SLUG_LOGS );

		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-sp-efb>
			<section class="sp-screen">
				<?php
				Ui::crumbs(
					array(
						array( __( 'Home', 'ai-site-assistant-autopilot' ), $home_url ),
						array( 'Easy Form Builder', null ),
						array( __( 'Proposed changes', 'ai-site-assistant-autopilot' ), null ),
					)
				);
				?>

				<div class="sp-page-head">
					<div>
						<h1 class="sp-h1"><span><?php esc_html_e( 'Changes to the open form', 'ai-site-assistant-autopilot' ); ?></span></h1>
						<div class="sp-meta-row">
							<span style="font-weight:600;color:var(--sp-ink)"><?php esc_html_e( 'Easy Form Builder editor', 'ai-site-assistant-autopilot' ); ?></span>
							<?php if ( '' !== $last['trace'] ) : ?>
								<span class="sp-mono"><?php echo esc_html( $last['trace'] ); ?></span>
							<?php endif; ?>
						</div>
					</div>
					<span class="sp-pill <?php echo $ready ? 'sp-pill--info sp-pill--outline' : 'sp-pill--off'; ?>" style="padding:8px 13px;font-size:13px">
						<?php echo esc_html( $ready ? __( 'Waiting to apply', 'ai-site-assistant-autopilot' ) : __( 'Connector unavailable', 'ai-site-assistant-autopilot' ) ); ?>
					</span>
				</div>

				<div class="sp-strip <?php echo $ready ? '' : 'sp-strip--warn'; ?>">
					<?php echo Ui::icon( 'shield', 18, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
					<span>
						<?php
						echo esc_html(
							$ready
								? __( 'Nothing has changed in the editor yet. A snapshot of the current state is taken before anything is applied, and nothing is written to the database until you press Save in Easy Form Builder.', 'ai-site-assistant-autopilot' )
								: __( 'Easy Form Builder is not active, or its version does not match the supported 4.1+ storage contract. AISAAutoPilot Forms remains available for simple forms.', 'ai-site-assistant-autopilot' )
						);
						?>
					</span>
				</div>

				<div class="sp-cols">
					<div class="sp-col sp-col--540">

						<div class="sp-card sp-card--bare sp-card--flat">
							<div class="sp-card__head sp-card__head--split">
								<span style="font-size:14px;font-weight:700"><?php esc_html_e( 'Form in the editor', 'ai-site-assistant-autopilot' ); ?></span>
								<span class="sp-help"><?php echo esc_html( $last['summary'] ); ?></span>
							</div>
							<div class="sp-card__body sp-stack">
								<?php if ( $ready ) : ?>
									<div class="sp-diff">
										<span class="sp-diff__type">add_field</span>
										<span class="sp-diff__name"><?php esc_html_e( 'Add a field of any allowed type', 'ai-site-assistant-autopilot' ); ?></span>
										<span class="sp-diff__tag sp-tag--ok"><?php esc_html_e( 'allowed', 'ai-site-assistant-autopilot' ); ?></span>
									</div>
									<div class="sp-diff">
										<span class="sp-diff__type">add_option</span>
										<span class="sp-diff__name"><?php esc_html_e( 'Add a choice to a select, radio or checkbox', 'ai-site-assistant-autopilot' ); ?></span>
										<span class="sp-diff__tag sp-tag--ok"><?php esc_html_e( 'allowed', 'ai-site-assistant-autopilot' ); ?></span>
									</div>
									<div class="sp-diff">
										<span class="sp-diff__type">set_field_property</span>
										<span class="sp-diff__name"><?php esc_html_e( 'Change a field property marked ai_writable', 'ai-site-assistant-autopilot' ); ?></span>
										<span class="sp-diff__tag sp-tag--warn"><?php esc_html_e( 'validated', 'ai-site-assistant-autopilot' ); ?></span>
									</div>
									<div class="sp-diff">
										<span class="sp-diff__type">remove_field</span>
										<span class="sp-diff__name"><?php esc_html_e( 'Remove a field and its stored settings', 'ai-site-assistant-autopilot' ); ?></span>
										<span class="sp-diff__tag sp-tag--warn"><?php esc_html_e( 'validated', 'ai-site-assistant-autopilot' ); ?></span>
									</div>
									<div class="sp-diff sp-diff--del">
										<span class="sp-diff__type">set_form_property</span>
										<span class="sp-diff__name"><?php esc_html_e( 'Payment, authentication and SMTP recipients', 'ai-site-assistant-autopilot' ); ?></span>
										<span class="sp-diff__tag sp-tag--err"><?php esc_html_e( 'never', 'ai-site-assistant-autopilot' ); ?></span>
									</div>
								<?php else : ?>
									<p class="sp-empty"><?php esc_html_e( 'No editor session is available while the connector is inactive.', 'ai-site-assistant-autopilot' ); ?></p>
								<?php endif; ?>
							</div>
						</div>

						<div class="sp-card sp-card--18">
							<h2 class="sp-h2 sp-h2--sm"><?php esc_html_e( 'Operations', 'ai-site-assistant-autopilot' ); ?></h2>
							<p class="sp-sub sp-sub--sm"><?php esc_html_e( 'Exactly what the AI may ask for, after server validation.', 'ai-site-assistant-autopilot' ); ?></p>
							<div class="sp-stack">
								<div class="sp-op">
									<div class="sp-op__head">
										<span class="sp-op__code">add_field</span>
										<span class="sp-op__name"><?php esc_html_e( 'Insert a new field', 'ai-site-assistant-autopilot' ); ?></span>
									</div>
									<div class="sp-op__note"><?php esc_html_e( 'Type must be in the allowed list; the position is validated against the current structure.', 'ai-site-assistant-autopilot' ); ?></div>
								</div>
								<div class="sp-op">
									<div class="sp-op__head">
										<span class="sp-op__code">set_field_property</span>
										<span class="sp-op__name"><?php esc_html_e( 'Change one property', 'ai-site-assistant-autopilot' ); ?></span>
									</div>
									<div class="sp-row-wrap sp-row-wrap--sm" style="font-size:12.5px">
										<span class="sp-mono sp-help">required</span>
										<span class="sp-op__was">false</span>
										<span style="color:var(--sp-faint)">&rarr;</span>
										<span class="sp-op__now">true</span>
									</div>
								</div>
								<div class="sp-op sp-op--err">
									<div class="sp-op__head">
										<span class="sp-op__code">set_form_property</span>
										<span class="sp-op__name" style="color:var(--sp-err-ink)"><?php esc_html_e( 'Rejected before apply', 'ai-site-assistant-autopilot' ); ?></span>
									</div>
									<div class="sp-op__note"><?php esc_html_e( 'Anything not marked ai_writable in the capability catalog is refused by the server. Change those in the Easy Form Builder UI.', 'ai-site-assistant-autopilot' ); ?></div>
								</div>
							</div>
						</div>
					</div>

					<aside class="sp-side sp-side--320">
						<div class="sp-card sp-card--tight sp-card--sticky" style="display:flex;flex-direction:column;gap:12px">
							<div>
								<div class="sp-h3" style="margin-bottom:9px"><?php esc_html_e( 'Connector', 'ai-site-assistant-autopilot' ); ?></div>
								<div class="sp-kv sp-kv--sm">
									<?php
									Ui::kv( __( 'Status', 'ai-site-assistant-autopilot' ), $ready ? __( 'Available', 'ai-site-assistant-autopilot' ) : __( 'Unavailable', 'ai-site-assistant-autopilot' ) );
									Ui::kv( __( 'Detected version', 'ai-site-assistant-autopilot' ), (string) ( $health['version'] ?: '—' ) );
									Ui::kv(
										__( 'Writable capabilities', 'ai-site-assistant-autopilot' ),
										sprintf(
											/* translators: %s: number of ai_writable catalog entries. */
											__( '%s ai_writable', 'ai-site-assistant-autopilot' ),
											number_format_i18n( $writable )
										)
									);
									Ui::kv( __( 'Applies to', 'ai-site-assistant-autopilot' ), __( 'Editor state only', 'ai-site-assistant-autopilot' ) );
									?>
								</div>
							</div>
							<div class="sp-hr"></div>
							<?php if ( $ready ) : ?>
								<a class="sp-btn sp-btn--lg sp-btn--block" href="<?php echo esc_url( $create_url ); ?>"><?php esc_html_e( 'Open the EFB editor', 'ai-site-assistant-autopilot' ); ?></a>
								<a class="sp-btn sp-btn--ghost sp-btn--block" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Open EFB forms', 'ai-site-assistant-autopilot' ); ?></a>
							<?php else : ?>
								<a class="sp-btn sp-btn--lg sp-btn--block" href="<?php echo esc_url( admin_url( 'plugin-install.php?s=easy+form+builder&tab=search&type=term' ) ); ?>"><?php esc_html_e( 'Install Easy Form Builder', 'ai-site-assistant-autopilot' ); ?></a>
							<?php endif; ?>
							<p class="sp-decide__note"><?php esc_html_e( 'Nothing is saved until you press Save in Easy Form Builder.', 'ai-site-assistant-autopilot' ); ?></p>
						</div>

						<div class="sp-card sp-card--accent">
							<div class="sp-row-wrap sp-row-wrap--sm" style="margin-bottom:6px">
								<?php echo Ui::icon( 'shield', 16, '#202a8d' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
								<span style="font-size:13.5px;font-weight:700"><?php esc_html_e( 'What the AI may change', 'ai-site-assistant-autopilot' ); ?></span>
							</div>
							<p class="sp-help sp-help--12"><?php esc_html_e( 'Only entries marked ai_writable in the capability catalog. Payment, authentication, conditional logic, SMTP recipients and raw HTML always need the Easy Form Builder UI.', 'ai-site-assistant-autopilot' ); ?></p>
						</div>

						<div class="sp-card sp-card--16 sp-card--flat">
							<div class="sp-h3" style="font-size:13.5px;margin-bottom:10px"><?php esc_html_e( 'Last apply', 'ai-site-assistant-autopilot' ); ?></div>
							<div class="sp-mono sp-help"><?php echo esc_html( $last['when'] ); ?></div>
							<a class="sp-btn sp-btn--ghost sp-btn--block" style="margin-top:12px;padding:9px" href="<?php echo esc_url( $logs_url ); ?>"><?php esc_html_e( 'Open activity log', 'ai-site-assistant-autopilot' ); ?></a>
						</div>
					</aside>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
	}

	/** How many catalog entries the AI is allowed to write. */
	private function ai_writable_count(): int {
		$file = AISAAUTOPILOT_DIR . 'includes/Efb/efb-capability-catalog.json';
		if ( ! is_readable( $file ) ) {
			return 0;
		}
		$raw = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- bundled plugin asset, not a remote request.

		return substr_count( $raw, '"ai_writable": true' ) + substr_count( $raw, '"ai_writable":true' );
	}

	/**
	 * The most recent EFB apply recorded in the log.
	 *
	 * @return array{when:string,trace:string,summary:string}
	 */
	private function last_apply(): array {
		foreach ( ( new Logger() )->recent( 60 ) as $entry ) {
			if ( 0 !== strpos( (string) ( $entry['event'] ?? '' ), 'efb.apply' ) ) {
				continue;
			}
			$context = is_array( $entry['context'] ?? null ) ? $entry['context'] : array();

			return array(
				'when'    => str_replace( array( 'T', '+00:00' ), array( ' ', ' UTC' ), (string) ( $entry['time'] ?? '' ) ),
				'trace'   => (string) ( $context['trace_id'] ?? '' ),
				'summary' => (string) ( $entry['message'] ?? '' ),
			);
		}

		return array(
			'when'    => __( 'Not applied yet', 'ai-site-assistant-autopilot' ),
			'trace'   => '',
			'summary' => __( 'Proposals are made from inside the EFB editor', 'ai-site-assistant-autopilot' ),
		);
	}
}
