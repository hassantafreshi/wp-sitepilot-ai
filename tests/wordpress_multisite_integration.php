<?php
/**
 * Local WordPress multisite checks for activation, site deletion and uninstall.
 *
 * Needs a multisite network of its own (AISAAUTOPILOT_WP_MS_ROOT, subdirectory install at
 * AISAAUTOPILOT_WP_MS_URL) with this plugin in its plugins folder and inactive, and WP-CLI
 * (AISAAUTOPILOT_WP_CLI, the path to wp-cli.phar). Every step runs in its own WP-CLI process,
 * as a real request would, and the state of every site is read from the main site, which
 * never loads the other sites.
 *
 * Proves: network activation sets up every site; a site added later, or one a large
 * network's activation skipped, sets itself up on its first request; a deleted site takes
 * the plugin's tables with it; activating on one site sets up only that site; uninstall
 * removes the tables and options of every site, and the tables a deleted site kept when the
 * plugin was active on that site alone. The sites it adds are deleted in finally.
 *
 * Never point it at a network whose plugin data matters: uninstall removes it.
 */

declare(strict_types=1);

$root   = (string) getenv( 'AISAAUTOPILOT_WP_MS_ROOT' );
$url    = rtrim( (string) ( getenv( 'AISAAUTOPILOT_WP_MS_URL' ) ?: 'http://ms.test' ), '/' );
$cli    = (string) getenv( 'AISAAUTOPILOT_WP_CLI' );
$plugin = 'ai-site-assistant-autopilot/ai-site-assistant-autopilot.php';
$checks = array();
$sites  = array();

if ( '' === $root || '' === $cli ) {
	fwrite( STDERR, 'Set AISAAUTOPILOT_WP_MS_ROOT and AISAAUTOPILOT_WP_CLI.' . PHP_EOL );
	exit( 2 );
}

// The plugin's own tables, counted from Core\Network::TABLES so a new table needs no edit here.
preg_match( '/public const TABLES = array\((.*?)\);/s', (string) file_get_contents( dirname( __DIR__ ) . '/includes/Core/Network.php' ), $aisaautopilot_tables );
$table_count = substr_count( $aisaautopilot_tables[1] ?? '', '::' );
$check = static function ( bool $ok, string $name, string $detail = '' ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name . ( $ok || '' === $detail ? '' : ' — ' . $detail ),
	);
};

/** Runs a WP-CLI command as $site_url (the main site by default); returns [exit code, output]. */
$wp = static function ( string $args, string $site_url = '', string $before = '' ) use ( $root, $url, $cli ): array {
	$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $cli ) . ' --allow-root --path=' . escapeshellarg( $root ) . ' --url=' . escapeshellarg( '' === $site_url ? $url : $site_url );
	if ( '' !== $before ) {
		$command .= ' --exec=' . escapeshellarg( $before );
	}
	exec( $command . ' ' . $args . ' 2>&1', $output, $code );
	return array( $code, trim( implode( "\n", $output ) ) );
};

/**
 * For each site id: how many of the plugin's tables exist and which of its options are
 * set. Read from the main site, so no other site runs a request.
 *
 * @param array<int, int> $ids
 * @return array<int, array{tables:int, options:array<int, string>}>
 */
$state = static function ( array $ids ) use ( $wp ): array {
	$code = 'global $wpdb; $r = array();'
		. 'foreach ( array( ' . implode( ',', array_map( 'intval', $ids ) ) . ' ) as $id ) {'
		. '  $p = $wpdb->get_blog_prefix( $id );'
		. '  $t = $wpdb->get_col( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $p . "aisaautopilot_" ) . "%" ) );'
		. '  $o = $p . "options" === $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $p . "options" ) ) )'
		. '    ? $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$p}options WHERE option_name LIKE %s", $wpdb->esc_like( "aisaautopilot_" ) . "%" ) ) : array();'
		. '  $r[ $id ] = array( "tables" => count( $t ), "options" => $o );'
		. '}'
		. 'echo "STATE" . wp_json_encode( $r );';
	[ , $out ] = $wp( 'eval ' . escapeshellarg( $code ) );
	$json      = (string) substr( (string) strrchr( $out, 'STATE' ), 5 );
	$decoded   = json_decode( $json, true );
	return is_array( $decoded ) ? $decoded : array();
};

$set_up = static function ( array $site ) use ( $table_count ): bool {
	return $table_count === ( $site['tables'] ?? 0 ) && in_array( 'aisaautopilot_site_setup', $site['options'] ?? array(), true ) && in_array( 'aisaautopilot_settings', $site['options'] ?? array(), true );
};

$empty = static function ( array $site ): bool {
	return 0 === ( $site['tables'] ?? -1 ) && array() === ( $site['options'] ?? array( 'missing' ) );
};

$uninstall = static function () use ( $wp, $plugin ): array {
	return $wp( 'eval ' . escapeshellarg( 'require_once ABSPATH . "wp-admin/includes/plugin.php"; uninstall_plugin( "' . $plugin . '" ); echo "uninstalled";' ) );
};

$describe = static function ( array $state ): string {
	return (string) json_encode( $state );
};

