<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Activity\ActivityFeed;
use AISAAutoPilot\Logging\Logger;

/**
 * Capability- and nonce-checked CSV export for the Activity Log (mockup D-12).
 *
 * Delivered through admin-post.php so the REST contract stays untouched. Only
 * administrators can trigger it, and the nonce is bound to the export action.
 * The feed applies Logger/ActivityFeed redaction and exports only the explicit
 * diagnostic columns, never arbitrary context or credentials from settings.
 */
final class LogExport {

	private const ACTION  = 'aisaautopilot_export_logs';
	private const COLUMNS = array( 'time', 'level', 'event', 'trace_id', 'action_id', 'connector_id', 'message' );

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_export' ) );
	}

	/**
	 * Nonce-protected URL the header action points at.
	 */
	public static function url(): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION ),
			self::ACTION
		);
	}

	/**
	 * @return array<int,array{time:string,level:string,event:string,trace_id:string,action_id:string,connector_id:string,message:string}>
	 */
	public function records(): array {
		$records = array();
		foreach ( ( new ActivityFeed( new Logger() ) )->recent( 100 ) as $item ) {
			$record = array();
			foreach ( self::COLUMNS as $column ) {
				$record[ $column ] = (string) ( $item[ $column ] ?? '' );
			}
			$records[] = $record;
		}

		return $records;
	}

	/**
	 * Writes a complete CSV stream without sending headers or exiting.
	 *
	 * @param resource $stream Writable CSV stream.
	 */
	public function write_csv( $stream ): void {
		fputcsv( $stream, self::COLUMNS );
		foreach ( $this->records() as $record ) {
			$row = array();
			foreach ( self::COLUMNS as $column ) {
				$row[] = self::csv_cell( (string) ( $record[ $column ] ?? '' ) );
			}
			fputcsv( $stream, $row );
		}
	}

	/**
	 * Keep every administrative CSV export under the same formula-injection policy.
	 */
	public static function csv_cell( string $value ): string {
		return ContentStudioHistoryExport::csv_cell( $value );
	}

	public function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export the activity log.', 'ai-site-assistant-autopilot' ) );
		}

		check_admin_referer( self::ACTION );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=aisaautopilot-activity-' . gmdate( 'Ymd-His' ) . '.csv' );

		$stream = fopen( 'php://output', 'w' );
		if ( false === $stream ) {
			wp_die( esc_html__( 'Could not open the export stream.', 'ai-site-assistant-autopilot' ) );
		}

		$this->write_csv( $stream );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output is a response stream, not a file; WP_Filesystem cannot write to it.
		fclose( $stream );
		exit;
	}
}
