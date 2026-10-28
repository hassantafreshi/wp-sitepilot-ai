<?php
declare(strict_types=1);

namespace AISAAutoPilot\Support;

use AISAAutoPilot\Chat\VisitorConversationStore;
use AISAAutoPilot\Content\KbCache;
use AISAAutoPilot\Content\KbGaps;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Logging\Logger;

/**
 * How long visitor data is kept, and the daily job that deletes what is older.
 *
 * Three periods, each set by the administrator in Advanced settings (0 keeps the data until
 * someone deletes it):
 * - recorded visitor conversations, by their last message;
 * - requests to talk to a person, by their last change;
 * - the text of questions the assistant could not answer, and of cached answers.
 *
 * The job runs on WP-Cron, which WordPress starts when someone visits the site; a site with
 * little traffic or with DISABLE_WP_CRON set needs a server cron job, which the settings
 * card explains. "Delete expired data now" runs the same job at once.
 */
final class Retention {

	public const HOOK = 'aisaautopilot_retention_cleanup';

	/** What the last run did: time and how many items each kind lost. */
	public const LAST_RUN_OPTION = 'aisaautopilot_retention_last_run';

	/** Defaults the owner approved; the administrator can change each one. */
	public const DEFAULTS = array(
		'conversations_days' => 30,
		'handoffs_days'      => 180,
		'questions_days'     => 90,
	);

	/** Ten years: anything longer is "keep". */
	public const MAX_DAYS = 3650;

	/** Rows deleted per query, so a large backlog never holds a long lock. */
	private const BATCH = 500;

	/** Batches per run; what is left goes in the next run. */
	private const MAX_BATCHES = 20;

	public function register(): void {
		add_action( self::HOOK, array( $this, 'cron' ) );
		add_action( 'init', array( self::class, 'schedule' ) );
	}

