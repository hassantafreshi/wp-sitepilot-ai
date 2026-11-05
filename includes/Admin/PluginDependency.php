<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Connectors\WooCommerceConnector;

/**
 * The plugins some AutoPilot screens need: WooCommerce for Store Copilot and Easy Form
 * Builder for its editor copilot.
 *
 * Tells apart the three ways a needed plugin can be missing (not installed, installed but
 * not active, active but too old) so every screen offers the one step that fixes it, and
 * only when the current user may take that step. Installing uses WordPress's own installer
 * screen. Activating goes through a small admin-post handler that activates the plugin
 * and comes back to the AutoPilot screen the button was on.
 */
final class PluginDependency {

	public const WOOCOMMERCE = 'woocommerce';

	public const EFB = 'easy-form-builder';

	public const ACTION = 'aisaautopilot_activate_dependency';

	/** Query arguments the activation handler adds to the screen it returns to. */
	public const RESULT_ARG = 'aisaautopilot_dependency';

	public const RESULT_STATUS_ARG = 'aisaautopilot_dependency_result';

	/** WordPress.org slug => plugin name and its usual main file. */
	private const PLUGINS = array(
		self::WOOCOMMERCE => array(
			'name' => 'WooCommerce',
			'file' => 'woocommerce/woocommerce.php',
		),
		self::EFB         => array(
			'name' => 'Easy Form Builder',
			'file' => 'easy-form-builder/emsfb.php',
		),
	);

