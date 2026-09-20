<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

/**
 * Shared sub-navigation for Content Studio.
 */
final class ContentStudioNavigation {


	public const TAB_WRITE     = 'write';
	public const TAB_TEMPLATES = 'templates';
	public const TAB_HISTORY   = 'history';
	public const VIEW_FORMS    = 'forms';

	/**
	 * Resolve the canonical Studio view while keeping the former mode URLs
	 * usable for bookmarks and integrations.
	 */
	public static function current_view(): string {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only route selection.
		if ( in_array( $tab, array( self::TAB_WRITE, self::TAB_TEMPLATES, self::TAB_HISTORY, self::VIEW_FORMS ), true ) ) {
			return $tab;
		}

		$mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- legacy read-only route selection.
		if ( 'templates' === $mode ) {
			return self::TAB_TEMPLATES;
		}
		if ( 'history' === $mode ) {
			return self::TAB_HISTORY;
		}
		if ( 'forms' === $mode ) {
			return self::VIEW_FORMS;
		}

		return self::TAB_WRITE;
	}

	/**
	 * Canonical Content Studio URL for one of the three visible mockup tabs.
	 */
	public static function url( string $tab ): string {
		if ( ! in_array( $tab, array( self::TAB_WRITE, self::TAB_TEMPLATES, self::TAB_HISTORY ), true ) ) {
			$tab = self::TAB_WRITE;
		}

		return admin_url( 'admin.php?page=' . AdminMenu::SLUG_FORM_BUILDER . '&tab=' . $tab );
	}

	/**
	 * Safe, editable hand-off from a built-in template to the Write brief.
	 */
	public static function write_url( string $brief = '', string $source_gap = '' ): string {
		$url = self::url( self::TAB_WRITE );
		if ( '' !== $brief ) {
			$url = add_query_arg( 'brief', rawurlencode( $brief ), $url );
		}
		// The unanswered question this draft answers; the draft is linked to it only after approval, server-side.
		if ( 1 === preg_match( '/^[a-f0-9]{32}$/', $source_gap ) ) {
			$url = add_query_arg( 'source_gap', $source_gap, $url );
		}

		return $url;
	}

	/**
	 * The unanswered-question hash passed by "Write it", or '' when absent or malformed. Whether the
	 * question is still open is checked by the caller and again by the content/plan route.
	 */
	public static function write_source_gap(): string {
		$raw = isset( $_GET['source_gap'] ) && is_string( $_GET['source_gap'] ) ? sanitize_key( wp_unslash( $_GET['source_gap'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only draft prefill.
		return 1 === preg_match( '/^[a-f0-9]{32}$/', $raw ) ? $raw : '';
	}

	/**
	 * Read a non-persistent template hand-off. It never creates or modifies a
	 * draft, and the eventual REST endpoint still applies its own 6,000-char
	 * validation before contacting a provider.
	 */
	public static function write_brief(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only draft prefill. Sanitised immediately below with wp_check_invalid_utf8 + wp_strip_all_tags, which keep the line breaks a brief needs; sanitize_text_field would collapse them.
		$raw = isset( $_GET['brief'] ) ? wp_unslash( $_GET['brief'] ) : '';
		if ( ! is_string( $raw ) ) {
			return '';
		}

		$brief = wp_check_invalid_utf8( $raw );
		$brief = wp_strip_all_tags( $brief, false );
		$brief = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $brief );
		if ( ! is_string( $brief ) ) {
			return '';
		}

		return mb_substr( trim( $brief ), 0, 6000 );
	}

	/**
	 * Form Studio (D-23, 2026-09-17; formerly "Form Composer") is its own
	 * top-level page, not a fourth Content Studio tab. The old deep link
	 * (`page=aisaautopilot-form-builder&mode=forms`) still renders the same
	 * content via `CopilotPage::render()`'s fallback, so bookmarks and any
	 * link built before this change keep resolving; every link generated from
	 * now on points at the canonical Form Studio slug instead.
	 */
	public static function forms_url(): string {
		return admin_url( 'admin.php?page=' . AdminMenu::SLUG_FORM_STUDIO );
	}

	public static function render( string $current ): void {
		$items = self::items();
		?>
		<nav class="sp-tabs sp-tabs--boxed sp-content-tabs" aria-label="<?php esc_attr_e( 'Content Studio sections', 'ai-site-assistant-autopilot' ); ?>">
		<?php foreach ( $items as $key => $item ) : ?>
				<a class="sp-tabs__btn<?php echo $current === $key ? ' is-on' : ''; ?>" href="<?php echo esc_url( (string) $item[1] ); ?>" <?php echo $current === $key ? 'aria-current="page"' : ''; ?>>
			<?php echo esc_html( (string) $item[0] ); ?>
				</a>
		<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Sub-tab definitions for the shared header bar (G-31 + G-05, mockup order).
	 *
	 * @return array<int, array{id:string,label:string,url:string,active:bool}>
	 */
	public static function subtabs( string $current ): array {
		$tabs = array();
		foreach ( self::items() as $key => $item ) {
			$tabs[] = array(
				'id'     => $key,
				'label'  => (string) $item[0],
				'url'    => (string) $item[1],
				'active' => $current === $key,
			);
		}
		return $tabs;
	}

	/**
	 * @return array<string, array{0:string,1:string}>
	 */
	private static function items(): array {
		return array(
			self::TAB_WRITE     => array( __( 'Write', 'ai-site-assistant-autopilot' ), self::url( self::TAB_WRITE ) ),
			self::TAB_TEMPLATES => array( __( 'Templates', 'ai-site-assistant-autopilot' ), self::url( self::TAB_TEMPLATES ) ),
			self::TAB_HISTORY   => array( __( 'History', 'ai-site-assistant-autopilot' ), self::url( self::TAB_HISTORY ) ),
		);
	}
}
