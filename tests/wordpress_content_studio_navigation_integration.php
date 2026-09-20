<?php
/**
 * Read-only local WordPress checks for Content Studio's canonical navigation.
 *
 * The mockup permits exactly Write, Templates, and History. Form Studio
 * (D-23, 2026-09-17; formerly "Form Composer") is its own top-level page;
 * its old Content Studio deep link is deliberately retained for compatibility,
 * never as a fourth Studio tab.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Admin\ContentStudioNavigation;
use AISAAutoPilot\Admin\CopilotPage;
use AISAAutoPilot\Admin\HistoryPage;
use AISAAutoPilot\Admin\NativeFormsPage;

$original_get     = $_GET;
$original_user_id = get_current_user_id();
$checks           = array();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$render = static function ( object $screen, array $query ): string {
	$_GET = $query; // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited -- isolated local renderer assertion.
	ob_start();
	$screen->render();
	return (string) ob_get_clean();
};

$scripts_for = static function ( array $query ): array {
	$_GET = $query; // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited -- isolated asset-route assertion.
	wp_styles()->queue  = array();
	wp_scripts()->queue = array();
	( new AdminMenu() )->enqueue_assets( 'aisaautopilot-navigation-contract' );
	return wp_scripts()->queue;
};

try {
	wp_set_current_user( 1 );

	$tabs      = ContentStudioNavigation::subtabs( ContentStudioNavigation::TAB_WRITE );
	$tab_ids   = array_column( $tabs, 'id' );
	$tab_urls  = array_column( $tabs, 'url' );
	$check(
		array( 'write', 'templates', 'history' ) === $tab_ids
		&& false !== strpos( $tab_urls[0], 'tab=write' )
		&& false !== strpos( $tab_urls[1], 'tab=templates' )
		&& false !== strpos( $tab_urls[2], 'tab=history' ),
		'Content Studio declares exactly the three canonical mockup tabs and URLs'
	);

	$write     = $render( new CopilotPage(), array( 'page' => AdminMenu::SLUG_FORM_BUILDER, 'tab' => 'write' ) );
	$templates = $render( new CopilotPage(), array( 'page' => AdminMenu::SLUG_FORM_BUILDER, 'tab' => 'templates' ) );
	$history   = $render( new CopilotPage(), array( 'page' => AdminMenu::SLUG_FORM_BUILDER, 'tab' => 'history' ) );
	$write_at  = strpos( $write, 'data-sp-subtab="write"' );
	$templ_at  = strpos( $write, 'data-sp-subtab="templates"' );
	$hist_at   = strpos( $write, 'data-sp-subtab="history"' );
	$check(
		false !== $write_at && false !== $templ_at && false !== $hist_at
		&& $write_at < $templ_at && $templ_at < $hist_at
		&& 3 === substr_count( $write, 'data-sp-subtab=' )
		&& false !== strpos( $write, 'data-sp-content-tab="write"' )
		&& false !== strpos( $templates, 'data-sp-content-tab="templates"' )
		&& false !== strpos( $history, 'data-sp-content-tab="history"' ),
		'Canonical Write, Templates, and History routes share the three-tab Studio shell in mockup order'
	);

	$legacy_write     = $render( new CopilotPage(), array( 'page' => AdminMenu::SLUG_FORM_BUILDER, 'mode' => 'content' ) );
	$legacy_templates = $render( new CopilotPage(), array( 'page' => AdminMenu::SLUG_FORM_BUILDER, 'mode' => 'templates' ) );
	$legacy_history   = $render( new HistoryPage(), array( 'page' => AdminMenu::SLUG_HISTORY ) );
	$check(
		false !== strpos( $legacy_write, 'data-sp-content-tab="write"' )
		&& false !== strpos( $legacy_templates, 'data-sp-content-tab="templates"' )
		&& false !== strpos( $legacy_history, 'data-sp-content-tab="history"' )
		&& 3 === substr_count( $legacy_history, 'data-sp-subtab=' ),
		'Legacy content, templates, and history URLs resolve to the canonical Studio surfaces'
	);

	$form_studio_legacy_link = $render( new CopilotPage(), array( 'page' => AdminMenu::SLUG_FORM_BUILDER, 'mode' => 'forms' ) );
	// The real top-level Form Studio page is registered straight against
	// `render_forms()` (see AdminMenu::register()), bypassing `render()`'s
	// tab/mode detection entirely, so it is called the same way here.
	$_GET                    = array( 'page' => AdminMenu::SLUG_FORM_STUDIO ); // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited -- isolated local renderer assertion.
	ob_start();
	( new CopilotPage() )->render_forms();
	$form_studio_top_level = (string) ob_get_clean();
	$native_forms          = $render( new NativeFormsPage(), array( 'page' => AdminMenu::SLUG_FORMS ) );
	$check(
		false !== strpos( $form_studio_legacy_link, 'data-aisaautopilot-copilot' )
		&& false === strpos( $form_studio_legacy_link, 'data-sp-content-studio' )
		&& false === strpos( $form_studio_legacy_link, 'data-sp-subtab=' )
		&& false !== strpos( $form_studio_legacy_link, 'Form Studio' )
		&& $form_studio_legacy_link === $form_studio_top_level
		&& false !== strpos( $native_forms, 'page=' . AdminMenu::SLUG_FORM_STUDIO ),
		'Form Studio (D-23; formerly "Form Composer") is a real top-level page and its legacy Content Studio deep link renders identically, without becoming a fourth Studio tab'
	);

	$write_scripts     = $scripts_for( array( 'page' => AdminMenu::SLUG_FORM_BUILDER, 'tab' => 'write' ) );
	$templates_scripts = $scripts_for( array( 'page' => AdminMenu::SLUG_FORM_BUILDER, 'mode' => 'templates' ) );
	$history_scripts   = $scripts_for( array( 'page' => AdminMenu::SLUG_FORM_BUILDER, 'tab' => 'history' ) );
	$forms_scripts     = $scripts_for( array( 'page' => AdminMenu::SLUG_FORM_BUILDER, 'mode' => 'forms' ) );
	$check(
		in_array( 'aisaautopilot-content-studio', $write_scripts, true )
		&& in_array( 'aisaautopilot-content-studio', $templates_scripts, true )
		&& in_array( 'aisaautopilot-shell', $history_scripts, true )
		&& ! in_array( 'aisaautopilot-logs', $history_scripts, true )
		&& ! in_array( 'aisaautopilot-content-studio', $history_scripts, true )
		&& ! in_array( 'aisaautopilot-form-builder', $history_scripts, true )
		&& in_array( 'aisaautopilot-form-builder', $forms_scripts, true )
		&& ! in_array( 'aisaautopilot-form-builder', $write_scripts, true )
		&& ! in_array( 'aisaautopilot-content-studio', $forms_scripts, true ),
		'Write and Templates receive their matching script while History keeps only the shared shell and the legacy Form Studio deep link stays isolated'
	);
} finally {
	$_GET = $original_get;
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
