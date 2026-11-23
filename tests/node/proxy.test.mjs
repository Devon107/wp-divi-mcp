// Tests for assets/mcpb/server.js, the Claude Desktop stdio-to-HTTP proxy.
// Run: node --test tests/node/
import { test } from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import { PassThrough } from 'node:stream';
import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );
const { start } = require( '../../assets/mcpb/server.js' );

/**
 * Fake MCP endpoint. handler(req, body, res) answers each request.
 * Returns { url, requests, close }.
 */
async function fakeServer( handler ) {
	const requests = [];
	const server = http.createServer( ( req, res ) => {
		let body = '';
		req.on( 'data', ( c ) => ( body += c ) );
		req.on( 'end', () => {
			requests.push( { method: req.method, headers: req.headers, body } );
			handler( req, body, res );
		} );
	} );
	await new Promise( ( r ) => server.listen( 0, '127.0.0.1', r ) );
	const { port } = server.address();
	return {
		url: `http://127.0.0.1:${ port }/wp-json/wp-divi-mcp/v1/mcp?key=SECRET`,
		requests,
		close: () =>
			new Promise( ( r ) => {
				server.close( r );
				// fetch keeps connections alive; drop them so close() returns at once.
				server.closeAllConnections();
			} ),
	};
}

function json( res, status, obj, headers = {} ) {
	res.writeHead( status, { 'Content-Type': 'application/json', ...headers } );
	res.end( obj === undefined ? '' : JSON.stringify( obj ) );
}

/** Run the proxy over the given input lines; resolves to { lines, logs }. */
async function run( url, inputLines, version = '9.9.9' ) {
	const input = new PassThrough();
	const output = new PassThrough();
	let out = '';
	output.on( 'data', ( c ) => ( out += c ) );
	const logs = [];
	const done = start( { url, version, input, output, log: ( ...a ) => logs.push( a.join( ' ' ) ) } );
	for ( const line of inputLines ) {
		input.write( line + '\n' );
	}
	input.end();
	await done;
	const lines = out.split( '\n' ).filter( Boolean ).map( ( l ) => JSON.parse( l ) );
	return { lines, logs };
}

const req = ( id, method = 'tools/list' ) => JSON.stringify( { jsonrpc: '2.0', id, method, params: {} } );

test( 'initialize stores session id', async () => {
	const srv = await fakeServer( ( r, body, res ) => {
		if ( r.method === 'DELETE' ) {
			return json( res, 204 );
		}
		const msg = JSON.parse( body );
		json( res, 200, { jsonrpc: '2.0', id: msg.id, result: {} }, { 'Mcp-Session-Id': 'abc' } );
	} );
	const { lines } = await run( srv.url, [ req( 1, 'initialize' ), req( 2 ) ] );
	await srv.close();
	assert.equal( lines.length, 2 );
	assert.deepEqual( lines.map( ( l ) => l.id ), [ 1, 2 ] );
	const posts = srv.requests.filter( ( r ) => r.method === 'POST' );
	assert.equal( posts[ 0 ].headers[ 'mcp-session-id' ], undefined );
	assert.equal( posts[ 1 ].headers[ 'mcp-session-id' ], 'abc' );
} );

test( 'sends headers and user agent', async () => {
	const srv = await fakeServer( ( r, body, res ) => json( res, 200, { jsonrpc: '2.0', id: 1, result: {} } ) );
	await run( srv.url, [ req( 1 ) ] );
	await srv.close();
	const h = srv.requests[ 0 ].headers;
	assert.equal( h[ 'user-agent' ], 'wp-divi-mcp-desktop/9.9.9' );
	assert.equal( h[ 'content-type' ], 'application/json' );
	assert.match( h.accept, /application\/json/ );
} );

test( 'notification gets 202 and no output', async () => {
	const srv = await fakeServer( ( r, body, res ) => {
		res.writeHead( 202 );
		res.end();
	} );
	const { lines } = await run( srv.url, [ JSON.stringify( { jsonrpc: '2.0', method: 'notifications/initialized' } ) ] );
	await srv.close();
	assert.equal( lines.length, 0 );
} );

test( 'batch response is written line per message', async () => {
	const srv = await fakeServer( ( r, body, res ) =>
		json( res, 200, [ { jsonrpc: '2.0', id: 1, result: {} }, { jsonrpc: '2.0', id: 2, result: {} } ] )
	);
	const { lines } = await run( srv.url, [ JSON.stringify( [ JSON.parse( req( 1 ) ), JSON.parse( req( 2 ) ) ] ) ] );
	await srv.close();
	assert.equal( lines.length, 2 );
	assert.deepEqual( lines.map( ( l ) => l.id ), [ 1, 2 ] );
} );

test( 'http 401 becomes json-rpc error', async () => {
	const srv = await fakeServer( ( r, body, res ) =>
		json( res, 401, { jsonrpc: '2.0', id: null, error: { code: -32001, message: 'Unauthorized' } } )
	);
	const { lines } = await run( srv.url, [ req( 7 ) ] );
	await srv.close();
	assert.equal( lines.length, 1 );
	assert.equal( lines[ 0 ].id, 7 );
	assert.equal( lines[ 0 ].error.code, -32001 );
	assert.match( lines[ 0 ].error.message, /Unauthorized/ );
} );

