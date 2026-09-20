<?php
/**
 * Local WordPress integration checks for the Integrations capability hub.
 *
 * The page may only render controls that change an actual persisted backend
 * contract. It also verifies the exact six-card mockup information architecture.
 */
declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\IntegrationsPage;
use AISAAutoPilot\Admin\CopilotPage;
use AISAAutoPilot\Api\ContentController;
use AISAAutoPilot\Api\CopilotController;
use AISAAutoPilot\Api\RestRoutes;
use AISAAutoPilot\Content\ContentHooks;
use AISAAutoPilot\Core\Settings;

wp_set_current_user( 1 );

$checks              = array();
$missing             = new stdClass();
$original_option     = get_option( Settings::OPTION, $missing );
$original_post       = $_POST;
$original_get        = $_GET;
$has_request_method  = array_key_exists( 'REQUEST_METHOD', $_SERVER );
$request_method      = $_SERVER['REQUEST_METHOD'] ?? '';
$temporary_post      = 0;

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'name' => $name,
		'ok'   => $ok,
	);
};

$render_hub = static function (): string {
	ob_start();
	( new IntegrationsPage() )->render();
	return (string) ob_get_clean();
};

$render_copilot = static function ( string $mode ): string {
	$_GET = array( 'mode' => $mode );
	ob_start();
	( new CopilotPage() )->render();
	$html = (string) ob_get_clean();
	$_GET = array();
	return $html;
};

$submit_hub = static function ( array $fields ) use ( $render_hub ): string {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST                     = array_merge(
		array(
			'aisaautopilot_integrations_nonce' => wp_create_nonce( 'aisaautopilot_integrations' ),
		),
		$fields
	);

	return $render_hub();
};

