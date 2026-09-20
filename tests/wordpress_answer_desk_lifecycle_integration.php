<?php
/**
 * Local WordPress integration checks for the canonical Answer Desk runtime.
 *
 * This deliberately exercises the Integration gate, the Widget visibility
 * gate, the public controller and the visitor renderer together. No provider
 * request or delivery channel is contacted: every endpoint is called only
 * through its safe disabled/empty input path.
 */
declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Api\ChatWidgetController;
use AISAAutoPilot\Api\PublicChatController;
use AISAAutoPilot\Core\Plugin;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Frontend\ChatWidget;
use AISAAutoPilot\Frontend\WidgetRenderer;

wp_set_current_user( 1 );

$checks           = array();
$missing          = new stdClass();
$original_settings = get_option( Settings::OPTION, $missing );

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'name' => $name,
		'ok'   => $ok,
	);
};

$has_callback = static function ( string $hook, string $class, string $method ): bool {
	global $wp_filter;

	if ( ! isset( $wp_filter[ $hook ] ) || ! $wp_filter[ $hook ] instanceof WP_Hook ) {
		return false;
	}

	foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
		foreach ( $callbacks as $registered ) {
			$callback = $registered['function'] ?? null;
			if (
				is_array( $callback )
				&& isset( $callback[0], $callback[1] )
				&& $callback[0] instanceof $class
				&& $method === $callback[1]
			) {
				return true;
			}
		}
	}

	return false;
};

$set_state = static function ( bool $integration_enabled, array $widget ): void {
	Settings::update(
		array(
			'public_assistant_enabled' => $integration_enabled,
			'widget'                   => $widget,
		)
	);
};

$render_widget = static function (): string {
	ob_start();
	Plugin::instance()->render_widget();
	return (string) ob_get_clean();
};

try {
	$check(
		$has_callback( 'wp_footer', Plugin::class, 'render_widget' )
		&& ! $has_callback( 'wp_footer', WidgetRenderer::class, 'render' ),
		'Plugin registers the configurable Answer Desk renderer and no incompatible second launcher'
	);

	$integration_off_widget = array_merge(
		Settings::widget_defaults(),
		array(
			'enabled'                      => true,
			'conversation_logging_enabled' => true,
		)
	);
	$set_state( false, $integration_off_widget );
	$disabled_widget_controller = new ChatWidgetController();
	$disabled_response          = $disabled_widget_controller->ask( new WP_REST_Request( 'POST' ) );
	$disabled_settings          = $disabled_widget_controller->settings()->get_data();
	$disabled_public_response   = ( new PublicChatController() )->ask( new WP_REST_Request( 'POST' ) );
	$check(
		! Settings::answer_desk_enabled()
		&& ! ( new ChatWidget() )->should_render_widget()
		&& '' === trim( $render_widget() )
		&& false === ( $disabled_settings['enabled'] ?? true )
		&& 403 === $disabled_response->get_status()
		&& 403 === $disabled_public_response->get_status(),
		'Turning the Answer Desk Integration off hides the widget and denies both public runtime paths'
	);

	$widget_off = array_merge( Settings::widget_defaults(), array( 'enabled' => false ) );
	$set_state( true, $widget_off );
	$widget_off_response = ( new ChatWidgetController() )->ask( new WP_REST_Request( 'POST' ) );
	$check(
		! Settings::answer_desk_enabled()
		&& ! ( new ChatWidget() )->should_render_widget()
		&& '' === trim( $render_widget() )
		&& 403 === $widget_off_response->get_status(),
		'Turning the Widget visibility off keeps Answer Desk unavailable without leaving a reachable launcher'
	);

	$incomplete_handoff_widget = array_merge(
		Settings::widget_defaults(),
		array(
			'enabled'                   => true,
			'handoff_enabled'           => true,
			'handoff_email_enabled'     => false,
			'handoff_telegram_enabled'  => false,
			'handoff_twilio_enabled'    => false,
			'handoff_email_to'          => '',
			'handoff_telegram_chat'     => '',
			'handoff_twilio_from'       => '',
			'handoff_twilio_to'         => '',
			'conversation_logging_enabled' => true,
		)
	);
	$set_state( true, $incomplete_handoff_widget );
	$incomplete_controller = new ChatWidgetController();
	$incomplete_settings   = $incomplete_controller->settings()->get_data();
	$handoff_response      = $incomplete_controller->handoff( new WP_REST_Request( 'POST' ) );
	$check(
		Settings::answer_desk_enabled()
		&& ! Settings::answer_desk_handoff_available()
		&& false === ( $incomplete_settings['handoff_enabled'] ?? true )
		&& false === strpos( $render_widget(), 'class="spw-handoff"' )
		&& 403 === $handoff_response->get_status()
		&& 'handoff_disabled' === ( $handoff_response->get_data()['code'] ?? '' ),
		'An incomplete handoff configuration exposes neither a visitor button nor a falsely enabled API'
	);

	$active_widget = array_merge(
		$incomplete_handoff_widget,
		array(
			'handoff_email_enabled' => true,
			'handoff_email_to'      => 'answer-desk-lifecycle@example.test',
			'greeting'              => 'Lifecycle greeting',
			'voice_name'            => 'Lifecycle voice',
			'voice_auto'            => true,
			'handoff_text'          => 'Lifecycle handoff',
			'handoff_hook'          => 'Lifecycle hook',
		)
	);
	$set_state( true, $active_widget );
	$active_controller = new ChatWidgetController();
	$active_settings   = $active_controller->settings()->get_data();
	$active_response   = $active_controller->ask( new WP_REST_Request( 'POST' ) );
	$active_markup     = $render_widget();
	$check(
		Settings::answer_desk_enabled()
		&& Settings::answer_desk_handoff_available()
		&& true === ( $active_settings['enabled'] ?? false )
		&& true === ( $active_settings['handoff_enabled'] ?? false )
		&& 1 === substr_count( $active_markup, 'id="spw-toggle"' )
		&& false !== strpos( $active_markup, 'class="spw-handoff"' )
		&& false === strpos( $active_markup, 'aisaautopilot-public-chat' )
		&& 400 === $active_response->get_status()
		&& 'empty' === ( $active_response->get_data()['code'] ?? '' ),
		'When both gates and one channel are configured, one full Answer Desk widget is live with logging and handoff contracts'
	);

	Plugin::instance()->enqueue_widget_assets();
	$localized_widget_data = (string) wp_scripts()->get_data( 'aisaautopilot-widget', 'data' );
	$check(
		false !== strpos( $localized_widget_data, 'Lifecycle greeting' )
		&& false !== strpos( $localized_widget_data, 'Lifecycle voice' )
		// T11 moved the handoff label and hook out of the script data: ChatWidget renders both
		// into the card and the footnote, so they are no longer pushed to every page view.
		// What the site owner saved must still reach the visitor, now through the markup.
		&& false === strpos( $localized_widget_data, 'Lifecycle handoff' )
		&& false === strpos( $localized_widget_data, 'Lifecycle hook' )
		&& false !== strpos( $active_markup, 'Lifecycle handoff' )
		&& false !== strpos( $active_markup, 'Lifecycle hook' ),
		'Frontend widget assets keep the persisted widget settings instead of fallback values: the greeting and voice name as script data, the handoff label and hook in the rendered card'
	);
} finally {
	if ( $original_settings === $missing ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original_settings, false );
	}
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
