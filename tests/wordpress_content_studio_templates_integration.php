<?php
/**
 * Local WordPress integration checks for Content Studio's built-in templates.
 *
 * Templates are static, non-persistent writing starters. This test confirms
 * their only hand-off is a safe, editable Write prefill; no provider is used.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Admin\ContentStudioNavigation;
use AISAAutoPilot\Admin\CopilotPage;

$original_get     = $_GET;
$original_user_id = get_current_user_id();
$checks           = array();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$render = static function ( array $query ): string {
	$_GET = $query; // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited -- isolated local renderer assertion.
	ob_start();
	( new CopilotPage() )->render();
	return (string) ob_get_clean();
};

try {
	wp_set_current_user( 1 );
	$template_brief = 'Outline a post on {topic} for {audience}. Six headings, one sentence each.';
	$template_url   = ContentStudioNavigation::write_url( $template_brief );
	$template_query = array();
	parse_str( (string) wp_parse_url( $template_url, PHP_URL_QUERY ), $template_query );
	$escaped_template_url   = html_entity_decode( esc_url( $template_url ), ENT_QUOTES, 'UTF-8' );
	$escaped_template_query = array();
	parse_str( (string) wp_parse_url( $escaped_template_url, PHP_URL_QUERY ), $escaped_template_query );
	$templates_html = $render(
		array(
			'page' => AdminMenu::SLUG_FORM_BUILDER,
			'tab'  => 'templates',
		)
	);
	$check(
		4 === substr_count( $templates_html, 'data-content-template' )
		&& 4 === substr_count( $templates_html, 'Built-in' )
		&& 4 === substr_count( $templates_html, 'Use template' )
		&& false !== strpos( $templates_html, 'Product description' )
		&& false !== strpos( $templates_html, 'Blog post outline' )
		&& false !== strpos( $templates_html, 'Meta description' )
		&& false !== strpos( $templates_html, 'Support answer' ),
		'Templates renders the four mockup writing starters with truthful built-in provenance'
	);
	$check(
		false !== strpos( $templates_html, 'Write 60–90 words for {product}.' )
		&& false !== strpos( $templates_html, 'Summarise {page} in under 155 characters.' )
		&& false === strpos( $templates_html, 'Used 38 times' )
		&& false === strpos( $templates_html, 'Edit template' ),
		'Templates preserves mockup-style mono briefs without inventing usage counts or a persistent editor'
	);
	$check(
		AdminMenu::SLUG_FORM_BUILDER === ( $template_query['page'] ?? '' )
		&& ContentStudioNavigation::TAB_WRITE === ( $template_query['tab'] ?? '' )
		&& $template_brief === ( $template_query['brief'] ?? '' )
		&& $template_brief === ( $escaped_template_query['brief'] ?? '' )
		&& false !== strpos( $templates_html, esc_url( $template_url ) ),
		'Each template preserves placeholder braces through its escaped canonical Write URL'
	);
	$template_write_html = $render(
		array(
			'page'  => AdminMenu::SLUG_FORM_BUILDER,
			'tab'   => 'write',
			'brief' => $template_brief,
		)
	);
	$template_prefill = ContentStudioNavigation::write_brief();
	$check(
		$template_brief === $template_prefill
		&& false !== strpos( $template_write_html, esc_textarea( $template_brief ) ),
		'Write preserves the mockup placeholder braces in a built-in template prefill'
	);

	$unsafe_brief = '<script>alert(1)</script>{product}' . str_repeat( 'a', 6100 );
	$write_html   = $render(
		array(
			'page'  => AdminMenu::SLUG_FORM_BUILDER,
			'tab'   => 'write',
			'brief' => $unsafe_brief,
		)
	);
	$prefill      = ContentStudioNavigation::write_brief();
	$check(
		mb_strlen( $prefill ) <= 6000
		&& false === strpos( $prefill, '<script' )
		&& false === strpos( $prefill, 'alert(1)' )
		&& false !== strpos( $prefill, '{product}' )
		&& false === strpos( $write_html, '<script>alert(1)</script>' )
		&& false !== strpos( $write_html, esc_textarea( $prefill ) )
		&& false !== strpos( $write_html, 'data-content-compose' ),
		'Write sanitizes and bounds a template-style prefill without planning or creating any draft'
	);
} finally {
	$_GET = $original_get; // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited -- restore global after isolated local assertion.
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
