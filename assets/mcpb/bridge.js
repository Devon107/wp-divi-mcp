/**
 * WP Divi MCP: Claude Desktop bridge (the logic; server.js is the entry point).
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
const DEFAULT_TIMEOUT_MS = 120000;

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
 * @param {number}   [opts.timeoutMs] Per-request timeout (default 120 s).
 * @return {Promise<void>} Resolves once input ended and shutdown was attempted.
 */
function start( { url, version, input, output, log, timeoutMs } ) {
	const timeout = timeoutMs || DEFAULT_TIMEOUT_MS;
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

	async function post( message ) {
		const res = await fetch( url, {
			method: 'POST',
			headers: headers(),
			body: JSON.stringify( message ),
			signal: AbortSignal.timeout( timeout ),
		} );
		return { res, text: await res.text() };
	}

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
			( { res, text } = await post( message ) );
			// The server forgets idle sessions (12 h). Drop the stale id and retry once
			// without it; the server accepts session-less requests.
			if ( res.status === 404 && sessionId ) {
				log( 'wp-divi-mcp: session expired, starting a new one' );
				sessionId = null;
				( { res, text } = await post( message ) );
			}
		} catch ( e ) {
			const timedOut = e && ( e.name === 'TimeoutError' || e.name === 'AbortError' );
			const why = timedOut ? 'request timed out' : 'cannot reach WordPress (' + ( e && e.name ) + ')';
			log( 'wp-divi-mcp: ' + why );
			errorFor( ids, ERR_PROXY, timedOut ? 'WordPress request timed out' : 'Cannot reach WordPress: ' + ( e && e.name ) );
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
			// Write JSON-RPC answers; any request left without one gets an error, so
			// Claude never waits on an id the server did not answer.
			const answered = new Set();
			for ( const item of Array.isArray( body ) ? body : [ body ] ) {
				if ( item && typeof item === 'object' && item.jsonrpc === '2.0' && 'id' in item ) {
					write( item );
					answered.add( item.id );
				}
			}
			const missing = ids.filter( ( id ) => ! answered.has( id ) );
			if ( missing.length ) {
				log( 'wp-divi-mcp: WordPress sent an unexpected response (HTTP ' + res.status + ')' );
				errorFor( missing, ERR_PROXY, 'Unexpected response from WordPress (HTTP ' + res.status + ')' );
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
					await fetch( url, { method: 'DELETE', headers: headers(), signal: AbortSignal.timeout( 5000 ) } );
				} catch ( e ) {
					// Best effort.
				}
			}
			resolve();
		} );
	} );
}

module.exports = { start };
