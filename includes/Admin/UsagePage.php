<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Usage\UsageTracker;

/**
 * Usage dashboard - token consumption and a monthly token cap.
 *
 * Every displayed number comes from UsageTracker's period and daily aggregates;
 * provider prices and feature-level billing are intentionally not inferred.
 */
final class UsagePage {

	public const SLUG = 'aisaautopilot-usage';

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'ai-site-assistant-autopilot' ) );
		}

		$report     = ( new UsageTracker() )->get_report();
		$totals     = is_array( $report['totals'] ?? null ) ? $report['totals'] : array();
		$budget     = max( 0, (int) ( $report['budget'] ?? 0 ) );
		$used       = max( 0, (int) ( $totals['total_tokens'] ?? 0 ) );
		$percent    = $budget > 0 ? min( 100.0, max( 0.0, (float) ( $report['used_percent'] ?? 0.0 ) ) ) : 0.0;
		$state      = $this->usage_state( $budget, $used, $percent );
		$days       = $this->chart_days( is_array( $report['last_7_days'] ?? null ) ? $report['last_7_days'] : array() );
		$operations = $this->operation_rows( is_array( $report['period_by_context'] ?? null ) ? $report['period_by_context'] : array(), $used );
		$max_day    = 0;
		foreach ( $days as $day ) {
			$max_day = max( $max_day, $day['tokens'] );
		}

		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-sp-usage data-sp-usage-state="<?php echo esc_attr( $state ); ?>" data-sp-usage-max-budget="<?php echo esc_attr( (string) UsageTracker::MAX_BUDGET ); ?>">
			<section class="sp-screen">
				<?php
				Ui::page_header(
					__( 'Usage & Cost', 'ai-site-assistant-autopilot' ),
					__( 'Token usage, and where it went.', 'ai-site-assistant-autopilot' ),
					array(
						'chip'   => ConnectionChip::derive(),
						'action' => array(
							'label' => __( 'Set a token cap', 'ai-site-assistant-autopilot' ),
							'type'  => 'button',
							'id'    => 'sp-usage-cap-open',
						),
					)
				);
				?>
				<div class="sp-pagebody">
					<div class="sp-usage__grid" data-sp-usage-grid>
						<section class="sp-card sp-card--solid sp-usage__card" data-sp-usage-card="cap" aria-labelledby="sp-usage-cap-title">
							<div class="sp-usage__summary">
								<div>
									<h2 class="sp-usage__total" id="sp-usage-cap-title" data-sp-usage-total><bdi><?php echo esc_html( $this->token_label( $used ) . ' ' . __( 'used', 'ai-site-assistant-autopilot' ) ); ?></bdi></h2>
									<p class="sp-usage__cap-note" data-sp-usage-cap-note><bdi><?php echo esc_html( $this->cap_note( $budget, $state ) ); ?></bdi></p>
								</div>
								<button type="button" class="sp-btn sp-btn--plain sp-usage__change" id="sp-usage-cap-change" data-sp-usage-cap-open>
									<?php esc_html_e( 'Change cap', 'ai-site-assistant-autopilot' ); ?>
								</button>
							</div>

							<div class="sp-usage__meter" data-sp-usage-meter data-sp-usage-state="<?php echo esc_attr( $state ); ?>" aria-label="<?php echo esc_attr( $this->meter_label( $budget, $percent, $state ) ); ?>">
								<span class="sp-usage__meter-fill" style="--sp-usage-meter-width:<?php echo esc_attr( (string) $percent ); ?>%"></span>
							</div>

							<?php if ( 'near' === $state || 'exhausted' === $state ) : ?>
								<p class="sp-usage__cap-alert sp-usage__cap-alert--<?php echo esc_attr( $state ); ?>">
									<?php
									echo esc_html(
										'exhausted' === $state
											? __( 'Requests covered by this token cap are paused until the next monthly period.', 'ai-site-assistant-autopilot' )
											: __( 'You are close to this month\'s token cap.', 'ai-site-assistant-autopilot' )
									);
									?>
								</p>
							<?php endif; ?>

							<div class="sp-usage__editor" id="sp-usage-cap-editor" data-sp-usage-cap-editor hidden>
								<label class="sp-field" for="sp-monthly-budget">
									<span class="sp-usage__editor-label"><?php esc_html_e( 'Monthly token cap', 'ai-site-assistant-autopilot' ); ?></span>
									<input type="number" id="sp-monthly-budget" class="sp-input sp-mono" min="0" max="<?php echo esc_attr( (string) UsageTracker::MAX_BUDGET ); ?>" step="1000" inputmode="numeric" value="<?php echo esc_attr( (string) $budget ); ?>" aria-describedby="sp-usage-cap-help" />
								</label>
								<p class="sp-usage__editor-help" id="sp-usage-cap-help"><?php esc_html_e( 'Set 0 for unlimited token usage. At the cap, only requests covered by this token cap pause; nothing is deleted.', 'ai-site-assistant-autopilot' ); ?></p>
								<div class="sp-usage__editor-actions">
									<button type="button" id="sp-save-budget" class="sp-btn sp-btn--r8" data-sp-usage-cap-save><?php esc_html_e( 'Save cap', 'ai-site-assistant-autopilot' ); ?></button>
									<button type="button" id="sp-usage-cap-cancel" class="sp-btn sp-btn--ghost sp-btn--r8" data-sp-usage-cap-cancel><?php esc_html_e( 'Cancel', 'ai-site-assistant-autopilot' ); ?></button>
								</div>
								<div class="sp-usage__reset-row">
									<button type="button" id="sp-reset-usage" class="sp-btn sp-btn--reject" data-sp-usage-reset><?php esc_html_e( 'Reset this month\'s usage', 'ai-site-assistant-autopilot' ); ?></button>
								</div>
								<span id="sp-budget-status" class="sp-status-text" data-sp-usage-status role="status" aria-live="polite"></span>
							</div>

							<div class="sp-usage__chart-section">
								<h3 class="sp-usage__eyebrow"><?php esc_html_e( 'Last 7 days', 'ai-site-assistant-autopilot' ); ?></h3>
								<div class="sp-usage__chart" data-sp-usage-chart>
									<?php foreach ( $days as $day ) : ?>
										<?php $height = $max_day > 0 && $day['tokens'] > 0 ? max( 4.0, ( $day['tokens'] / $max_day ) * 100 ) : 0.0; ?>
										<div class="sp-usage__day" data-sp-usage-day aria-label="<?php echo esc_attr( $this->day_label( $day ) ); ?>">
											<span class="sp-usage__day-bar" style="--sp-usage-day-height:<?php echo esc_attr( (string) round( $height, 2 ) ); ?>%"></span>
											<span class="sp-usage__day-label"><?php echo esc_html( $day['label'] ); ?></span>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						</section>

						<section class="sp-card sp-card--solid sp-usage__card" data-sp-usage-card="where-it-went" aria-labelledby="sp-usage-operations-title">
							<h2 class="sp-usage__operations-title" id="sp-usage-operations-title"><?php esc_html_e( 'Where it went', 'ai-site-assistant-autopilot' ); ?></h2>
							<?php if ( 0 === $used ) : ?>
								<p class="sp-usage__empty" data-sp-usage-empty><?php esc_html_e( 'No token usage has been recorded in this month.', 'ai-site-assistant-autopilot' ); ?></p>
							<?php endif; ?>
							<div class="sp-usage__operations" data-sp-usage-breakdown>
								<?php foreach ( $operations as $operation ) : ?>
									<div class="sp-usage__operation" data-sp-usage-operation data-sp-usage-operation-id="<?php echo esc_attr( $operation['key'] ); ?>">
										<div class="sp-usage__operation-head">
											<span class="sp-usage__operation-label"><?php echo esc_html( $operation['label'] ); ?></span>
											<span class="sp-usage__operation-amount"><?php echo esc_html( $operation['tokens'] > 0 ? $this->token_label( $operation['tokens'] ) : __( 'Not used yet', 'ai-site-assistant-autopilot' ) ); ?></span>
										</div>
										<div class="sp-usage__operation-meter" aria-label="<?php echo esc_attr( $this->operation_label( $operation ) ); ?>">
											<span class="sp-usage__operation-fill" style="--sp-usage-operation-width:<?php echo esc_attr( (string) $operation['percent'] ); ?>%"></span>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
							<p class="sp-usage__foot" data-sp-usage-foot><?php esc_html_e( 'Token totals are recorded locally on this site. Provider prices and billing are not available here.', 'ai-site-assistant-autopilot' ); ?></p>
						</section>
					</div>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
		Ui::json_script(
			'sp-usage-i18n',
			array(
				'saving'        => __( 'Saving...', 'ai-site-assistant-autopilot' ),
				'saved'         => __( 'Token cap saved.', 'ai-site-assistant-autopilot' ),
				'failed'        => __( 'Could not save the token cap.', 'ai-site-assistant-autopilot' ),
				'invalidBudget' => __( 'Enter a whole number of zero or more tokens.', 'ai-site-assistant-autopilot' ),
				'confirmWipe'   => __( 'Reset only this month\'s token usage? This cannot be undone.', 'ai-site-assistant-autopilot' ),
				'resetting'     => __( 'Resetting...', 'ai-site-assistant-autopilot' ),
				'resetDone'     => __( 'This month\'s usage was reset.', 'ai-site-assistant-autopilot' ),
			)
		);
	}

	private function usage_state( int $budget, int $used, float $percent ): string {
		if ( 0 === $used ) {
			return 'empty';
		}
		if ( $budget <= 0 ) {
			return 'unlimited';
		}
		if ( $percent >= 100.0 ) {
			return 'exhausted';
		}

		return $percent >= 75.0 ? 'near' : 'normal';
	}

	private function cap_note( int $budget, string $state ): string {
		if ( $budget <= 0 ) {
			return __( 'No token cap is set.', 'ai-site-assistant-autopilot' );
		}
		if ( 'exhausted' === $state ) {
			return __( 'This month\'s token cap has been reached.', 'ai-site-assistant-autopilot' );
		}

		return sprintf(
			/* translators: %s: monthly token cap. */
			__( 'of your %s-token cap this month', 'ai-site-assistant-autopilot' ),
			number_format_i18n( $budget )
		);
	}

	private function meter_label( int $budget, float $percent, string $state ): string {
		if ( $budget <= 0 ) {
			return __( 'No token cap is set.', 'ai-site-assistant-autopilot' );
		}
		if ( 'exhausted' === $state ) {
			return __( 'Monthly token cap reached.', 'ai-site-assistant-autopilot' );
		}

		return sprintf(
			/* translators: %s: percentage of the monthly token cap used. */
			__( '%s%% of the monthly token cap used.', 'ai-site-assistant-autopilot' ),
			number_format_i18n( $percent, 1 )
		);
	}

	/**
	 * @param array<mixed> $days Raw seven-day report data.
	 * @return array<int, array{label:string, tokens:int, requests:int}>
	 */
	private function chart_days( array $days ): array {
		$clean = array();
		foreach ( array_slice( $days, 0, 7 ) as $day ) {
			$day     = is_array( $day ) ? $day : array();
			$clean[] = array(
				'label'    => sanitize_text_field( (string) ( $day['label'] ?? '' ) ),
				'tokens'   => max( 0, (int) ( $day['tokens'] ?? 0 ) ),
				'requests' => max( 0, (int) ( $day['requests'] ?? 0 ) ),
			);
		}

		while ( count( $clean ) < 7 ) {
			$clean[] = array(
				'label'    => '-',
				'tokens'   => 0,
				'requests' => 0,
			);
		}

		return $clean;
	}

	/**
	 * @param array<mixed> $contexts Current-period operation aggregates.
	 * @return array<int, array{key:string, label:string, tokens:int, requests:int, percent:float}>
	 */
	private function operation_rows( array $contexts, int $used ): array {
		$labels = array(
			'text_generation'   => __( 'Text generation', 'ai-site-assistant-autopilot' ),
			'structured_output' => __( 'Structured output', 'ai-site-assistant-autopilot' ),
			'embedding'         => __( 'Embeddings', 'ai-site-assistant-autopilot' ),
		);
		$rows   = array();
		foreach ( $labels as $key => $label ) {
			$rows[ $key ] = array(
				'key'      => $key,
				'label'    => $label,
				'tokens'   => 0,
				'requests' => 0,
			);
		}
		$other = array(
			'key'      => 'other',
			'label'    => __( 'Other or unclassified records', 'ai-site-assistant-autopilot' ),
			'tokens'   => 0,
			'requests' => 0,
		);

		foreach ( $contexts as $context => $data ) {
			$key      = sanitize_key( (string) $context );
			$data     = is_array( $data ) ? $data : array();
			$tokens   = max( 0, (int) ( $data['tokens'] ?? 0 ) );
			$requests = max( 0, (int) ( $data['requests'] ?? 0 ) );
			if ( isset( $rows[ $key ] ) ) {
				$rows[ $key ]['tokens']   += $tokens;
				$rows[ $key ]['requests'] += $requests;
				continue;
			}

			$other['tokens']   += $tokens;
			$other['requests'] += $requests;
		}

		$known_tokens = 0;
		foreach ( $rows as $row ) {
			$known_tokens += $row['tokens'];
		}
		$other['tokens'] += max( 0, $used - $known_tokens - $other['tokens'] );

		$operations      = array_values( $rows );
		$operations[]    = $other;
		$operation_total = 0;
		foreach ( $operations as $operation ) {
			$operation_total += $operation['tokens'];
		}
		$denominator = max( $used, $operation_total );

		foreach ( $operations as $index => $operation ) {
			$operations[ $index ]['percent'] = $denominator > 0 ? round( ( $operation['tokens'] / $denominator ) * 100, 2 ) : 0.0;
		}

		return $operations;
	}

	/**
	 * @param array{label:string, tokens:int, requests:int} $day One chart day.
	 */
	private function day_label( array $day ): string {
		return sprintf(
			/* translators: 1: day label, 2: tokens, 3: requests. */
			__( '%1$s: %2$s in %3$s requests.', 'ai-site-assistant-autopilot' ),
			$day['label'],
			$this->token_label( $day['tokens'] ),
			number_format_i18n( $day['requests'] )
		);
	}

	/**
	 * @param array{key:string, label:string, tokens:int, requests:int, percent:float} $operation One operation row.
	 */
	private function operation_label( array $operation ): string {
		return sprintf(
			/* translators: 1: operation name, 2: tokens, 3: requests. */
			__( '%1$s: %2$s in %3$s requests.', 'ai-site-assistant-autopilot' ),
			$operation['label'],
			$this->token_label( $operation['tokens'] ),
			number_format_i18n( $operation['requests'] )
		);
	}

	private function token_label( int $tokens ): string {
		return sprintf(
			/* translators: %s: token count. */
			_n( '%s token', '%s tokens', $tokens, 'ai-site-assistant-autopilot' ),
			number_format_i18n( $tokens )
		);
	}
}
