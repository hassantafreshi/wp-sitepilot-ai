<?php
/** Local WordPress renderer contract for the specialist Form Studio shell (D-23; formerly "Form Composer"). */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Admin\CopilotPage;
use AISAAutoPilot\Core\Settings;

$missing           = new stdClass();
$original_settings = get_option( Settings::OPTION, $missing );
$original_key      = get_option( Settings::KEY_OPTION, $missing );
$original_user_id  = get_current_user_id();
$original_get      = $_GET;
$checks            = array();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array( 'ok' => $ok, 'name' => $name );
};
$restore_option = static function ( string $name, $value ) use ( $missing ): void {
	if ( $missing === $value ) {
		delete_option( $name );
		return;
	}
	update_option( $name, $value, false );
};
$render = static function (): string {
	ob_start();
	( new CopilotPage() )->render();
	return (string) ob_get_clean();
};

try {
	wp_set_current_user( 1 );
	$_GET = array( 'page' => 'aisaautopilot-form-builder', 'mode' => 'forms' );
	Settings::update(
		array(
			'form_composer_enabled' => true,
			'provider'              => 'deepseek',
			'base_url'              => 'https://form-render.test.invalid/v1',
			'model'                 => 'form-render-fixture',
			'key_management'        => 'keyvault',
		)
	);
	Settings::set_api_key( 'form-render-fixture-key' );
	$html = $render();
	$check(
		1 === substr_count( $html, 'class="sp-fc-header"' )
		&& 1 === substr_count( $html, 'data-sp-form-canvas' )
		&& 1 === substr_count( $html, 'data-sp-form-assistant' )
		&& 1 === substr_count( $html, 'data-sp-form-empty' ),
		'Form Studio renders one mockup header, canvas, assistant and truthful empty state'
	);
	$check(
		// The gallery (owner-approved redesign, 2026-10-28): six examples made only of native
		// field types, all plain buttons that only prefill the prompt.
		6 === substr_count( $html, 'data-example=' )
		&& 6 === substr_count( $html, '<button type="button" class="sp-fc-example" data-example=' )
		&& false !== strpos( $html, 'id="aisaautopilot-thread"' )
		&& false !== strpos( $html, 'id="aisaautopilot-prompt"' )
		&& false !== strpos( $html, 'id="aisaautopilot-generate"' )
		&& false !== strpos( $html, 'id="aisaautopilot-voice-toggle"' )
		&& false !== strpos( $html, 'data-sp-form-delivery' ),
		'Compose keeps examples, text, voice, explicit send and the administrator-owned delivery destination'
	);
	$check(
		false === strpos( $html, 'class="sp-pagebar"' )
		&& false === strpos( $html, 'Product draft' )
		&& false === strpos( $html, 'Estimated usage' )
		&& false === strpos( $html, 'Context of this request' ),
		'Specialist shell removes the unrelated admin-page chrome and unsupported estimates from its canonical surface'
	);

	Settings::update( array( 'form_composer_enabled' => false ) );
	$disabled = $render();
	$check(
		1 === preg_match( '/id="aisaautopilot-prompt"[^>]*disabled/', $disabled )
		&& 1 === preg_match( '/id="aisaautopilot-generate"[^>]*disabled/', $disabled )
		// The chat draws the readiness checklist from the boot data: Form Studio is off, and the
		// way back is Integrations.
		&& false !== strpos( $disabled, '"studio":false' )
		&& false !== strpos( $disabled, 'Turn on Form Studio' )
		&& false !== strpos( $disabled, 'Open Integrations' )
		&& false !== strpos( $disabled, 'page=' . AdminMenu::SLUG_INTEGRATIONS ),
		'Disabled integration preserves the workspace but prevents plan requests and offers the real recovery route'
	);
} finally {
	$restore_option( Settings::OPTION, $original_settings );
	$restore_option( Settings::KEY_OPTION, $original_key );
	$_GET = $original_get;
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
