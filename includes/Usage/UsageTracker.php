<?php
declare(strict_types=1);

namespace AISAAutoPilot\Usage;

/**
 * Tracks AI token usage locally and provides credit-threshold reporting.
 *
 * Storage: a single wp_option ring buffer (like Logger) plus a current-month
 * counter option. No custom table needed for MVP.
 *
 * Thresholds: 50%, 25%, 15%, 5% — the admin UI shows a warning banner
 * when the remaining budget crosses each level.
 */
final class UsageTracker {

	/** Largest token cap accepted by the local budget control. */
	public const MAX_BUDGET = 1000000000;

	private const OPTION_LOG             = 'aisaautopilot_usage_log';
	private const OPTION_TOTALS          = 'aisaautopilot_usage_totals';
	private const OPTION_BUDGET          = 'aisaautopilot_monthly_budget';
	private const OPTION_PERIOD          = 'aisaautopilot_usage_period';
	private const OPTION_PERIOD_CONTEXTS = 'aisaautopilot_usage_period_contexts';
	private const OPTION_DAILY           = 'aisaautopilot_usage_daily';
	private const OPTION_LEGACY_TOTALS   = 'aisaautopilot_usage_historical_totals';
	private const OPTION_LEGACY_LOG      = 'aisaautopilot_usage_historical_log';
	private const OPTION_ARCHIVE         = 'aisaautopilot_usage_period_archive';
	private const MAX_LOG                = 500;
	private const MAX_ARCHIVE            = 12;

	/**
	 * An optional fixed clock keeps date-boundary behaviour testable without
	 * relying on WP-Cron or the server timezone.
	 *
	 * @var \DateTimeImmutable|null
	 */
	private $clock;

	/** Threshold percentages that trigger warnings. */
	public const THRESHOLDS = array( 50, 25, 15, 5 );

	/**
	 * @param \DateTimeImmutable|null $clock Fixed time for deterministic tests.
	 */
	public function __construct( ?\DateTimeImmutable $clock = null ) {
		$this->clock = $clock;
	}