test( 'html error page becomes json-rpc error', async () => {
	const srv = await fakeServer( ( r, body, res ) => {
		res.writeHead( 403, { 'Content-Type': 'text/html' } );
		res.end( '<html>403 Forbidden</html>' );
	} );
	const { lines } = await run( srv.url, [ req( 8 ) ] );
	await srv.close();
	assert.equal( lines[ 0 ].id, 8 );
	assert.equal( lines[ 0 ].error.code, -32000 );
	assert.match( lines[ 0 ].error.message, /HTTP 403/ );
} );

test( 'network error becomes json-rpc error', async () => {
	const srv = await fakeServer( () => {} );
	const url = srv.url;
	await srv.close();
	const { lines } = await run( url, [ req( 9 ) ] );
	assert.equal( lines[ 0 ].id, 9 );
	assert.equal( lines[ 0 ].error.code, -32000 );
} );

test( 'bad stdin line is skipped', async () => {
	const srv = await fakeServer( ( r, body, res ) => json( res, 200, { jsonrpc: '2.0', id: 3, result: {} } ) );
	const { lines, logs } = await run( srv.url, [ 'not json', req( 3 ) ] );
	await srv.close();
	assert.equal( lines.length, 1 );
	assert.equal( lines[ 0 ].id, 3 );
	assert.ok( logs.length > 0 );
} );

test( 'delete on end', async () => {
	const srv = await fakeServer( ( r, body, res ) => {
		if ( r.method === 'DELETE' ) {
			res.writeHead( 204 );
			return res.end();
		}
		json( res, 200, { jsonrpc: '2.0', id: 1, result: {} }, { 'Mcp-Session-Id': 'abc' } );
	} );
	await run( srv.url, [ req( 1, 'initialize' ) ] );
	await srv.close();
	const del = srv.requests.find( ( r ) => r.method === 'DELETE' );
	assert.ok( del );
	assert.equal( del.headers[ 'mcp-session-id' ], 'abc' );
} );

test( 'url never logged', async () => {
	const srv = await fakeServer( () => {} );
	const url = srv.url;
	await srv.close();
	const { logs } = await run( url, [ 'not json', req( 9 ) ] );
	assert.ok( logs.length > 0 );
	for ( const l of logs ) {
		assert.ok( ! l.includes( 'SECRET' ), l );
	}
} );

test( 'expired session is dropped and the call retried once', async () => {
	let posts = 0;
	const srv = await fakeServer( ( r, body, res ) => {
		if ( r.method === 'DELETE' ) {
			res.writeHead( 204 );
			return res.end();
		}
		posts++;
		const msg = JSON.parse( body );
		if ( r.headers[ 'mcp-session-id' ] === 'old' ) {
			return json( res, 404, { jsonrpc: '2.0', id: null, error: { code: -32001, message: 'Session not found' } } );
		}
		const sid = msg.method === 'initialize' ? 'old' : undefined;
		json( res, 200, { jsonrpc: '2.0', id: msg.id, result: { ok: msg.id } }, sid ? { 'Mcp-Session-Id': sid } : {} );
	} );
	const { lines } = await run( srv.url, [ req( 1, 'initialize' ), req( 2 ), req( 3 ) ] );
	await srv.close();
	assert.deepEqual( lines.map( ( l ) => l.id ), [ 1, 2, 3 ] );
	assert.ok( lines.every( ( l ) => l.result ), JSON.stringify( lines ) );
	// initialize, 404 for id 2, retry of id 2 without the header, id 3: four POSTs.
	assert.equal( posts, 4 );
	assert.equal( srv.requests.filter( ( r ) => r.method === 'DELETE' ).length, 0 );
} );

test( 'a request that takes too long gets an error', async () => {
	const srv = await fakeServer( () => {} );
	const input = new PassThrough();
	const output = new PassThrough();
	let out = '';
	output.on( 'data', ( c ) => ( out += c ) );
	const done = start( { url: srv.url, version: 't', input, output, log: () => {}, timeoutMs: 200 } );
	input.end( req( 4 ) + '\n' );
	await done;
	await srv.close();
	const lines = out.split( '\n' ).filter( Boolean ).map( ( l ) => JSON.parse( l ) );
	assert.equal( lines.length, 1 );
	assert.equal( lines[ 0 ].id, 4 );
	assert.equal( lines[ 0 ].error.code, -32000 );
	assert.match( lines[ 0 ].error.message, /timed out/i );
} );

test( '2xx json without a json-rpc answer still answers every request', async () => {
	const srv = await fakeServer( ( r, body, res ) => json( res, 200, { ok: true } ) );
	const { lines } = await run( srv.url, [ req( 5 ) ] );
	await srv.close();
	assert.equal( lines.length, 1 );
	assert.equal( lines[ 0 ].id, 5 );
	assert.equal( lines[ 0 ].error.code, -32000 );
} );