	/** Keeps the daily event in the queue. One autoloaded option read when it is there. */
	public static function schedule(): void {
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	public static function unschedule(): void {
		wp_unschedule_hook( self::HOOK );
	}

	/**
	 * The saved periods in days, each 0 (keep) to MAX_DAYS.
	 *
	 * @return array{conversations_days:int, handoffs_days:int, questions_days:int}
	 */
	public static function periods(): array {
		$saved   = Settings::get( 'retention', array() );
		$saved   = is_array( $saved ) ? $saved : array();
		$periods = array();
		foreach ( self::DEFAULTS as $key => $default ) {
			$periods[ $key ] = array_key_exists( $key, $saved ) ? self::days( $saved[ $key ] ) : $default;
		}

		/** @var array{conversations_days:int, handoffs_days:int, questions_days:int} $periods */
		return $periods;
	}

	/** A submitted number of days, clamped to 0..MAX_DAYS. */
	public static function days( $value ): int {
		return max( 0, min( self::MAX_DAYS, (int) $value ) );
	}

	/** The daily WP-Cron callback. */
	public function cron(): void {
		$this->run();
	}

	/**
	 * Deletes everything older than its period and records what was deleted.
	 *
	 * @return array{time:int, conversations:int, handoffs:int, questions:int, complete:bool}
	 */
	public function run(): array {
		$periods  = self::periods();
		$complete = true;
		$deleted  = array(
			'conversations' => 0,
			'handoffs'      => 0,
			'questions'     => 0,
		);
		$db       = $this->db();
		$tables   = array(
			'conversations' => array( $db->prefix . VisitorConversationStore::TABLE, $periods['conversations_days'] ),
			'handoffs'      => array( $db->prefix . HandoffInbox::TABLE, $periods['handoffs_days'] ),
		);
		foreach ( $tables as $kind => $table ) {
			if ( $table[1] > 0 ) {
				$result           = $this->delete_rows( $table[0], $this->cutoff( $table[1] ) );
				$deleted[ $kind ] = $result['deleted'];
				$complete         = $complete && $result['complete'];
			}
		}
		if ( $periods['questions_days'] > 0 ) {
			$before               = time() - $periods['questions_days'] * DAY_IN_SECONDS;
			$deleted['questions'] = ( new KbGaps() )->forget_before( $before ) + ( new KbCache() )->forget_before( $before );
		}

		$last = array( 'time' => time() ) + $deleted + array( 'complete' => $complete );
		update_option( self::LAST_RUN_OPTION, $last, false );
		if ( array_sum( $deleted ) > 0 ) {
			( new Logger() )->info(
				'privacy.retention_cleanup',
				sprintf( 'Retention cleanup deleted %d conversations, %d requests to talk to a person and %d questions.', $deleted['conversations'], $deleted['handoffs'], $deleted['questions'] )
			);
		}

		return $last;
	}

	/**
	 * What the last run did, or null before the first one.
	 *
	 * @return array{time:int, conversations:int, handoffs:int, questions:int, complete:bool}|null
	 */
	public static function last_run(): ?array {
		$last = get_option( self::LAST_RUN_OPTION, null );
		if ( ! is_array( $last ) || empty( $last['time'] ) ) {
			return null;
		}

		return array(
			'time'          => (int) $last['time'],
			'conversations' => (int) ( $last['conversations'] ?? 0 ),
			'handoffs'      => (int) ( $last['handoffs'] ?? 0 ),
			'questions'     => (int) ( $last['questions'] ?? 0 ),
			'complete'      => ! empty( $last['complete'] ),
		);
	}

	/**
	 * One sentence for the administrator: when the last cleanup ran and what it deleted.
	 *
	 * @param array{time:int, conversations:int, handoffs:int, questions:int, complete:bool}|null $last
	 */
	public static function summary( ?array $last ): string {
		if ( null === $last ) {
			return __( 'The cleanup has not run yet.', 'ai-site-assistant-autopilot' );
		}
		$text = sprintf(
			/* translators: 1: date and time, 2: "3 conversations", 3: "1 request to talk to a person", 4: "2 questions". */
			__( 'Last cleanup %1$s: deleted %2$s, %3$s and %4$s.', 'ai-site-assistant-autopilot' ),
			wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last['time'] ),
			/* translators: %s: number of recorded visitor conversations. */
			sprintf( _n( '%s conversation', '%s conversations', $last['conversations'], 'ai-site-assistant-autopilot' ), number_format_i18n( $last['conversations'] ) ),
			/* translators: %s: number of requests to talk to a person. */
			sprintf( _n( '%s request to talk to a person', '%s requests to talk to a person', $last['handoffs'], 'ai-site-assistant-autopilot' ), number_format_i18n( $last['handoffs'] ) ),
			/* translators: %s: number of unanswered questions and cached answers. */
			sprintf( _n( '%s question', '%s questions', $last['questions'], 'ai-site-assistant-autopilot' ), number_format_i18n( $last['questions'] ) )
		);
		if ( ! $last['complete'] ) {
			$text .= ' ' . __( 'More is left; the next run continues.', 'ai-site-assistant-autopilot' );
		}

		return $text;
	}

	/** When the daily job runs next, or 0 when it is not queued. */
	public static function next_run(): int {
		return (int) wp_next_scheduled( self::HOOK );
	}

	/** Whether WordPress's own visit-triggered cron is turned off in wp-config.php. */
	public static function wp_cron_disabled(): bool {
		return defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	}

	/** UTC MySQL datetime $days ago. */
	private function cutoff( int $days ): string {
		return gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
	}

	/**
	 * Deletes rows last changed before $cutoff, a batch at a time.
	 *
	 * @return array{deleted:int, complete:bool}
	 */
	private function delete_rows( string $table, string $cutoff ): array {
		$db = $this->db();
		if ( $table !== $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $db->esc_like( $table ) ) ) ) {
			return array(
				'deleted'  => 0,
				'complete' => true,
			);
		}
		$deleted = 0;
		for ( $batch = 0; $batch < self::MAX_BATCHES; $batch++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a cleanup of the plugin's own table.
			$count    = (int) $db->query( $db->prepare( 'DELETE FROM %i WHERE updated_at < %s LIMIT %d', $table, $cutoff, self::BATCH ) );
			$deleted += max( 0, $count );
			if ( $count < self::BATCH ) {
				return array(
					'deleted'  => $deleted,
					'complete' => true,
				);
			}
		}

		return array(
			'deleted'  => $deleted,
			'complete' => false,
		);
	}

	private function db(): \wpdb {
		return $GLOBALS['wpdb'];
	}
}