	/**
	 * Record a single AI API call's token usage.
	 *
	 * @param string $model      Model identifier.
	 * @param int    $prompt     Prompt (input) tokens.
	 * @param int    $completion Completion (output) tokens.
	 * @param string $context    Where the call originated (e.g. "kb_index", "chat").
	 */
	public function record( string $model, int $prompt, int $completion, string $context = '' ): void {
		$this->ensure_current_period();

		$prompt     = max( 0, $prompt );
		$completion = max( 0, $completion );
		$total      = $prompt + $completion;
		$context    = sanitize_key( $context );
		$context    = '' !== $context ? $context : 'general';
		$contexts   = $this->period_contexts();
		$daily      = $this->daily_usage();

		// Append to ring buffer log.
		$log = get_option( self::OPTION_LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$log[] = array(
			'time'       => $this->utc_now()->format( 'c' ),
			'period'     => $this->current_period(),
			'model'      => sanitize_text_field( $model ),
			'prompt'     => $prompt,
			'completion' => $completion,
			'total'      => $total,
			'context'    => $context,
			'user_id'    => get_current_user_id(),
		);

		if ( count( $log ) > self::MAX_LOG ) {
			$log = array_slice( $log, -self::MAX_LOG );
		}

		update_option( self::OPTION_LOG, $log, false );

		// Update current-period totals.
		$totals                       = $this->get_totals();
		$totals['prompt_tokens']     += $prompt;
		$totals['completion_tokens'] += $completion;
		$totals['total_tokens']      += $total;
		$totals['request_count']     += 1;
		$totals['last_used']          = $this->utc_now()->format( 'c' );

		update_option( self::OPTION_TOTALS, $totals, false );
		$this->record_period_context( $contexts, $context, $total );
		$this->record_daily( $daily, $this->current_day(), $total );
	}

	/**
	 * Get current-period usage totals.
	 *
	 * @return array{prompt_tokens:int, completion_tokens:int, total_tokens:int, request_count:int, last_used:string}
	 */
	public function get_totals(): array {
		$this->ensure_current_period();

		return $this->normalise_totals( get_option( self::OPTION_TOTALS, array() ) );
	}

	/**
	 * Get the monthly token budget (0 = unlimited).
	 */
	public function get_budget(): int {
		// `absint()` would turn a corrupt negative stored value into a positive
		// cap. A negative value must instead retain the documented meaning of
		// zero: no local cap.
		return min( self::MAX_BUDGET, max( 0, (int) get_option( self::OPTION_BUDGET, 0 ) ) );
	}

	/**
	 * Set the monthly token budget.
	 */
	public function set_budget( int $tokens ): void {
		update_option( self::OPTION_BUDGET, min( self::MAX_BUDGET, max( 0, $tokens ) ), false );
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
	 * Whether a configured token budget is fully consumed.
	 *
	 * This deliberately compares raw token counts rather than the rounded
	 * display percentage, so a 99.99% display round-up cannot pause work early.
	 */
	public function is_exhausted(): bool {
		$budget = $this->get_budget();
		if ( $budget <= 0 ) {
			return false;
		}

		return $this->get_totals()['total_tokens'] >= $budget;
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

		// Find the most severe threshold crossed (smallest remaining %).
		$remaining_percent = 100 - $used_percent;
		$crossed           = null;
		foreach ( self::THRESHOLDS as $threshold ) {
			if ( $remaining_percent <= $threshold ) {
				$crossed = $threshold;
			}
		}

		if ( null === $crossed ) {
			return null;
		}

		$level = $crossed <= 5 ? 'critical' : ( $crossed <= 15 ? 'danger' : ( $crossed <= 25 ? 'warning' : 'info' ) );

		return array(
			'level'     => $level,
			'percent'   => $used_percent,
			'remaining' => $remaining,
			'message'   => sprintf(
				/* translators: 1: remaining percentage, 2: remaining tokens. */
				__( 'AI credit alert: Only %1$d%% remaining (%2$s tokens left).', 'ai-site-assistant-autopilot' ),
				(int) round( $remaining_percent ),
				number_format_i18n( $remaining )
			),
		);
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

		// Existing aggregate keys retain their retained-log behaviour. New
		// current-period keys let the UI report a truthful monthly split.
		$log        = $this->current_log();
		$by_context = $this->group_by_context( $log );
		$by_model   = $this->group_by_model( $log );
		$daily      = $this->daily_usage();

		return array(
			'totals'            => $totals,
			'budget'            => $budget,
			'period'            => $this->current_period(),
			'used_percent'      => $this->get_usage_percent(),
			'remaining'         => $this->get_remaining(),
			'warning'           => $warning,
			'by_context'        => $by_context,
			'by_model'          => $by_model,
			'period_by_context' => $this->period_contexts(),
			'last_7_days'       => $this->last_seven_days( $daily ),
			'historical_totals' => $this->historical_totals(),
			'recent'            => array_slice( array_reverse( $log ), 0, 20 ),
		);
	}

	/**
	 * Reset current-period usage (admin action).
	 */
	public function reset(): void {
		$this->ensure_current_period();
		$this->reset_current_period();
	}

	/**
	 * Reset only the current monthly counter.
	 */
	public function reset_monthly(): void {
		$this->ensure_current_period();
		$this->reset_current_period();
	}

	/**
	 * Make the monthly accounting period correct on every read and write.
	 * This deliberately does not depend on a scheduled task: a quiet site can
	 * still cross a month boundary before its next request.
	 */
	private function ensure_current_period(): void {
		$current = $this->current_period();
		$saved   = get_option( self::OPTION_PERIOD, '' );
		$saved   = is_string( $saved ) ? $saved : '';

		if ( ! $this->is_period( $saved ) ) {
			$this->preserve_legacy_usage();
			$this->start_period( $current, true );
			return;
		}

		// A valid-looking period from the future can only come from corrupt
		// storage or a clock rollback. It must not be recorded as a completed
		// archive period; retain the aggregate as historical evidence instead.
		if ( $saved > $current ) {
			$this->preserve_legacy_usage();
			$this->start_period( $current, true );
			return;
		}

		if ( $saved !== $current ) {
			$this->archive_period( $saved );
			$this->start_period( $current );
		}
	}

	/**
	 * Existing installations stored cumulative totals with no period marker.
	 * Keep that data separate rather than pretending it belongs to this month.
	 */
	private function preserve_legacy_usage(): void {
		$totals = get_option( self::OPTION_TOTALS, array() );
		$log    = get_option( self::OPTION_LOG, array() );

		if ( $this->has_usage( $totals ) && false === get_option( self::OPTION_LEGACY_TOTALS, false ) ) {
			update_option( self::OPTION_LEGACY_TOTALS, $this->normalise_totals( $totals ), false );
		}

		if ( is_array( $log ) && array() !== $log && false === get_option( self::OPTION_LEGACY_LOG, false ) ) {
			update_option( self::OPTION_LEGACY_LOG, $log, false );
		}
	}

	/**
	 * Start a fresh current period. Retained log entries deliberately survive a
	 * normal rollover so the seven-day chart can cross a month boundary.
	 */
	private function start_period( string $period, bool $clear_legacy_log = false ): void {
		update_option( self::OPTION_PERIOD, $period, false );
		update_option( self::OPTION_TOTALS, $this->empty_totals(), false );
		update_option( self::OPTION_PERIOD_CONTEXTS, array(), false );
		if ( $clear_legacy_log ) {
			update_option( self::OPTION_LOG, array(), false );
			update_option( self::OPTION_DAILY, array(), false );
		}
	}

	/**
	 * Reset only current-period counters. The configured cap, prior-period log
	 * entries and preserved historical migration data remain intact.
	 */
	private function reset_current_period(): void {
		update_option( self::OPTION_TOTALS, $this->empty_totals(), false );
		update_option( self::OPTION_PERIOD_CONTEXTS, array(), false );

		$period = $this->current_period();
		$log    = $this->current_log();
		$log    = array_values(
			array_filter(
				$log,
				static function ( array $entry ) use ( $period ): bool {
					return (string) ( $entry['period'] ?? '' ) !== $period;
				}
			)
		);
		update_option( self::OPTION_LOG, $log, false );

		$daily = array_filter(
			$this->daily_usage(),
			static function ( string $date ) use ( $period ): bool {
				return ! str_starts_with( $date, $period . '-' );
			},
			ARRAY_FILTER_USE_KEY
		);
		update_option( self::OPTION_DAILY, $daily, false );
	}

	/**
	 * @return array{prompt_tokens:int, completion_tokens:int, total_tokens:int, request_count:int, last_used:string}
	 */
	private function empty_totals(): array {
		return array(
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
			'total_tokens'      => 0,
			'request_count'     => 0,
			'last_used'         => '',
		);
	}

	/**
	 * @param mixed $totals Raw option data.
	 * @return array{prompt_tokens:int, completion_tokens:int, total_tokens:int, request_count:int, last_used:string}
	 */
	private function normalise_totals( $totals ): array {
		$totals = is_array( $totals ) ? $totals : array();
		$clean  = $this->empty_totals();

		foreach ( array( 'prompt_tokens', 'completion_tokens', 'total_tokens', 'request_count' ) as $key ) {
			$clean[ $key ] = max( 0, (int) ( $totals[ $key ] ?? 0 ) );
		}
		$clean['last_used'] = is_string( $totals['last_used'] ?? null ) ? $totals['last_used'] : '';

		return $clean;
	}

	/**
	 * @param mixed $totals Raw option data.
	 */
	private function has_usage( $totals ): bool {
		$totals = $this->normalise_totals( $totals );

		return $totals['total_tokens'] > 0 || $totals['request_count'] > 0;
	}

	/**
	 * @return array{prompt_tokens:int, completion_tokens:int, total_tokens:int, request_count:int, last_used:string}
	 */
	private function historical_totals(): array {
		return $this->normalise_totals( get_option( self::OPTION_LEGACY_TOTALS, array() ) );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function current_log(): array {
		$log = get_option( self::OPTION_LOG, array() );
		if ( ! is_array( $log ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$log,
				static function ( $entry ): bool {
					return is_array( $entry );
				}
			)
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $log Retained log entries.
	 * @return array<int, array<string, mixed>>
	 */
	private function log_for_period( array $log, string $period ): array {
		return array_values(
			array_filter(
				$log,
				static function ( array $entry ) use ( $period ): bool {
					return (string) ( $entry['period'] ?? '' ) === $period;
				}
			)
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $log Retained log entries.
	 * @return array<string, array{tokens:int, requests:int}>
	 */
	private function group_by_context( array $log ): array {
		$grouped = array();
		foreach ( $log as $entry ) {
			$context = (string) ( $entry['context'] ?? '' );
			$context = '' !== $context ? $context : 'general';
			if ( ! isset( $grouped[ $context ] ) ) {
				$grouped[ $context ] = array(
					'tokens'   => 0,
					'requests' => 0,
				);
			}

			$grouped[ $context ]['tokens']   += max( 0, (int) ( $entry['total'] ?? 0 ) );
			$grouped[ $context ]['requests'] += 1;
		}

		return $grouped;
	}

	/**
	 * Current-period operation totals are kept independently from the event ring
	 * buffer, so a busy month does not make the dashboard undercount where token
	 * usage went after older events are trimmed.
	 *
	 * @return array<string, array{tokens:int, requests:int}>
	 */
	private function period_contexts(): array {
		$contexts = get_option( self::OPTION_PERIOD_CONTEXTS, null );
		if ( is_array( $contexts ) ) {
			return $this->normalise_contexts( $contexts );
		}

		// Versions before the period-context option already tagged current log
		// entries. Derive their still-retained data once without making up older
		// events that are no longer available locally.
		$derived = $this->group_by_context( $this->log_for_period( $this->current_log(), $this->current_period() ) );
		update_option( self::OPTION_PERIOD_CONTEXTS, $derived, false );

		return $derived;
	}

	/**
	 * @param array<string, array{tokens:int, requests:int}> $contexts Existing current-period totals.
	 */
	private function record_period_context( array $contexts, string $context, int $tokens ): void {
		if ( ! isset( $contexts[ $context ] ) ) {
			$contexts[ $context ] = array(
				'tokens'   => 0,
				'requests' => 0,
			);
		}

		$contexts[ $context ]['tokens']   += max( 0, $tokens );
		$contexts[ $context ]['requests'] += 1;
		update_option( self::OPTION_PERIOD_CONTEXTS, $contexts, false );
	}

	/**
	 * @param array<mixed> $contexts Raw option data.
	 * @return array<string, array{tokens:int, requests:int}>
	 */
	private function normalise_contexts( array $contexts ): array {
		$clean = array();
		foreach ( $contexts as $context => $data ) {
			$key = sanitize_key( (string) $context );
			$key = '' !== $key ? $key : 'general';
			if ( ! isset( $clean[ $key ] ) ) {
				$clean[ $key ] = array(
					'tokens'   => 0,
					'requests' => 0,
				);
			}

			$data                       = is_array( $data ) ? $data : array();
			$clean[ $key ]['tokens']   += max( 0, (int) ( $data['tokens'] ?? 0 ) );
			$clean[ $key ]['requests'] += max( 0, (int) ( $data['requests'] ?? 0 ) );
		}

		return $clean;
	}

	/**
	 * Daily usage is independent from the bounded event log. This makes the
	 * seven-day chart complete even after older individual events are trimmed.
	 *
	 * @return array<string, array{tokens:int, requests:int}>
	 */
	private function daily_usage(): array {
		$daily = get_option( self::OPTION_DAILY, null );
		if ( is_array( $daily ) ) {
			return $this->normalise_daily( $daily );
		}

		// Upgrade safely from retained event data. Events trimmed before this
		// option existed cannot be reconstructed and are deliberately not guessed.
		$derived = $this->daily_from_log( $this->current_log() );
		update_option( self::OPTION_DAILY, $derived, false );

		return $derived;
	}

	/**
	 * @param array<int, array<string, mixed>> $log Retained log entries.
	 * @return array<string, array{tokens:int, requests:int}>
	 */
	private function daily_from_log( array $log ): array {
		$daily    = array();
		$timezone = wp_timezone();
		foreach ( $log as $entry ) {
			$time = isset( $entry['time'] ) && is_string( $entry['time'] ) ? strtotime( $entry['time'] ) : false;
			if ( false === $time ) {
				continue;
			}

			$date = wp_date( 'Y-m-d', $time, $timezone );
			if ( ! isset( $daily[ $date ] ) ) {
				$daily[ $date ] = array(
					'tokens'   => 0,
					'requests' => 0,
				);
			}

			$daily[ $date ]['tokens']   += max( 0, (int) ( $entry['total'] ?? 0 ) );
			$daily[ $date ]['requests'] += 1;
		}

		return $daily;
	}

	/**
	 * @param array<string, array{tokens:int, requests:int}> $daily Existing daily totals.
	 */
	private function record_daily( array $daily, string $date, int $tokens ): void {
		$today  = $this->current_day();
		$oldest = $this->now()->setTimezone( wp_timezone() )->setTime( 0, 0, 0 )->modify( '-6 days' )->format( 'Y-m-d' );
		foreach ( array_keys( $daily ) as $day ) {
			if ( $day < $oldest || $day > $today ) {
				unset( $daily[ $day ] );
			}
		}

		if ( ! isset( $daily[ $date ] ) ) {
			$daily[ $date ] = array(
				'tokens'   => 0,
				'requests' => 0,
			);
		}

		$daily[ $date ]['tokens']   += max( 0, $tokens );
		$daily[ $date ]['requests'] += 1;
		update_option( self::OPTION_DAILY, $daily, false );
	}

	/**
	 * @param array<mixed> $daily Raw option data.
	 * @return array<string, array{tokens:int, requests:int}>
	 */
	private function normalise_daily( array $daily ): array {
		$clean = array();
		foreach ( $daily as $date => $data ) {
			$date = (string) $date;
			if ( ! $this->is_day( $date ) ) {
				continue;
			}

			$data           = is_array( $data ) ? $data : array();
			$clean[ $date ] = array(
				'tokens'   => max( 0, (int) ( $data['tokens'] ?? 0 ) ),
				'requests' => max( 0, (int) ( $data['requests'] ?? 0 ) ),
			);
		}

		return $clean;
	}

	private function is_day( string $date ): bool {
		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts ) ) {
			return false;
		}

		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] );
	}

	/**
	 * @param array<int, array<string, mixed>> $log Retained log entries.
	 * @return array<string, array{tokens:int, requests:int}>
	 */
	private function group_by_model( array $log ): array {
		$grouped = array();
		foreach ( $log as $entry ) {
			$model = (string) ( $entry['model'] ?? '' );
			$model = '' !== $model ? $model : 'unknown';
			if ( ! isset( $grouped[ $model ] ) ) {
				$grouped[ $model ] = array(
					'tokens'   => 0,
					'requests' => 0,
				);
			}

			$grouped[ $model ]['tokens']   += max( 0, (int) ( $entry['total'] ?? 0 ) );
			$grouped[ $model ]['requests'] += 1;
		}

		return $grouped;
	}

	/**
	 * Preserve a compact record of recent closed accounting periods without
	 * turning the current dashboard into an unbounded history store.
	 */
	private function archive_period( string $period ): void {
		$totals = $this->normalise_totals( get_option( self::OPTION_TOTALS, array() ) );
		if ( ! $this->has_usage( $totals ) ) {
			return;
		}

		$archive   = get_option( self::OPTION_ARCHIVE, array() );
		$archive   = is_array( $archive ) ? $archive : array();
		$archive[] = array(
			'period'    => $period,
			'totals'    => $totals,
			'closed_at' => $this->utc_now()->format( 'c' ),
		);
		if ( count( $archive ) > self::MAX_ARCHIVE ) {
			$archive = array_slice( $archive, -self::MAX_ARCHIVE );
		}

		update_option( self::OPTION_ARCHIVE, $archive, false );
	}

	/**
	 * @param array<string, array{tokens:int, requests:int}> $daily Daily aggregates.
	 * @return array<int, array{date:string, label:string, tokens:int, requests:int}>
	 */
	private function last_seven_days( array $daily ): array {
		$timezone = wp_timezone();
		$today    = $this->now()->setTimezone( $timezone )->setTime( 0, 0, 0 );
		$days     = array();

		for ( $offset = 6; $offset >= 0; --$offset ) {
			$date         = $today->modify( '-' . $offset . ' days' );
			$key          = $date->format( 'Y-m-d' );
			$days[ $key ] = array(
				'date'     => $key,
				'label'    => wp_date( 'D', $date->getTimestamp(), $timezone ),
				'tokens'   => 0,
				'requests' => 0,
			);
		}

		foreach ( $days as $key => $day ) {
			$usage                    = $daily[ $key ] ?? array(
				'tokens'   => 0,
				'requests' => 0,
			);
			$days[ $key ]['tokens']   = max( 0, (int) ( $usage['tokens'] ?? 0 ) );
			$days[ $key ]['requests'] = max( 0, (int) ( $usage['requests'] ?? 0 ) );
		}

		return array_values( $days );
	}

	private function current_period(): string {
		return $this->now()->setTimezone( wp_timezone() )->format( 'Y-m' );
	}

	private function current_day(): string {
		return $this->now()->setTimezone( wp_timezone() )->format( 'Y-m-d' );
	}

	private function is_period( string $period ): bool {
		return 1 === preg_match( '/^\\d{4}-(0[1-9]|1[0-2])$/', $period );
	}

	private function now(): \DateTimeImmutable {
		if ( $this->clock instanceof \DateTimeImmutable ) {
			return $this->clock;
		}

		return new \DateTimeImmutable( 'now', wp_timezone() );
	}

	private function utc_now(): \DateTimeImmutable {
		return $this->now()->setTimezone( new \DateTimeZone( 'UTC' ) );
	}
}