try {
	$defaults = Settings::defaults();
	$check(
		! empty( $defaults['form_composer_enabled'] )
		&& ! empty( $defaults['efb_copilot_enabled'] )
		&& ! empty( $defaults['content_studio_enabled'] ),
		'new integration gates default to enabled so existing sites keep their behaviour'
	);

	Settings::update(
		array(
			'form_composer_enabled'  => false,
			'content_studio_enabled' => false,
			'efb_copilot_enabled'    => false,
			'kb_auto_index'          => false,
		)
	);

	$form_response = ( new CopilotController() )->plan( new WP_REST_Request( 'POST' ) );
	$form_data     = $form_response->get_data();
	$check(
		403 === $form_response->get_status() && 'integration_disabled' === ( $form_data['code'] ?? '' ),
		'Form Studio server action is denied while its Integration switch is off'
	);

	$content_response = ( new ContentController() )->plan( new WP_REST_Request( 'POST' ) );
	$content_data     = $content_response->get_data();
	$check(
		403 === $content_response->get_status() && 'integration_disabled' === ( $content_data['code'] ?? '' ),
		'Content Studio server action is denied while its Integration switch is off'
	);

	$routes = new RestRoutes();
	$check(
		! $routes->can_manage_form_composer()
		&& ! $routes->can_manage_content_studio()
		&& ! $routes->can_manage_efb_copilot(),
		'Form Studio, Content Studio and EFB REST permission gates follow their persisted switches'
	);
	$form_ui    = $render_copilot( 'forms' );
	$content_ui = $render_copilot( 'content' );
	$check(
		false !== strpos( $form_ui, 'Form Studio is turned off' )
		&& false !== strpos( $form_ui, 'Open Integrations' )
		&& false !== strpos( $content_ui, 'Content Studio is turned off' )
		&& false !== strpos( $content_ui, 'Open Integrations' ),
		'disabled Form Studio and Content Studio routes show a truthful Integration recovery state'
	);

	$woo            = (array) ( Settings::all()['woocommerce'] ?? array() );
	$woo['enabled'] = false;
	Settings::update(
		array(
			'public_assistant_enabled' => false,
			'form_composer_enabled'    => false,
			'content_studio_enabled'   => false,
			'efb_copilot_enabled'      => false,
			'woocommerce'              => $woo,
		)
	);
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_POST                     = array();
	$hub                       = $render_hub();
	$check(
		false !== strpos( $hub, 'data-sp-integrations' )
		&& 4 === substr_count( $hub, 'data-sp-integration-group=' )
		&& 6 === substr_count( $hub, 'data-sp-integration-card=' ),
		'Integrations renders the canonical four groups and six cards'
	);
	$check(
		false !== strpos( $hub, 'data-sp-integration-card="form-composer"' )
		&& false !== strpos( $hub, 'data-sp-integration-status="always-on"' )
		&& false !== strpos( $hub, 'data-sp-integration-open="site-knowledge"' )
		&& false !== strpos( $hub, 'data-sp-integration-dialog="site-knowledge"' ),
		'Form Studio and the always-on Site Knowledge Configure dialog keep their stable UI contracts'
	);
	$check(
		false !== strpos( $hub, 'data-sp-integrations-all-off' ),
		'The all-off banner appears only when every available optional integration is off'
	);

	$invalid = $submit_hub(
		array(
			'aisaautopilot_integrations_nonce' => 'invalid',
			'aisaautopilot_integration_action' => 'toggle',
			'aisaautopilot_integration'        => 'answer-desk',
			'aisaautopilot_enabled'            => '1',
		)
	);
	$check(
		empty( Settings::get( 'public_assistant_enabled', false ) ) && false === strpos( $invalid, 'Integration settings saved.' ),
		'An invalid nonce cannot change an Integration switch'
	);

	$answer_saved = $submit_hub(
		array(
			'aisaautopilot_integration_action' => 'toggle',
			'aisaautopilot_integration'        => 'answer-desk',
			'aisaautopilot_enabled'            => '1',
		)
	);
	$check(
		! empty( Settings::get( 'public_assistant_enabled', false ) ) && false !== strpos( $answer_saved, 'Integration settings saved.' ),
		'The Answer Desk card switch persists its public assistant setting'
	);

	$knowledge_saved = $submit_hub(
		array(
			'aisaautopilot_integration_action' => 'configure',
			'aisaautopilot_integration'        => 'site-knowledge',
		)
	);
	$check(
		empty( Settings::get( 'kb_auto_index', true ) ) && false !== strpos( $knowledge_saved, 'Integration settings saved.' ),
		'The Site Knowledge dialog changes auto-indexing without pretending the core capability is off'
	);

	$widget_before = Settings::get( 'widget', array() );
	$form_saved    = $submit_hub(
		array(
			'aisaautopilot_integration_action' => 'configure',
			'aisaautopilot_integration'        => 'form-composer',
			'form_composer_enabled'            => '1',
		)
	);
	$check(
		! empty( Settings::get( 'form_composer_enabled', false ) )
		&& $widget_before === Settings::get( 'widget', array() )
		&& false !== strpos( $form_saved, 'Integration settings saved.' ),
		'The Form Studio dialog persists only its gate and preserves unrelated widget settings'
	);

	$temporary_post = wp_insert_post(
		array(
			'post_title'   => 'AISAAutoPilot integration-gate temporary post',
			'post_content' => 'Temporary test content.',
			'post_status'  => 'draft',
			'post_type'    => 'post',
		),
		true
	);
	if ( is_wp_error( $temporary_post ) ) {
		throw new RuntimeException( $temporary_post->get_error_message() );
	}
	$temporary_post = (int) $temporary_post;
	$post           = get_post( $temporary_post );
	if ( ! $post instanceof WP_Post ) {
		throw new RuntimeException( 'Could not load the temporary post.' );
	}
	$cron_args = array( $temporary_post );
	wp_clear_scheduled_hook( 'aisaautopilot_index_post', $cron_args );
	$hooks = new ContentHooks();
	$hooks->on_status_change( 'publish', 'draft', $post );
	$check(
		false === wp_next_scheduled( 'aisaautopilot_index_post', $cron_args ),
		'Site Knowledge does not schedule an auto-index job when auto-index is off'
	);

	Settings::update( array( 'kb_auto_index' => true ) );
	$hooks->on_status_change( 'publish', 'draft', $post );
	$check(
		false !== wp_next_scheduled( 'aisaautopilot_index_post', $cron_args ),
		'Site Knowledge schedules a deferred index job when auto-index is on'
	);
} finally {
	if ( $temporary_post > 0 ) {
		wp_clear_scheduled_hook( 'aisaautopilot_index_post', array( $temporary_post ) );
		wp_delete_post( $temporary_post, true );
	}
	if ( $original_option === $missing ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original_option, false );
	}
	$_POST = $original_post;
	$_GET  = $original_get;
	if ( $has_request_method ) {
		$_SERVER['REQUEST_METHOD'] = $request_method;
	} else {
		unset( $_SERVER['REQUEST_METHOD'] );
	}
}

$failed = array_filter( $checks, static fn( array $item ): bool => ! $item['ok'] );
foreach ( $checks as $item ) {
	echo ( $item['ok'] ? 'PASS ' : 'FAIL ' ) . $item['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
