/*
 * Offline regression for the admin knowledge-base console renderer (T20).
 *
 * chat.js turns an AI answer into HTML with escHtml() + inlineMd(). The answer
 * is grounded on site content that lower-privileged authors can write, so a
 * prompt-injected answer must never break out of the link's href attribute.
 * The two functions are taken verbatim from assets/admin/chat.js and run with a
 * document stub that serialises text nodes the way browsers do (&, <, > only).
 * No WordPress, browser or network is needed.
 */
'use strict';
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const assert = require( 'node:assert/strict' );

const source = fs.readFileSync( path.join( __dirname, '..', 'assets', 'admin', 'chat.js' ), 'utf8' );
function extract( name ) {
	const match = source.match( new RegExp( '\\tfunction ' + name + '\\([\\s\\S]*?\\r?\\n\\t}\\r?\\n' ) );
	assert( match, name + '() was not found in chat.js' );
	return match[ 0 ];
}

const document = {
	createElement() {
		let text = '';
		return {
			set textContent( value ) { text = String( value ); },
			get innerHTML() { return text.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ); },
		};
	},
};
// eslint-disable-next-line no-new-func
const inlineMd = new Function( 'document', extract( 'escHtml' ) + extract( 'inlineMd' ) + 'return inlineMd;' )( document );

let passed = 0;
function check( label, fn ) {
	fn();
	passed++;
	console.log( 'PASS ' + label );
}

check( 'a quote in a link URL cannot add an attribute', () => {
	const out = inlineMd( '[click](https://example.com/"onmouseover="alert(1))' );
	assert( ! /<a\b/.test( out ), 'no link is built from a URL that contains a quote: ' + out );
	assert( ! out.includes( '"onmouseover' ), 'the raw quote is escaped: ' + out );
} );

check( 'a single quote in a link URL is escaped and not linked', () => {
	const out = inlineMd( "[x](https://example.com/'onfocus='alert(1))" );
	assert( ! /<a\b/.test( out ) );
	assert( ! out.includes( "'" ) );
} );

check( 'markup in answer text stays text', () => {
	assert.equal( inlineMd( '<img src=x onerror=alert(1)>' ), '&lt;img src=x onerror=alert(1)&gt;' );
} );

check( 'a quote in plain text is escaped', () => {
	assert.equal( inlineMd( 'He said "hi"' ), 'He said &quot;hi&quot;' );
} );

check( 'an ordinary link, including a query string, still renders', () => {
	assert.equal(
		inlineMd( 'See [Docs](https://example.com/a?b=1&c=2) now' ),
		'See <a href="https://example.com/a?b=1&amp;c=2" target="_blank" rel="noopener noreferrer">Docs</a> now'
	);
} );

check( 'non-http link schemes are never linked', () => {
	assert( ! /<a\b/.test( inlineMd( '[x](javascript:alert(1))' ) ) );
} );

check( 'emphasis and code still render', () => {
	assert.equal( inlineMd( '**bold** *it* `code`' ), '<strong>bold</strong> <em>it</em> <code>code</code>' );
} );

console.log( 'admin chat markdown: ' + passed + ' PASS / 0 FAIL' );
