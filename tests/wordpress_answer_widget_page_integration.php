<?php
/**
 * Local WordPress integration checks for the mockup-aligned Answer Desk Widget tab.
 *
 * This exercises the canonical entry route, truthful control contract and the
 * custom-palette lock without contacting a provider or visitor endpoint.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\AnswerConversationsPage;
use AISAAutoPilot\Admin\AnswerDeskNavigation;
use AISAAutoPilot\Core\Settings;

$missing           = new stdClass();
$original_settings = get_option( Settings::OPTION, $missing );
$original_get      = $_GET;
$original_user_id  = get_current_user_id();
$checks            = array();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$render_widget = static function (): string {
	$_GET['tab'] = 'widget';
	ob_start();
	( new AnswerConversationsPage() )->render();
	return (string) ob_get_clean();
};

try {
	wp_set_current_user( 1 );
	Settings::update(
		array(
			'public_assistant_enabled' => true,
			'widget'                   => array_merge(
				Settings::widget_defaults(),
				array(
					'enabled'       => true,
					'color'         => '#ff455f',
					'title'         => 'Widget test title',
					'subtitle'      => 'Widget test subtitle',
					'custom_colors' => false,
				)
			),
		)
	);

	$tabs   = AnswerDeskNavigation::subtabs( AnswerDeskNavigation::TAB_WIDGET );
	$markup = $render_widget();
	$check(
		array( 'conversations', 'widget', 'handoff', 'voice' ) === array_column( $tabs, 'id' )
		&& false !== strpos( (string) $tabs[1]['url'], 'page=aisaautopilot-conversations&tab=widget' )
		&& false !== strpos( $markup, 'data-sp-answer-desk' )
		&& false !== strpos( $markup, 'data-sp-answer-tab="widget"' ),
		'Widget is served from the canonical Answer Desk route with the four mockup tabs in order'
	);
	$check(
		false !== strpos( $markup, 'data-sp-answer-widget-controls' )
		&& false !== strpos( $markup, 'data-sp-widget-enabled' )
		&& false !== strpos( $markup, 'data-sp-widget-color="#ff455f"' )
		&& false !== strpos( $markup, 'data-sp-widget-preview' )
		&& false === strpos( $markup, 'name="widget[voice_enabled]"' )
		&& false === strpos( $markup, 'name="widget[handoff_email_to]"' ),
		'Widget tab keeps mockup controls and preview while leaving deep voice and handoff settings out of this surface'
	);
	$check(
		false !== strpos( $markup, 'Sources appear automatically, but only when a response contains a real saved citation.' )
		&& false !== strpos( $markup, 'Automatic theme detection is not available.' )
		&& false === strpos( $markup, 'Match my theme colours</strong><button' ),
		'Widget tab does not present unsupported source or theme controls as working toggles'
	);

	Settings::update(
		array(
			'widget' => array(
				'custom_colors' => true,
				'colors'        => array( 'head_from' => '#14152b' ),
			),
		)
	);
	$custom_markup = $render_widget();
	$check(
		false !== strpos( $custom_markup, 'data-sp-widget-custom-colors="1"' )
		&& false !== strpos( $custom_markup, 'data-sp-widget-custom-warning' )
		&& false !== strpos( $custom_markup, 'An advanced custom palette is active' ),
		'Widget tab locks standard swatches when an existing advanced custom palette would override them'
	);
} finally {
	$_GET = $original_get;
	if ( $original_settings === $missing ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original_settings, false );
	}
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
