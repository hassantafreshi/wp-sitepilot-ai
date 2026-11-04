<?php
declare(strict_types=1);

namespace AISAAutoPilot\Core;

/**
 * Integrations: tells the administrator when another chat or AI assistant plugin is active,
 * because two chat buttons can overlap in the same corner and two assistants can answer the
 * same visitor differently.
 *
 * Only plugins on a short, checked list are reported, matched by their WordPress.org folder
 * name (each slug below was confirmed through the WordPress.org plugin directory API on
 * 2026-11-04). Nothing is sent anywhere: the list of active plugins is read from this site.
 * The list can be extended with the `aisaautopilot_conflicting_plugins` filter.
 *
 * Each administrator can hide the warning for the plugins it names; another plugin from
 * the list activated later brings it back (a hidden plugin stays hidden). Hidden plugins
 * are kept per user and per site.
 */
final class ConflictDetector {

	public const FILTER = 'aisaautopilot_conflicting_plugins';

	/** Per-site user option (update_user_option adds the site's table prefix). */
	public const DISMISSED_OPTION = 'aisaautopilot_dismissed_conflicts';

	public const KIND_CHAT = 'chat_widget';

	public const KIND_AI = 'ai_assistant';

	/** At most this many hidden plugins are remembered per user. */
	private const MAX_DISMISSED = 50;

	/**
	 * WordPress.org folder => kind. A chat_widget puts its own chat button on the site;
	 * an ai_assistant is an AI chatbot or assistant plugin.
	 */
	private const KNOWN = array(
		'tidio-live-chat'                     => self::KIND_CHAT,
		'tawkto-live-chat'                    => self::KIND_CHAT,
		'crisp'                               => self::KIND_CHAT,
		'wp-live-chat-software-for-wordpress' => self::KIND_CHAT,
		'wp-live-chat-support'                => self::KIND_CHAT,
		'zopim-live-chat'                     => self::KIND_CHAT,
		'smartsupp-live-chat'                 => self::KIND_CHAT,
		'wp-whatsapp-chat'                    => self::KIND_CHAT,
		'click-to-chat-for-whatsapp'          => self::KIND_CHAT,
		'chaty'                               => self::KIND_CHAT,
		'social-chat-buttons'                 => self::KIND_CHAT,
		'ai-engine'                           => self::KIND_AI,
		'chatbot'                             => self::KIND_AI,
		'gpt3-ai-content-generator'           => self::KIND_AI,
	);

	/**
	 * The checked list plus anything a site adds through the filter, cleaned.
	 *
	 * @return array<string, string> folder => kind
	 */
	public static function known(): array {
		$list  = apply_filters( 'aisaautopilot_conflicting_plugins', self::KNOWN ); // Same name as self::FILTER.
		$clean = array();
		foreach ( is_array( $list ) ? $list : array() as $slug => $kind ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' !== $slug && in_array( $kind, array( self::KIND_CHAT, self::KIND_AI ), true ) ) {
				$clean[ $slug ] = $kind;
			}
		}

		return $clean;
	}

	/**
	 * Pure matcher: which active plugin files belong to a known folder.
	 *
	 * @param string[]              $active Plugin files such as "tidio-live-chat/tidio-elements.php".
	 * @param array<string, string> $known  folder => kind.
	 * @return array<string, string> plugin file => kind, in the order given.
	 */
	public static function match( array $active, array $known ): array {
		$found = array();
		foreach ( $active as $file ) {
			$file   = (string) $file;
			$folder = strtolower( (string) strstr( $file, '/', true ) );
			if ( '' !== $folder && isset( $known[ $folder ] ) && AISAAUTOPILOT_BASENAME !== $file ) {
				$found[ $file ] = $known[ $folder ];
			}
		}

		return $found;
	}

	/**
	 * Plugin files active on this site, network-activated ones included.
	 *
	 * @return string[]
	 */
	public static function active_plugins(): array {
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}

		return array_values( array_unique( array_map( 'strval', $active ) ) );
	}

	/**
	 * Every conflicting plugin that is active, with its display name.
	 *
	 * @return array<int, array{plugin:string, kind:string, name:string, network:bool}>
	 */
	public function detect(): array {
		$matches = self::match( self::active_plugins(), self::known() );
		if ( array() === $matches ) {
			return array();
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$headers  = get_plugins();
		$sitewide = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();
		$found    = array();
		foreach ( $matches as $file => $kind ) {
			$name    = isset( $headers[ $file ]['Name'] ) ? (string) $headers[ $file ]['Name'] : '';
			$found[] = array(
				'plugin'  => $file,
				'kind'    => $kind,
				'name'    => '' !== $name ? wp_strip_all_tags( $name ) : (string) strstr( $file, '/', true ),
				'network' => isset( $sitewide[ $file ] ),
			);
		}

		return $found;
	}

	/**
	 * What this administrator should be warned about now, grouped by kind. A chat button
	 * can only overlap when the Answer Desk chat button is on, so chat plugins are reported
	 * only then; AI assistants are always reported. Plugins the user hid are left out.
	 *
	 * @return array<string, array<int, array{plugin:string, kind:string, name:string, network:bool}>>
	 */
	public function warnings( int $user_id ): array {
		$hidden = self::dismissed( $user_id );
		$chat   = Settings::answer_desk_enabled();
		$groups = array();
		foreach ( $this->detect() as $plugin ) {
			if ( in_array( $plugin['plugin'], $hidden, true ) || ( self::KIND_CHAT === $plugin['kind'] && ! $chat ) ) {
				continue;
			}
			$groups[ $plugin['kind'] ][] = $plugin;
		}

		return $groups;
	}

	/** @return string[] Plugin files this user hid on this site. */
	public static function dismissed( int $user_id ): array {
		$stored = get_user_option( self::DISMISSED_OPTION, $user_id );

		return is_array( $stored ) ? array_values( array_filter( array_map( 'strval', $stored ) ) ) : array();
	}

	/**
	 * Hides the warning for these plugins. Only plugins detected right now are accepted,
	 * so the stored list holds nothing but real plugin files.
	 *
	 * @param string[] $plugins
	 * @return string[] The plugin files now hidden.
	 */
	public function dismiss( int $user_id, array $plugins ): array {
		$active = array_column( $this->detect(), 'plugin' );
		$add    = array_values( array_intersect( array_map( 'strval', $plugins ), $active ) );
		if ( array() === $add ) {
			return array();
		}
		$hidden = array_values( array_unique( array_merge( self::dismissed( $user_id ), $add ) ) );
		update_user_option( $user_id, self::DISMISSED_OPTION, array_slice( $hidden, -self::MAX_DISMISSED ) );

		return $add;
	}
}
