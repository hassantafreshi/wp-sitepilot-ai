<?php
/**
 * Local WordPress contracts for the Easy Form Builder copilot details page.
 *
 * Proves the page lists exactly the operations the customise skill executes, every area the
 * capability catalog refuses, catalog-derived counts and the last applied change from the
 * activity log. The log option, the user and the locale are restored in finally.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\EasyFormBuilderPage;
use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Efb\EfbCustomizeSkill;
use AISAAutoPilot\Logging\Logger;

const EFB_DETAILS_LOG_OPTION = 'aisaautopilot_logs';

$missing       = new stdClass();
$original_logs = get_option( EFB_DETAILS_LOG_OPTION, $missing );
$original_user = get_current_user_id();
$checks        = array();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$render = static function (): string {
	ob_start();
	( new EasyFormBuilderPage() )->render();
	return (string) ob_get_clean();
};

try {
	wp_set_current_user( 1 );
	switch_to_locale( 'en_US' );

	$ready   = ! empty( ( new EasyFormBuilderConnector() )->get_health()['available'] );
	$catalog = json_decode( (string) file_get_contents( AISAAUTOPILOT_DIR . 'includes/Efb/efb-capability-catalog.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- bundled plugin asset.
	$blocked = count( (array) ( $catalog['safe_ai_contract']['blocked'] ?? array() ) );
	$writable = 0;
	foreach ( array( 'field_properties', 'form_properties' ) as $section ) {
		foreach ( (array) ( $catalog[ $section ] ?? array() ) as $entry ) {
			if ( is_array( $entry ) && true === ( $entry['ai_writable'] ?? false ) ) {
				++$writable;
			}
		}
	}

	update_option( EFB_DETAILS_LOG_OPTION, array(), false );
	$html = $render();
	$check( false !== strpos( $html, 'data-sp-efb-state="' . ( $ready ? 'available' : 'unavailable' ) . '"' ), 'The page states the real Easy Form Builder connection' );
	$check( count( EfbCustomizeSkill::OPS ) === substr_count( $html, 'data-sp-efb-op="' ), 'Every operation the copilot executes is listed, and nothing more' );
	foreach ( EfbCustomizeSkill::OPS as $op ) {
		if ( false === strpos( $html, 'data-sp-efb-op="' . $op . '"' ) ) {
			$check( false, 'Operation ' . $op . ' is listed' );
		}
	}
	// A new operation without a label once rendered as an untitled group of blank rows.
	preg_match_all( '/<span class="sp-efbx-row__label">([^<]*)<\/span>/', $html, $row_labels );
	preg_match_all( '/<h3 class="sp-efbx-group">([^<]*)<\/h3>/', $html, $group_titles );
	$check(
		array() !== $row_labels[1] && array() === array_filter( $row_labels[1], static fn( string $label ): bool => '' === trim( $label ) )
			&& array() !== $group_titles[1] && array() === array_filter( $group_titles[1], static fn( string $title ): bool => '' === trim( $title ) ),
		'Every listed operation has a label and every group a title'
	);
	$raw_blocked = array_filter(
		(array) ( $catalog['safe_ai_contract']['blocked'] ?? array() ),
		static fn( $item ): bool => in_array( esc_html( (string) $item ), $row_labels[1], true )
	);
	$check( array() === $raw_blocked, 'Every area the catalog refuses has its own translatable label, not the raw catalog key' . ( $raw_blocked ? ': ' . implode( ', ', $raw_blocked ) : '' ) );
	$check( $blocked > 0 && $blocked === substr_count( $html, 'data-sp-efb-blocked' ), 'Every area the catalog refuses is listed as never' );
	$check( false !== strpos( $html, 'Nothing applied yet.' ), 'Without an applied change the page says so honestly' );
	if ( $ready ) {
		$check(
			false !== strpos( $html, 'data-sp-efb-writable="' . $writable . '"' ) && false !== strpos( $html, 'page=Emsfb' ),
			'A connected page shows the catalog AI-writable count and links to the Easy Form Builder forms'
		);
	} else {
		$check(
			false !== strpos( $html, 'plugin-install.php' ) || false !== strpos( $html, 'plugins.php?s=easy+form+builder' ),
			'An unavailable connection offers to install, activate or update Easy Form Builder'
		);
	}

	( new Logger() )->info( 'efb.apply', 'Made “Message” required.', array( 'trace_id' => 'trace-qa-7f3a' ) );
	$applied = $render();
	$check(
		false !== strpos( $applied, 'Made “Message” required.' ) && false !== strpos( $applied, 'trace-qa-7f3a' ) && false === strpos( $applied, 'Nothing applied yet.' ),
		'The last applied change shows its message and trace from the activity log'
	);

	if ( switch_to_locale( 'fa_IR' ) ) {
		$fa = $render();
		restore_previous_locale();
		$check( 1 === preg_match( '/<h2 class="sp-efbx-card__title" id="sp-efbx-ops-title">[^<]*[\x{0600}-\x{06FF}]/u', $fa ), 'fa_IR renders the details page in Persian' );
	} else {
		$check( false, 'fa_IR is available for the details page copy check' );
	}
} catch ( Throwable $error ) {
	$check( false, 'Easy Form Builder details contracts complete without a fatal error: ' . $error->getMessage() );
} finally {
	if ( $original_logs === $missing ) {
		delete_option( EFB_DETAILS_LOG_OPTION );
	} else {
		update_option( EFB_DETAILS_LOG_OPTION, $original_logs, false );
	}
	while ( is_locale_switched() ) {
		restore_previous_locale();
	}
	wp_set_current_user( $original_user );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
