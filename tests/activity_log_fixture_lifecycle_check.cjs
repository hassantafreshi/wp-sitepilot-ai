/* Local-only regression: preparing/restoring browser fixtures must preserve real logs. */
'use strict';
const { execFileSync } = require( 'node:child_process' );
const assert = require( 'node:assert/strict' );
const php = process.env.AISAAUTOPILOT_PHP || 'C:\\xampp\\php\\php.exe';
const fixture = require( 'node:path' ).join( __dirname, 'local_activity_log_browser_fixture.php' );
const bootstrap = "<?php define('DISABLE_WP_CRON', true); require (getenv('AISAAUTOPILOT_WP_ROOT') ?: 'C:/xampp/htdocs/wp') . '/wp-load.php'; ";
function runPhp( code ) {
	return execFileSync( php, [], { input: bootstrap + code, encoding: 'utf8', windowsHide: true } ).trim();
}
function snapshot() {
	return JSON.parse( runPhp( "$missing = new stdClass(); $logs = get_option('aisaautopilot_logs', $missing); echo wp_json_encode(array('exists' => $logs !== $missing, 'value' => $logs !== $missing ? $logs : null, 'fixture' => get_option('aisaautopilot_activity_log_browser_fixture', null)));" ) );
}
function restoreSnapshot( state ) {
	const data = Buffer.from( JSON.stringify( state ) ).toString( 'base64' );
	runPhp( "$state = json_decode(base64_decode('" + data + "'), true); if ($state['exists']) { update_option('aisaautopilot_logs', $state['value'], false); } else { delete_option('aisaautopilot_logs'); }" );
}
function runFixture( mode ) {
	return execFileSync( php, [ fixture, mode ], { encoding: 'utf8', windowsHide: true } ).trim();
}
const original = snapshot();
assert.equal( original.fixture, null, 'An active QA fixture must be cleaned up before this test.' );
try {
	runPhp( "update_option('aisaautopilot_logs', array(array('time' => gmdate('c'), 'level' => 'info', 'event' => 'fixture.guard', 'message' => 'Keep this sentinel', 'context' => array())), false);" );
	const baseline = snapshot();
	runFixture( 'restore' );
	assert.deepEqual( snapshot(), baseline, 'Restore without a fixture must be a no-op.' );
	console.log( 'PASS restore without a snapshot leaves the log intact' );
	const prepared = JSON.parse( runFixture( 'prepare' ) );
	assert.deepEqual( snapshot().fixture.log, { exists: baseline.exists, value: baseline.value } );
	runPhp( "(new \\AISAAutoPilot\\Logging\\Logger())->info('fixture.concurrent', 'Preserve a concurrent event');" );
	runFixture( 'restore' );
	const restored = snapshot();
	assert.equal( restored.fixture, null );
	assert( restored.value.some( ( item ) => item.message === 'Keep this sentinel' ) );
	assert( restored.value.some( ( item ) => item.message === 'Preserve a concurrent event' ) );
	assert( !restored.value.some( ( item ) => ( item.message || '' ).startsWith( 'AISAAutoPilot Activity Log browser ' ) ) );
	assert.equal( runPhp( 'echo get_post(' + prepared.post_id + ') ? 1 : 0;' ), '0' );
	console.log( 'PASS prepare/restore preserves existing and concurrent logs and removes only the owned draft' );
	runFixture( 'restore' );
	assert.deepEqual( snapshot(), restored );
	console.log( 'PASS repeated restore is idempotent' );
} finally {
	runFixture( 'restore' );
	restoreSnapshot( original );
}
assert.deepEqual( snapshot(), original );
console.log( 'PASS lifecycle test restored its original log snapshot' );
