<?php
declare(strict_types=1);

namespace SitePilot\Usage;

/**
 * Tracks AI token usage locally and provides credit-threshold reporting.
 *
 * Storage: a single wp_option ring buffer (like Logger) plus a cumulative
 * counter option. No custom table needed for MVP.
 *
 * Thresholds: 50%, 25%, 15%, 5% — the admin UI shows a warning banner
 * when the remaining budget crosses each level.
 */
final class UsageTracker {

	private const OPTION_LOG     = 'sitepilot_ai_usage_log';
	private const OPTION_TOTALS  = 'sitepilot_ai_usage_totals';
	private const OPTION_BUDGET  = 'sitepilot_ai_monthly_budget';
	private const MAX_LOG        = 500;

	/** Threshold percentages that trigger warnings. */
	public const THRESHOLDS = array( 50, 25, 15, 5 );

	/**
	 * Record a single AI API call's token usage.
	 *
	 * @param string $model      Model identifier.
	 * @param int    $prompt     Prompt (input) tokens.
	 * @param int    $completion Completion (output) tokens.
	 * @param string $context    Where the call originated (e.g. "kb_index", "chat").
	 */
	public function record( string $model, int $prompt, int $completion, string $context = '' ): void {
		$total = $prompt + $completion;

		// Append to ring buffer log.
		$log = get_option( self::OPTION_LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$log[] = array(
			'time'       => gmdate( 'c' ),
			'model'      => sanitize_text_field( $model ),
			'prompt'     => $prompt,
			'completion' => $completion,
			'total'      => $total,
			'context'    => sanitize_text_field( $context ),
			'user_id'    => get_current_user_id(),
		);

		if ( count( $log ) > self::MAX_LOG ) {
			$log = array_slice( $log, -self::MAX_LOG );
		}

		update_option( self::OPTION_LOG, $log, false );

		// Update cumulative totals.
		$totals = $this->get_totals();
		$totals['prompt_tokens']     += $prompt;
		$totals['completion_tokens'] += $completion;
		$totals['total_tokens']      += $total;
		$totals['request_count']     += 1;
		$totals['last_used']          = gmdate( 'c' );

		update_option( self::OPTION_TOTALS, $totals, false );
	}

	/**
	 * Get cumulative usage totals.
	 *
	 * @return array{prompt_tokens:int, completion_tokens:int, total_tokens:int, request_count:int, last_used:string}
	 */
	public function get_totals(): array {
		$totals = get_option( self::OPTION_TOTALS, array() );
		if ( ! is_array( $totals ) ) {
			$totals = array();
		}

		return array_merge( array(
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
			'total_tokens'      => 0,
			'request_count'     => 0,
			'last_used'         => '',
		), $totals );
	}

	/**
	 * Get the monthly token budget (0 = unlimited).
	 */
	public function get_budget(): int {
		return absint( get_option( self::OPTION_BUDGET, 0 ) );
	}

	/**
	 * Set the monthly token budget.
	 */
	public function set_budget( int $tokens ): void {
		update_option( self::OPTION_BUDGET, max( 0, $tokens ), false );
	}

	/**
	 * Get the current usage percentage against the budget.
	 * Returns 0 if no budget is set (unlimited).
	 */
	public function get_usage_percent(): float {
		$budget = $this->get_budget();
		if ( $budget <= 0 ) {
			return 0.0;
		}

		$totals  = $this->get_totals();
		$percent = ( $totals['total_tokens'] / $budget ) * 100;

		return min( 100.0, round( $percent, 1 ) );
	}

	/**
	 * Get remaining tokens.
	 * Returns -1 if no budget is set (unlimited).
	 */
	public function get_remaining(): int {
		$budget = $this->get_budget();
		if ( $budget <= 0 ) {
			return -1;
		}

		$totals = $this->get_totals();

		return max( 0, $budget - $totals['total_tokens'] );
	}

	/**
	 * Determine which threshold warning (if any) should be shown.
	 *
	 * @return array{level:string, percent:float, remaining:int, message:string}|null
	 */
	public function get_threshold_warning(): ?array {
		$budget = $this->get_budget();
		if ( $budget <= 0 ) {
			return null;
		}

		$used_percent = $this->get_usage_percent();
		$remaining    = $this->get_remaining();

		// Find the highest threshold we've crossed.
		foreach ( self::THRESHOLDS as $threshold ) {
			$remaining_percent = 100 - $used_percent;
			if ( $remaining_percent <= $threshold ) {
				$level = $threshold <= 5 ? 'critical' : ( $threshold <= 15 ? 'danger' : ( $threshold <= 25 ? 'warning' : 'info' ) );

				return array(
					'level'     => $level,
					'percent'   => $used_percent,
					'remaining' => $remaining,
					'message'   => sprintf(
						/* translators: 1: remaining percentage, 2: remaining tokens. */
						__( 'AI credit alert: Only %1$d%% remaining (%2$s tokens left).', 'sitepilot-ai' ),
						(int) round( 100 - $used_percent ),
						number_format_i18n( $remaining )
					),
				);
			}
		}

		return null;
	}

	/**
	 * Get a full usage report for the admin dashboard.
	 *
	 * @return array<string, mixed>
	 */
	public function get_report(): array {
		$totals  = $this->get_totals();
		$budget  = $this->get_budget();
		$warning = $this->get_threshold_warning();

		// Group recent usage by context.
		$log      = get_option( self::OPTION_LOG, array() );
		$by_context = array();
		if ( is_array( $log ) ) {
			foreach ( $log as $entry ) {
				$ctx = $entry['context'] ?: 'general';
				if ( ! isset( $by_context[ $ctx ] ) ) {
					$by_context[ $ctx ] = array( 'tokens' => 0, 'requests' => 0 );
				}
				$by_context[ $ctx ]['tokens']    += (int) $entry['total'];
				$by_context[ $ctx ]['requests']  += 1;
			}
		}

		// Group by model.
		$by_model = array();
		if ( is_array( $log ) ) {
			foreach ( $log as $entry ) {
				$model = $entry['model'] ?: 'unknown';
				if ( ! isset( $by_model[ $model ] ) ) {
					$by_model[ $model ] = array( 'tokens' => 0, 'requests' => 0 );
				}
				$by_model[ $model ]['tokens']   += (int) $entry['total'];
				$by_model[ $model ]['requests'] += 1;
			}
		}

		return array(
			'totals'        => $totals,
			'budget'        => $budget,
			'used_percent'  => $this->get_usage_percent(),
			'remaining'     => $this->get_remaining(),
			'warning'       => $warning,
			'by_context'    => $by_context,
			'by_model'      => $by_model,
			'recent'        => is_array( $log ) ? array_slice( array_reverse( $log ), 0, 20 ) : array(),
		);
	}

	/**
	 * Reset all usage data (admin action).
	 */
	public function reset(): void {
		delete_option( self::OPTION_LOG );
		delete_option( self::OPTION_TOTALS );
	}

	/**
	 * Reset only the monthly counter (called by WP-Cron at month start).
	 */
	public function reset_monthly(): void {
		$totals = $this->get_totals();
		$totals['prompt_tokens']     = 0;
		$totals['completion_tokens'] = 0;
		$totals['total_tokens']      = 0;
		$totals['request_count']     = 0;
		$totals['last_used']         = '';

		update_option( self::OPTION_TOTALS, $totals, false );
		delete_option( self::OPTION_LOG );
	}
}
