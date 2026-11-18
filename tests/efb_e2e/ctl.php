<?php
/**
 * Test-site control for tests/efb_e2e/run.cjs (wp eval-file tests/efb_e2e/ctl.php <command> [args]).
 * Local throwaway test site only: it switches Easy Form Builder add-ons and the plan through EFB's own settings
 * functions (as EFB's e2e suite does) and configures the copilot with the inert mock provider.
 *
 *   setup                      provider = mock (https://ai-mock.invalid/v1), EFB copilot on, plan Free Plus
 *   queue <json>               queue one AI reply (the JSON object the model would return)
 *   prompts                    the prompts the mock received (last first), then clears them
 *   addon <code> <0|1>         EFB add-on switch in EFB's settings
 *   plan <1|2|3>               EFB plan option (1 Pro, 3 Free Plus, 2 Free)
 *   form <id>                  the stored rows of an EFB form (read only)
 *   caps <key...>              registry entries
 *   locale <xx_YY>             site language (WPLANG) and the admin user's language
 *   status <id>                EFB's status of a form (Form_Package::read(): draft | published)
 *   saved-form <title>         a published contact form (name, Email) made through the connector (EFB's import)
 *   delete <id>                remove a form the test made (EFB's own delete of the forms list)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}
$aisa_a = isset( $args ) ? array_values( (array) $args ) : array();
$aisa_out = static function ( $d ): void {
	echo "\n" . wp_json_encode( $d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
};
switch ( $aisa_a[0] ?? '' ) {
	case 'setup':
		wp_set_current_user( 1 );
		\AISAAutoPilot\Core\Settings::update( array( 'provider' => 'deepseek', 'base_url' => 'https://ai-mock.invalid/v1', 'model' => 'mock-model', 'key_management' => 'keyvault', 'efb_copilot_enabled' => true ) );
		\AISAAutoPilot\Core\Settings::set_api_key( 'inert-test-key' );
		update_option( 'aisa_e2e_ai_queue', array(), false );
		update_option( 'aisa_e2e_ai_prompts', array(), false );
		$aisa_out( array( 'ok' => true ) );
		break;
	case 'queue':
		$q   = get_option( 'aisa_e2e_ai_queue', array() );
		$q   = is_array( $q ) ? $q : array();
		$q[] = json_decode( (string) ( $aisa_a[1] ?? '{}' ), true );
		update_option( 'aisa_e2e_ai_queue', $q, false );
		$aisa_out( array( 'queued' => count( $q ) ) );
		break;
	case 'prompts':
		$aisa_out( array_reverse( (array) get_option( 'aisa_e2e_ai_prompts', array() ) ) );
		update_option( 'aisa_e2e_ai_prompts', array(), false );
		break;
	case 'addon':
		$s     = get_setting_Emsfb( 'decoded' );
		$k     = preg_replace( '/[^A-Za-z]/', '', (string) ( $aisa_a[1] ?? '' ) );
		$s->$k = '1' === ( $aisa_a[2] ?? '0' ) ? 1 : 0;
		\efbFunction::set_setting_Emsfb( $s );
		$aisa_out( array( $k => get_setting_Emsfb( 'decoded' )->$k ) );
		break;
	case 'plan':
		update_option( 'emsfb_pro', (int) ( $aisa_a[1] ?? 2 ) );
		$aisa_out( array( 'emsfb_pro' => (int) get_option( 'emsfb_pro' ) ) );
		break;
	case 'form':
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT form_name, form_type, form_structer FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", (int) ( $aisa_a[1] ?? 0 ) ), ARRAY_A );
		$rows = is_array( $row ) ? json_decode( str_replace( '\\"', '"', (string) $row['form_structer'] ), true ) : null;
		$aisa_out( array( 'name' => $row['form_name'] ?? null, 'type' => $row['form_type'] ?? null, 'rows' => $rows ) );
		break;
	case 'caps':
		$c = get_efbFunction()->capabilities_efb();
		$o = array();
		foreach ( array_slice( $aisa_a, 1 ) as $k ) {
			$o[ $k ] = $c['features'][ $k ] ?? ( $c[ $k ] ?? null );
		}
		$aisa_out( $o );
		break;
	case 'locale':
		$l = (string) ( $aisa_a[1] ?? '' );
		update_option( 'WPLANG', 'en_US' === $l ? '' : $l );
		update_user_meta( 1, 'locale', 'en_US' === $l ? '' : $l );
		$aisa_out( array( 'locale' => $l ) );
		break;
	case 'status':
		wp_set_current_user( 1 );
		$r = \Emsfb\Form_Package::read( (int) ( $aisa_a[1] ?? 0 ) );
		$aisa_out( array( 'status' => is_array( $r ) ? $r['status'] : null ) );
		break;
	case 'saved-form':
		wp_set_current_user( 1 );
		$res = ( new \AISAAutoPilot\Connectors\EasyFormBuilderConnector() )->execute_action(
			array(
				'title'  => (string) ( $aisa_a[1] ?? 'Saved form' ),
				'fields' => array(
					array(
						'key'      => 'name',
						'type'     => 'text',
						'label'    => 'Full name',
						'required' => true,
						'options'  => array(),
					),
					array(
						'key'      => 'email',
						'type'     => 'email',
						'label'    => 'Email',
						'required' => true,
						'options'  => array(),
					),
				),
			)
		);
		$aisa_out( array( 'form_id' => (int) ( $res['form_id'] ?? 0 ) ) );
		break;
	case 'delete':
		global $wpdb;
		$id = (int) ( $aisa_a[1] ?? 0 );
		$wpdb->delete( $wpdb->prefix . 'emsfb_form', array( 'form_id' => $id ) );
		do_action( 'efb_form_deleted', $id );
		$aisa_out( array( 'ok' => true ) );
		break;
	default:
		$aisa_out( array( 'error' => 'unknown command' ) );
}