	/** AutoPilot screens the activation handler may return to. */
	private const RETURN_PAGES = array(
		AdminMenu::SLUG_WC_DASHBOARD,
		AdminMenu::SLUG_WC,
		AdminMenu::SLUG_EFB,
		AdminMenu::SLUG_INTEGRATIONS,
		AdminMenu::SLUG_SETUP,
		AdminMenu::SLUG_SETTINGS,
		AdminMenu::SLUG_FORM_STUDIO,
	);

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_activate' ) );
	}

	/**
	 * Where a needed plugin stands for this site and what this user can do about it.
	 *
	 * @return array{
	 *     id: string,
	 *     name: string,
	 *     state: string,
	 *     file: string,
	 *     detail: string,
	 *     action: array{label: string, url: string, kind: string}|null,
	 *     note: string
	 * } state is one of ready | missing | inactive | outdated.
	 */
	public static function status( string $id, string $return_page = '' ): array {
		$plugin = self::PLUGINS[ $id ] ?? null;
		if ( null === $plugin ) {
			return array(
				'id'     => $id,
				'name'   => $id,
				'state'  => 'ready',
				'file'   => '',
				'detail' => '',
				'action' => null,
				'note'   => '',
			);
		}

		// Translatable: some languages write the WooCommerce name in their own script.
		$name = self::WOOCOMMERCE === $id
			/* translators: The WooCommerce plugin's name, as WooCommerce's own translation writes it. */
			? __( 'WooCommerce', 'ai-site-assistant-autopilot' )
			: $plugin['name'];
		$detail = '';
		if ( self::WOOCOMMERCE === $id ) {
			$active = WooCommerceConnector::is_active();
			$ready  = $active;
		} else {
			$health = ( new EasyFormBuilderConnector() )->get_health();
			$active = ! empty( $health['active'] );
			$ready  = ! empty( $health['available'] );
			$detail = $active && ! $ready ? (string) ( $health['notes'] ?? '' ) : '';
		}
		$file = self::installed_file( $id );

		if ( $ready ) {
			$state = 'ready';
		} elseif ( $active ) {
			$state = 'outdated';
		} elseif ( '' !== $file ) {
			$state = 'inactive';
		} else {
			$state = 'missing';
		}

		$action = null;
		$note   = '';
		if ( 'missing' === $state ) {
			if ( current_user_can( 'install_plugins' ) ) {
				$action = array(
					/* translators: %s: plugin name, such as WooCommerce. */
					'label' => sprintf( __( 'Install %s', 'ai-site-assistant-autopilot' ), $name ),
					'url'   => wp_nonce_url( self::plugins_admin_url( 'update.php?action=install-plugin&plugin=' . rawurlencode( $id ) ), 'install-plugin_' . $id ),
					'kind'  => 'install',
				);
			} else {
				$note = is_multisite()
					/* translators: %s: plugin name, such as WooCommerce. */
					? sprintf( __( 'Ask your network administrator to install %s.', 'ai-site-assistant-autopilot' ), $name )
					/* translators: %s: plugin name, such as WooCommerce. */
					: sprintf( __( 'Ask an administrator who manages plugins to install %s.', 'ai-site-assistant-autopilot' ), $name );
			}
		} elseif ( 'inactive' === $state ) {
			if ( current_user_can( 'activate_plugin', $file ) ) {
				$action = array(
					/* translators: %s: plugin name, such as WooCommerce. */
					'label' => sprintf( __( 'Activate %s', 'ai-site-assistant-autopilot' ), $name ),
					'url'   => self::activate_url( $id, $return_page ),
					'kind'  => 'activate',
				);
			} else {
				$note = is_multisite()
					/* translators: %s: plugin name, such as WooCommerce. */
					? sprintf( __( 'Ask your network administrator to activate %s.', 'ai-site-assistant-autopilot' ), $name )
					/* translators: %s: plugin name, such as WooCommerce. */
					: sprintf( __( 'Ask an administrator who manages plugins to activate %s.', 'ai-site-assistant-autopilot' ), $name );
			}
		} elseif ( 'outdated' === $state ) {
			if ( current_user_can( 'update_plugins' ) ) {
				$action = array(
					/* translators: %s: plugin name, such as WooCommerce. */
					'label' => sprintf( __( 'Update %s', 'ai-site-assistant-autopilot' ), $name ),
					'url'   => self::plugins_admin_url( 'plugins.php?s=' . rawurlencode( $name ) ),
					'kind'  => 'update',
				);
			} else {
				$note = is_multisite()
					/* translators: %s: plugin name, such as WooCommerce. */
					? sprintf( __( 'Ask your network administrator to update %s.', 'ai-site-assistant-autopilot' ), $name )
					/* translators: %s: plugin name, such as WooCommerce. */
					: sprintf( __( 'Ask an administrator who manages plugins to update %s.', 'ai-site-assistant-autopilot' ), $name );
			}
		}

		return array(
			'id'     => $id,
			'name'   => $name,
			'state'  => $state,
			'file'   => $file,
			'detail' => $detail,
			'action' => $action,
			'note'   => $note,
		);
	}

	/**
	 * The installed main file of a needed plugin, or '' when it is not installed. The usual
	 * file is preferred; any plugin in the same folder counts, so a renamed main file is found.
	 */
	public static function installed_file( string $id ): string {
		if ( ! isset( self::PLUGINS[ $id ] ) ) {
			return '';
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		$usual   = self::PLUGINS[ $id ]['file'];
		if ( isset( $plugins[ $usual ] ) ) {
			return $usual;
		}
		foreach ( array_keys( $plugins ) as $file ) {
			if ( 0 === strpos( (string) $file, $id . '/' ) ) {
				return (string) $file;
			}
		}

		return '';
	}

	/** Nonce-protected link that activates a needed plugin and returns to $return_page. */
	public static function activate_url( string $id, string $return_page = '' ): string {
		$args = array(
			'action'     => self::ACTION,
			'dependency' => $id,
		);
		if ( in_array( $return_page, self::RETURN_PAGES, true ) ) {
			$args['return'] = $return_page;
		}

		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), self::ACTION . '_' . $id );
	}

	/**
	 * admin-post handler: activates WooCommerce or Easy Form Builder for this site after
	 * the same checks WordPress's Plugins screen makes, then returns to the AutoPilot screen.
	 */
	public function handle_activate(): void {
		$id = isset( $_GET['dependency'] ) ? sanitize_key( wp_unslash( $_GET['dependency'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified by check_admin_referer() below, which needs the id first.
		if ( ! isset( self::PLUGINS[ $id ] ) ) {
			wp_die( esc_html__( 'This plugin cannot be activated from here.', 'ai-site-assistant-autopilot' ), '', array( 'response' => 400 ) );
		}
		check_admin_referer( self::ACTION . '_' . $id );

		$return = isset( $_GET['return'] ) ? sanitize_key( wp_unslash( $_GET['return'] ) ) : '';
		if ( ! in_array( $return, self::RETURN_PAGES, true ) ) {
			$return = AdminMenu::SLUG_INTEGRATIONS;
		}

		$file = self::installed_file( $id );
		if ( '' === $file || ! current_user_can( 'manage_options' ) || ! current_user_can( 'activate_plugin', $file ) ) {
			wp_die( esc_html__( 'You do not have permission to activate this plugin.', 'ai-site-assistant-autopilot' ), '', array( 'response' => 403 ) );
		}

		$result = is_plugin_active( $file ) ? null : activate_plugin( $file );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                  => $return,
					self::RESULT_ARG        => $id,
					self::RESULT_STATUS_ARG => is_wp_error( $result ) ? 'failed' : 'activated',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * After the activation handler returns: a short confirmation, or why it did not work.
	 * Shown only when the plugin's state agrees, so a hand-made link cannot claim success.
	 */
	public static function render_result_notice(): void {
		$id     = isset( $_GET[ self::RESULT_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT_ARG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice; the state is re-read below.
		$result = isset( $_GET[ self::RESULT_STATUS_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT_STATUS_ARG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice.
		if ( ! isset( self::PLUGINS[ $id ] ) ) {
			return;
		}
		$status = self::status( $id );
		$name   = $status['name'];
		if ( 'activated' === $result && 'inactive' !== $status['state'] && 'missing' !== $status['state'] ) {
			?>
			<div class="sp-alert sp-alert--ok sp-dep-result" role="status" data-sp-dependency-result="activated">
				<?php echo Ui::icon( 'check-circle', 20, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
				<span>
				<?php
				echo esc_html(
					'ready' === $status['state']
						/* translators: %s: plugin name, such as WooCommerce. */
						? sprintf( __( '%s is active. You can use it here now.', 'ai-site-assistant-autopilot' ), $name )
						/* translators: %s: plugin name, such as WooCommerce. */
						: sprintf( __( '%s is active, but this version still needs an update.', 'ai-site-assistant-autopilot' ), $name )
				);
				?>
				</span>
			</div>
			<?php
		} elseif ( 'failed' === $result && 'ready' !== $status['state'] ) {
			?>
			<div class="sp-alert sp-alert--err sp-dep-result" role="alert" data-sp-dependency-result="failed">
				<?php echo Ui::icon( 'alert-circle', 20, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
				<span>
				<?php
				echo esc_html(
					/* translators: %s: plugin name, such as WooCommerce. */
					sprintf( __( 'WordPress could not activate %s. Open the Plugins screen to see why.', 'ai-site-assistant-autopilot' ), $name )
				);
				?>
				<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Open Plugins', 'ai-site-assistant-autopilot' ); ?></a>
				</span>
			</div>
			<?php
		}
	}

	/**
	 * The full "this screen needs another plugin" state.
	 *
	 * @param array<string, mixed> $status From status().
	 * @param array{
	 *     feature: string,
	 *     lead: string,
	 *     benefits?: string[],
	 *     reassurance?: string,
	 *     attrs?: array<string, string>
	 * } $copy feature is a short name such as "Store Copilot"; lead says what it does with the plugin.
	 */
	public static function render_required( array $status, array $copy ): void {
		$id       = (string) $status['id'];
		$name     = (string) $status['name'];
		$state    = (string) $status['state'];
		$action   = is_array( $status['action'] ) ? $status['action'] : null;
		$feature  = (string) $copy['feature'];
		$benefits = array_values( array_filter( array_map( 'strval', (array) ( $copy['benefits'] ?? array() ) ) ) );
		$title_id = 'sp-dep-' . $id . '-title';

		$why = self::why( $status );
		if ( 'missing' === $state ) {
			/* translators: 1: plugin name, such as WooCommerce, 2: AutoPilot feature, such as Store Copilot. */
			$title = sprintf( __( 'Install %1$s to use %2$s', 'ai-site-assistant-autopilot' ), $name, $feature );
		} elseif ( 'inactive' === $state ) {
			/* translators: 1: plugin name, such as WooCommerce, 2: AutoPilot feature, such as Store Copilot. */
			$title = sprintf( __( 'Activate %1$s to use %2$s', 'ai-site-assistant-autopilot' ), $name, $feature );
		} else {
			/* translators: 1: plugin name, such as WooCommerce, 2: AutoPilot feature, such as Store Copilot. */
			$title = sprintf( __( 'Update %1$s to use %2$s', 'ai-site-assistant-autopilot' ), $name, $feature );
		}

		$attrs = '';
		foreach ( (array) ( $copy['attrs'] ?? array() ) as $attr => $value ) {
			$attrs .= ' ' . sanitize_key( (string) $attr ) . '="' . esc_attr( (string) $value ) . '"';
		}
		?>
		<section class="sp-dep" data-sp-dependency="<?php echo esc_attr( $id ); ?>" data-sp-dependency-state="<?php echo esc_attr( $state ); ?>" aria-labelledby="<?php echo esc_attr( $title_id ); ?>"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each name is sanitize_key()ed and each value esc_attr()ed above. ?>>
			<span class="sp-dep__icon" aria-hidden="true"><?php echo Ui::icon( 'plug', 26, 'currentColor', 1.8 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
			<p class="sp-dep__eyebrow">
			<?php
			/* translators: %s: required plugin name. */
			echo esc_html( sprintf( __( 'Needs %s', 'ai-site-assistant-autopilot' ), $name ) );
			?>
			</p>
			<h2 class="sp-dep__title" id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $title ); ?></h2>
			<p class="sp-dep__lead"><?php echo esc_html( $why . ' ' . (string) $copy['lead'] ); ?></p>
			<?php if ( array() !== $benefits ) : ?>
				<ul class="sp-dep__list">
					<?php foreach ( $benefits as $benefit ) : ?>
						<li><?php echo Ui::icon( 'check', 15, 'currentColor', 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><span><?php echo esc_html( $benefit ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<div class="sp-dep__actions">
				<?php if ( null !== $action ) : ?>
					<a class="sp-btn sp-dep__primary" href="<?php echo esc_url( (string) $action['url'] ); ?>" data-sp-dependency-action="<?php echo esc_attr( (string) $action['kind'] ); ?>"><?php echo esc_html( (string) $action['label'] ); ?></a>
				<?php endif; ?>
				<a class="sp-dep__link" href="<?php echo esc_url( 'https://wordpress.org/plugins/' . $id . '/' ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'About it on WordPress.org', 'ai-site-assistant-autopilot' ); ?>
					<?php echo Ui::icon( 'external', 13, 'currentColor', 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
					<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'ai-site-assistant-autopilot' ); ?></span>
				</a>
			</div>
			<?php if ( '' !== (string) $status['note'] ) : ?>
				<p class="sp-dep__note" data-sp-dependency-note><?php echo esc_html( (string) $status['note'] ); ?></p>
			<?php elseif ( null !== $action && 'install' === $action['kind'] ) : ?>
				<p class="sp-dep__note"><?php esc_html_e( 'WordPress installs it from WordPress.org. Activate it on the next screen, then come back here.', 'ai-site-assistant-autopilot' ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== (string) ( $copy['reassurance'] ?? '' ) ) : ?>
				<p class="sp-dep__foot"><?php echo esc_html( (string) $copy['reassurance'] ); ?></p>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * One sentence on why the plugin is not usable yet.
	 *
	 * @param array<string, mixed> $status From status().
	 */
	public static function why( array $status ): string {
		$name = (string) $status['name'];
		switch ( (string) $status['state'] ) {
			case 'missing':
				/* translators: %s: plugin name, such as WooCommerce. */
				return sprintf( __( '%s is not installed on this site yet.', 'ai-site-assistant-autopilot' ), $name );
			case 'inactive':
				/* translators: %s: plugin name, such as WooCommerce. */
				return sprintf( __( '%s is installed on this site but is not active.', 'ai-site-assistant-autopilot' ), $name );
			case 'outdated':
				return '' !== (string) $status['detail']
					? (string) $status['detail']
					/* translators: %s: plugin name, such as WooCommerce. */
					: sprintf( __( 'This version of %s is not supported yet.', 'ai-site-assistant-autopilot' ), $name );
			default:
				return '';
		}
	}

	/** Plugin installs and updates live in the network admin on a multisite. */
	private static function plugins_admin_url( string $path ): string {
		return is_multisite() ? network_admin_url( $path ) : admin_url( $path );
	}
}
