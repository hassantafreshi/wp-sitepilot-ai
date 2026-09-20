<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Content\ContentStudioReadModel;

/**
 * Capability- and nonce-checked CSV export for Content Studio History.
 *
 * This intentionally has no dependency on the Activity Log export: Content
 * Studio history is limited to durable, plugin-owned post/page output and
 * must never disclose unrelated operational events, prompts, bodies, or
 * credentials.
 */
final class ContentStudioHistoryExport {

	private const ACTION  = 'aisaautopilot_export_content_studio_history';
	private const COLUMNS = array( 'created_at', 'title', 'destination', 'state', 'template', 'usage', 'edit_url' );

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_export' ) );
	}

	/** Nonce-protected URL for the History card action. */
	public static function url(): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION ),
			self::ACTION
		);
	}

	/**
	 * @return array<int,array{created_at:string,title:string,destination:string,state:string,template:string,usage:string,edit_url:string}>
	 */
	public function records(): array {
		$records = array();
		foreach ( ( new ContentStudioReadModel() )->history( 100 ) as $row ) {
			$records[] = array(
				'created_at'  => (string) ( $row['created_at_iso'] ?? '' ),
				'title'       => (string) ( $row['title'] ?? '' ),
				'destination' => (string) ( $row['kind'] ?? '' ),
				'state'       => (string) ( $row['state'] ?? '' ),
				'template'    => (string) ( $row['template'] ?? '' ),
				'usage'       => (string) ( $row['usage'] ?? '' ),
				'edit_url'    => (string) ( $row['edit_url'] ?? '' ),
			);
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
	 * Neutralize spreadsheet formulas and normalize fields before CSV output.
	 */
	public static function csv_cell( string $value ): string {
		$value = wp_strip_all_tags( $value, false );
		$value = str_replace( array( "\r", "\n" ), ' ', $value );
		if ( 1 === preg_match( '/^\s*[=+\-@]/', $value ) ) {
			return "'" . $value;
		}

		return $value;
	}

	public function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export Content Studio history.', 'ai-site-assistant-autopilot' ) );
		}

		check_admin_referer( self::ACTION );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=aisaautopilot-content-studio-history-' . gmdate( 'Ymd-His' ) . '.csv' );

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