try {
	[ , $active ] = $wp( 'plugin list --status=active,active-network --field=name' );
	if ( false !== strpos( $active, 'ai-site-assistant-autopilot' ) ) {
		throw new RuntimeException( 'Start from a network where the plugin is inactive.' );
	}

	foreach ( array( 'a', 'b' ) as $key ) {
		[ $code, $id ] = $wp( 'site create --slug=aisa-ms-' . $key . '-' . getmypid() . ' --porcelain' );
		if ( 0 !== $code || ! ctype_digit( $id ) ) {
			throw new RuntimeException( 'Could not create a site: ' . $id );
		}
		$sites[ $key ] = (int) $id;
	}
	$site_url = static function ( string $key ) use ( $url ): string {
		return $url . '/aisa-ms-' . $key . '-' . getmypid() . '/';
	};

	// Network activation.
	[ $code ]  = $wp( 'plugin activate --network ai-site-assistant-autopilot' );
	$after     = $state( array( 1, $sites['a'], $sites['b'] ) );
	$check(
		0 === $code && $set_up( $after[1] ?? array() ) && $set_up( $after[ $sites['a'] ] ?? array() ) && $set_up( $after[ $sites['b'] ] ?? array() ),
		'Network activation gives every site its ' . $table_count . ' tables and default settings, not only the main site',
		$describe( $after )
	);

	// A site added after activation.
	[ , $id ]     = $wp( 'site create --slug=aisa-ms-c-' . getmypid() . ' --porcelain' );
	$sites['c']   = (int) $id;
	$before_visit = $state( array( $sites['c'] ) );
	$wp( 'eval "echo 1;"', $site_url( 'c' ) );
	$after_visit = $state( array( $sites['c'] ) );
	$check(
		0 === ( $before_visit[ $sites['c'] ]['tables'] ?? -1 ) && $set_up( $after_visit[ $sites['c'] ] ?? array() ),
		'A site added after network activation sets itself up on its first request',
		$describe( array( $before_visit, $after_visit ) )
	);

	// Deleting a site.
	[ $code ]      = $wp( 'site delete ' . $sites['b'] . ' --yes' );
	$deleted       = $state( array( $sites['b'] ) );
	$deleted_id    = $sites['b'];
	$sites['b']    = 0;
	$check(
		0 === $code && $set_up( $after[ $deleted_id ] ?? array() ) && 0 === ( $deleted[ $deleted_id ]['tables'] ?? -1 ),
		'Deleting a site drops the plugin\'s tables with the site\'s own',
		$describe( $deleted )
	);

	// Uninstall after network deactivation.
	$wp( 'plugin deactivate --network ai-site-assistant-autopilot' );
	[ , $out ] = $uninstall();
	$gone      = $state( array( 1, $sites['a'], $sites['c'] ) );
	$check(
		'uninstalled' === $out && $empty( $gone[1] ?? array() ) && $empty( $gone[ $sites['a'] ] ?? array() ) && $empty( $gone[ $sites['c'] ] ?? array() ),
		'Uninstall removes the plugin\'s tables and options from every site of the network',
		$describe( $gone )
	);

	// A large network: activation does not walk the sites, each one sets itself up when visited.
	[ $code ] = $wp( 'plugin activate --network ai-site-assistant-autopilot', '', 'WP_CLI::add_wp_hook( "wp_is_large_network", "__return_true" );' );
	$skipped  = $state( array( $sites['a'], $sites['c'] ) );
	$wp( 'eval "echo 1;"', $site_url( 'a' ) );
	$visited = $state( array( $sites['a'], $sites['c'] ) );
	$check(
		0 === $code && $empty( $skipped[ $sites['a'] ] ?? array() ) && $empty( $skipped[ $sites['c'] ] ?? array() )
		&& $set_up( $visited[ $sites['a'] ] ?? array() ) && $empty( $visited[ $sites['c'] ] ?? array() ),
		'On a large network activation leaves the sites alone and each sets itself up on its first request',
		$describe( array( $skipped, $visited ) )
	);
	$wp( 'plugin deactivate --network ai-site-assistant-autopilot' );
	$uninstall();

	// Activation on one site only.
	[ $code ] = $wp( 'plugin activate ai-site-assistant-autopilot', $site_url( 'a' ) );
	$one      = $state( array( 1, $sites['a'], $sites['c'] ) );
	$check(
		0 === $code && $set_up( $one[ $sites['a'] ] ?? array() ) && $empty( $one[1] ?? array() ) && $empty( $one[ $sites['c'] ] ?? array() ),
		'Activating on one site of a network sets up that site only',
		$describe( $one )
	);
	// Deleting that site from the network screen, where the plugin is not loaded, leaves its tables.
	$wp( 'site delete ' . $sites['a'] . ' --yes' );
	$orphan_id  = $sites['a'];
	$sites['a'] = 0;
	$orphaned   = $state( array( $orphan_id ) );
	$uninstall();
	$clean = $state( array( 1, $orphan_id, $sites['c'] ) );
	$check(
		$table_count === ( $orphaned[ $orphan_id ]['tables'] ?? 0 ) && $empty( $clean[1] ?? array() ) && $empty( $clean[ $orphan_id ] ?? array() ) && $empty( $clean[ $sites['c'] ] ?? array() ),
		'Uninstall also drops the tables a deleted site kept when the plugin was active on that site alone',
		$describe( array( $orphaned, $clean ) )
	);
} catch ( Throwable $error ) {
	$check( false, 'Multisite contract completes without a fatal error: ' . $error->getMessage() );
} finally {
	$wp( 'plugin deactivate --network ai-site-assistant-autopilot' );
	foreach ( $sites as $key => $id ) {
		if ( $id > 0 ) {
			$wp( 'plugin deactivate ai-site-assistant-autopilot', $url . '/aisa-ms-' . $key . '-' . getmypid() . '/' );
			$wp( 'site delete ' . $id . ' --yes' );
		}
	}
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
