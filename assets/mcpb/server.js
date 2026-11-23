#!/usr/bin/env node
/**
 * WP Divi MCP: Claude Desktop extension server.
 *
 * A dependency-free bridge between Claude Desktop (MCP over stdio,
 * newline-delimited JSON-RPC) and the WP Divi MCP endpoint of one WordPress
 * site (MCP Streamable HTTP, JSON responses). It runs on the user's computer,
 * so requests come from there with this proxy's own User-Agent. That is what
 * lets it work on hosts whose edge blocks Anthropic's cloud clients.
 *
 * Env: WPDM_URL (connection URL including ?key=), WPDM_VERSION (optional).
 * Logs go to stderr only and never include the URL or the key.
 */
'use strict';

const readline = require( 'node:readline' );

const ERR_PROXY = -32000;

/**
 * Collect the ids of the requests (not notifications or responses) in a message.
 *
 * @param {object|object[]} message Parsed JSON-RPC message or batch.
 * @return {Array<string|number>} Ids.
 */
function requestIds( message ) {
	const items = Array.isArray( message ) ? message : [ message ];
	return items
		.filter( ( m ) => m && typeof m === 'object' && 'method' in m && 'id' in m && m.id !== null )
		.map( ( m ) => m.id );
}

/**
 * Start the bridge.
 *
 * @param {object}   opts
 * @param {string}   opts.url     Connection URL.
 * @param {string}   opts.version Version for the User-Agent.
 * @param {object}   opts.input   Readable stream of JSON-RPC lines.
 * @param {object}   opts.output  Writable stream for responses.
 * @param {Function} opts.log     Logger (stderr).
 * @return {Promise<void>} Resolves once input ended and shutdown was attempted.
 */
function start( { url, version, input, output, log } ) {
	const userAgent = 'wp-divi-mcp-desktop/' + ( version || 'dev' );
	let sessionId = null;
	let chain = Promise.resolve();

	const write = ( msg ) => output.write( JSON.stringify( msg ) + '\n' );
	const errorFor = ( ids, code, message ) => {
		for ( const id of ids ) {
			write( { jsonrpc: '2.0', id, error: { code, message } } );
		}
	};
	const headers = () => {
		const h = {
			'Content-Type': 'application/json',
			Accept: 'application/json, text/event-stream',
			'User-Agent': userAgent,
		};
		if ( sessionId ) {
			h[ 'Mcp-Session-Id' ] = sessionId;
		}
		return h;
	};

	async function forward( line ) {
		let message;
		try {
			message = JSON.parse( line );
		} catch ( e ) {
			log( 'wp-divi-mcp: ignoring a line that is not JSON' );
			return;
		}
		const ids = requestIds( message );

		let res;
		let text;
		try {
			res = await fetch( url, { method: 'POST', headers: headers(), body: JSON.stringify( message ) } );
			text = await res.text();
		} catch ( e ) {
			log( 'wp-divi-mcp: cannot reach WordPress (' + ( e && e.name ) + ')' );
			errorFor( ids, ERR_PROXY, 'Cannot reach WordPress: ' + ( e && e.name ) );
			return;
		}

		const sid = res.headers.get( 'mcp-session-id' );
		if ( sid ) {
			sessionId = sid;
		}

		let body = null;
		if ( text.trim() !== '' ) {
			try {
				body = JSON.parse( text );
			} catch ( e ) {
				body = null;
			}
		}

		if ( res.ok ) {
			if ( body === null ) {
				if ( text.trim() !== '' ) {
					log( 'wp-divi-mcp: WordPress sent a non-JSON response (HTTP ' + res.status + ')' );
					errorFor( ids, ERR_PROXY, 'Invalid response from WordPress (HTTP ' + res.status + ')' );
				}
				return;
			}
			for ( const item of Array.isArray( body ) ? body : [ body ] ) {
				write( item );
			}
			return;
		}

		log( 'wp-divi-mcp: WordPress answered HTTP ' + res.status );
		if ( body && body.error && typeof body.error.code === 'number' ) {
			errorFor( ids, body.error.code, String( body.error.message || 'Error' ) + ' (HTTP ' + res.status + ')' );
		} else {
			errorFor( ids, ERR_PROXY, 'HTTP ' + res.status + ' from WordPress' );
		}
	}

	return new Promise( ( resolve ) => {
		const rl = readline.createInterface( { input, crlfDelay: Infinity } );
		rl.on( 'line', ( line ) => {
			if ( line.trim() === '' ) {
				return;
			}
			chain = chain.then( () => forward( line ) ).catch( ( e ) => log( 'wp-divi-mcp: ' + ( e && e.name ) ) );
		} );
		rl.on( 'close', async () => {
			await chain;
			if ( sessionId ) {
				try {
					await fetch( url, { method: 'DELETE', headers: headers() } );
				} catch ( e ) {
					// Best effort.
				}
			}
			resolve();
		} );
	} );
}

module.exports = { start };

if ( require.main === module ) {
	const url = process.env.WPDM_URL;
	if ( ! url ) {
		console.error( 'wp-divi-mcp: WPDM_URL is not set' );
		process.exit( 1 );
	}
	start( {
		url,
		version: process.env.WPDM_VERSION,
		input: process.stdin,
		output: process.stdout,
		log: ( ...a ) => console.error( ...a ),
	} ).then( () => process.exit( 0 ) );
}
