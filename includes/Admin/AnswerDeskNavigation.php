<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

/**
 * Shared sub-navigation for the Answer Desk surfaces.
 */
final class AnswerDeskNavigation {


	public const TAB_CONVERSATIONS = 'conversations';
	public const TAB_WIDGET        = 'widget';
	public const TAB_HANDOFF       = 'handoff';
	public const TAB_VOICE         = 'voice';

	public static function render( string $current ): void {
		$items = self::items();
		?>
		<nav class="sp-tabs sp-tabs--boxed sp-content-tabs" aria-label="<?php esc_attr_e( 'Answer Desk sections', 'ai-site-assistant-autopilot' ); ?>">
		<?php foreach ( $items as $key => $item ) : ?>
				<a class="sp-tabs__btn<?php echo $current === $key ? ' is-on' : ''; ?>" href="<?php echo esc_url( (string) $item[1] ); ?>" <?php echo $current === $key ? 'aria-current="page"' : ''; ?>>
			<?php echo esc_html( (string) $item[0] ); ?>
				</a>
		<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Sub-tab definitions for the shared header bar (G-23, mockup order).
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

	public static function url( string $tab ): string {
		if ( self::TAB_WIDGET === $tab ) {
			return admin_url( 'admin.php?page=' . AdminMenu::SLUG_CONVERSATIONS . '&tab=widget' );
		}
		if ( self::TAB_CONVERSATIONS === $tab ) {
			return admin_url( 'admin.php?page=' . AdminMenu::SLUG_CONVERSATIONS . '&tab=conversations' );
		}
		if ( self::TAB_HANDOFF === $tab ) {
			return admin_url( 'admin.php?page=' . AdminMenu::SLUG_CONVERSATIONS . '&tab=handoff' );
		}

		return admin_url( 'admin.php?page=' . AdminMenu::SLUG_CONVERSATIONS . '&tab=voice' );
	}

	/**
	 * @return array<string, array{0:string,1:string}>
	 */
	private static function items(): array {
		return array(
			self::TAB_CONVERSATIONS => array( __( 'Conversations', 'ai-site-assistant-autopilot' ), self::url( self::TAB_CONVERSATIONS ) ),
			self::TAB_WIDGET        => array( __( 'Widget', 'ai-site-assistant-autopilot' ), self::url( self::TAB_WIDGET ) ),
			self::TAB_HANDOFF       => array( __( 'Human handoff', 'ai-site-assistant-autopilot' ), self::url( self::TAB_HANDOFF ) ),
			self::TAB_VOICE         => array( __( 'Real-time voice', 'ai-site-assistant-autopilot' ), self::url( self::TAB_VOICE ) ),
		);
	}
}
