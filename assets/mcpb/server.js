#!/usr/bin/env node
/**
 * WP Divi MCP: Claude Desktop extension entry point.
 *
 * Starts unconditionally. Claude Desktop may load this file from its own
 * launcher (so require.main is not this module); a require.main check here
 * would leave the server silent.
 *
 * Env: WPDM_URL (connection URL including ?key=), WPDM_VERSION (optional).
 */
'use strict';

const { start } = require( './bridge.js' );

const url = process.env.WPDM_URL;
if ( ! url ) {
	console.error( 'wp-divi-mcp: WPDM_URL is not set' );
	process.exit( 1 );
}
console.error( 'wp-divi-mcp: started (Node ' + process.version + ')' );
start( {
	url,
	version: process.env.WPDM_VERSION,
	input: process.stdin,
	output: process.stdout,
	log: ( ...a ) => console.error( ...a ),
} ).then( () => process.exit( 0 ) );
