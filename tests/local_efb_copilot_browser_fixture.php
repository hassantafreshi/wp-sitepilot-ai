<?php
/**
 * Creates/removes the exact Easy Form Builder record used by browser QA.
 *
 * `publish` puts the prepared form on a page for a visitor check and prints the
 * logic EFB stored for it; `restore` removes that page with the form.
 */
declare(strict_types=1);

$mode = $argv[1] ?? 'prepare';
if ( ! in_array( $mode, array( 'prepare', 'publish', 'restore' ), true ) ) {
	fwrite( STDERR, "Usage: php local_efb_copilot_browser_fixture.php prepare|publish|restore\n" );
	exit( 64 );
}

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\xampp\htdocs\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Core\Settings;

const AISAAUTOPILOT_EFB_FIXTURE_OPTION = 'aisaautopilot_efb_copilot_browser_fixture';
const AISAAUTOPILOT_EFB_FIXTURE_NAME   = 'AISAAutoPilot EFB Copilot browser QA';
const AISAAUTOPILOT_EFB_FIXTURE_PAGE   = 'AISAAutoPilot EFB Copilot logic QA';

$restore = static function (): void {
	$record = get_option( AISAAUTOPILOT_EFB_FIXTURE_OPTION, null );
	// No snapshot means no fixture to restore. Never touch live settings here:
	// before T15 a missing snapshot fell through to delete_option( Settings::OPTION ).
	if ( null === $record ) {
		return;
	}
	if ( ! is_array( $record ) || ! array_key_exists( 'had_settings', $record ) ) {
		throw new RuntimeException( 'EFB Copilot fixture snapshot is invalid; live settings were left untouched.' );
	}

	// Delete only the page `publish` created, recognised by id and title.
	$page_id = absint( $record['page_id'] ?? 0 );
	if ( $page_id > 0 && AISAAUTOPILOT_EFB_FIXTURE_PAGE === get_the_title( $page_id ) ) {
		wp_delete_post( $page_id, true );
	}

	global $wpdb;
	$form_id = absint( $record['form_id'] ?? 0 );
	$table   = $wpdb->prefix . 'emsfb_form';
	if ( $form_id > 0 ) {
		// Delete only the exact row created by this helper.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$name = $wpdb->get_var( $wpdb->prepare( "SELECT form_name FROM {$table} WHERE form_id = %d", $form_id ) );
		if ( AISAAUTOPILOT_EFB_FIXTURE_NAME === $name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $table, array( 'form_id' => $form_id ), array( '%d' ) );
		}
	}

	if ( ! empty( $record['had_settings'] ) ) {
		update_option( Settings::OPTION, $record['settings'], false );
	} else {
		delete_option( Settings::OPTION );
	}
	delete_option( AISAAUTOPILOT_EFB_FIXTURE_OPTION );
};

if ( 'restore' === $mode ) {
	$restore();
	echo "EFB Copilot browser fixture removed.\n";
	exit( 0 );
}

if ( 'publish' === $mode ) {
	$record  = get_option( AISAAUTOPILOT_EFB_FIXTURE_OPTION, null );
	$form_id = is_array( $record ) ? absint( $record['form_id'] ?? 0 ) : 0;
	if ( $form_id <= 0 ) {
		fwrite( STDERR, "Run prepare first.\n" );
		exit( 1 );
	}

	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$stored    = (string) $wpdb->get_var( $wpdb->prepare( "SELECT form_structer FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", $form_id ) );
	$structure = json_decode( $stored, true );
	if ( ! is_array( $structure ) ) {
		$structure = json_decode( stripslashes( $stored ), true );
	}

	$page_id = absint( $record['page_id'] ?? 0 );
	if ( $page_id <= 0 ) {
		$page_id = (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => AISAAUTOPILOT_EFB_FIXTURE_PAGE,
				'post_content' => '[EMS_Form_Builder id=' . $form_id . ']',
			)
		);
		$record['page_id'] = $page_id;
		update_option( AISAAUTOPILOT_EFB_FIXTURE_OPTION, $record, false );
	}

	echo wp_json_encode(
		array(
			'page_url'    => get_permalink( $page_id ),
			'logic'       => is_array( $structure ) ? ( $structure[0]['logic'] ?? null ) : null,
			'logic_rules' => is_array( $structure ) ? ( $structure[0]['logic_rules'] ?? array() ) : array(),
		)
	) . PHP_EOL;
	exit( 0 );
}

$restore();

$missing           = new stdClass();
$original_settings = get_option( Settings::OPTION, $missing );
$form_id           = 0;

try {
	Settings::update( array( 'efb_copilot_enabled' => true ) );
	$result  = ( new EasyFormBuilderConnector() )->execute_action(
		array(
			'title'                => AISAAUTOPILOT_EFB_FIXTURE_NAME,
			'confirmation_message' => 'Thank you.',
			'fields'               => array(
				array( 'type' => 'text', 'label' => 'Full name', 'required' => true ),
				array( 'type' => 'email', 'label' => 'Work email', 'required' => true ),
				array( 'type' => 'textarea', 'label' => 'Message', 'required' => false ),
			),
		)
	);
	$form_id = absint( $result['form_id'] ?? 0 );
	if ( 'success' !== ( $result['status'] ?? '' ) || $form_id <= 0 ) {
		throw new RuntimeException( (string) ( $result['message'] ?? 'EFB fixture creation failed.' ) );
	}

	update_option(
		AISAAUTOPILOT_EFB_FIXTURE_OPTION,
		array(
			'had_settings' => $original_settings !== $missing,
			'settings'     => $original_settings !== $missing ? $original_settings : array(),
			'form_id'      => $form_id,
		),
		false
	);

	echo wp_json_encode(
		array(
			'form_id'  => $form_id,
			'edit_url' => admin_url( 'admin.php?page=Emsfb&state=edit-form&id=' . $form_id ),
		)
	) . PHP_EOL;
} catch ( Throwable $error ) {
	if ( $form_id > 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'emsfb_form';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'form_id' => $form_id ), array( '%d' ) );
	}
	if ( $original_settings === $missing ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original_settings, false );
	}
	delete_option( AISAAUTOPILOT_EFB_FIXTURE_OPTION );
	fwrite( STDERR, $error->getMessage() . PHP_EOL );
	exit( 1 );
}
