<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

/**
 * Shared sub-navigation for Overview (D-23, 2026-09-17).
 *
 * Usage & Cost, Health and Activity Log used to be separate top-level admin
 * pages; the owner asked for them to become tabs under Overview instead, so
 * the visible sidebar drops from ten items to eight. Each page keeps its own
 * slug, class and URL — nothing about how they render or what they store
 * changed — this class only adds the shared tab bar every one of the four
 * renders at its own route, exactly like `ContentStudioNavigation` does for
 * Content Studio's Write/Templates/History.
 */
final class OverviewNavigation {

	public const TAB_OVERVIEW = 'overview';
	public const TAB_USAGE    = 'usage';
	public const TAB_HEALTH   = 'health';
	public const TAB_LOGS     = 'logs';

	/**
	 * Resolve which tab a request is on from its own admin page slug — each
	 * tab is still its own real page (and its own bookmarkable URL), not a
	 * `tab=` query argument on a single shared route.
	 */
	public static function current_view(): string {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only route selection.
		switch ( $page ) {
			case AdminMenu::SLUG_USAGE:
				return self::TAB_USAGE;
			case AdminMenu::SLUG_CONNECTIONS:
				return self::TAB_HEALTH;
			case AdminMenu::SLUG_LOGS:
				return self::TAB_LOGS;
			default:
				return self::TAB_OVERVIEW;
		}
	}

	/** Canonical admin URL for one of the four Overview tabs. */
	public static function url( string $tab ): string {
		$slug = self::slug_for( $tab );

		return admin_url( 'admin.php?page=' . $slug );
	}

	public static function render( string $current ): void {
		$items = self::items();
		?>
		<nav class="sp-tabs sp-tabs--boxed sp-overview-tabs" aria-label="<?php esc_attr_e( 'Overview sections', 'ai-site-assistant-autopilot' ); ?>">
		<?php foreach ( $items as $key => $item ) : ?>
				<a class="sp-tabs__btn<?php echo $current === $key ? ' is-on' : ''; ?>" href="<?php echo esc_url( (string) $item[1] ); ?>" <?php echo $current === $key ? 'aria-current="page"' : ''; ?>>
			<?php echo esc_html( (string) $item[0] ); ?>
				</a>
		<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
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

	private static function slug_for( string $tab ): string {
		switch ( $tab ) {
			case self::TAB_USAGE:
				return AdminMenu::SLUG_USAGE;
			case self::TAB_HEALTH:
				return AdminMenu::SLUG_CONNECTIONS;
			case self::TAB_LOGS:
				return AdminMenu::SLUG_LOGS;
			default:
				return AdminMenu::SLUG_HOME;
		}
	}

	/**
	 * @return array<string, array{0:string,1:string}>
	 */
	private static function items(): array {
		return array(
			self::TAB_OVERVIEW => array( __( 'Overview', 'ai-site-assistant-autopilot' ), self::url( self::TAB_OVERVIEW ) ),
			self::TAB_USAGE    => array( __( 'Usage & Cost', 'ai-site-assistant-autopilot' ), self::url( self::TAB_USAGE ) ),
			self::TAB_HEALTH   => array( __( 'Health', 'ai-site-assistant-autopilot' ), self::url( self::TAB_HEALTH ) ),
			self::TAB_LOGS     => array( __( 'Activity Log', 'ai-site-assistant-autopilot' ), self::url( self::TAB_LOGS ) ),
		);
	}
}
