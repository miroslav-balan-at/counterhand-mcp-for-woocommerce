import { execFile } from 'node:child_process';
import { mkdtempSync, readdirSync, readFileSync, existsSync } from 'node:fs';
import { createServer, type Server } from 'node:http';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { promisify } from 'node:util';
import { expect, test } from '@playwright/test';
import { connect } from '../support/oauth';
import { adminStatePath } from '../support/store';

/*
 * The official MCP conformance suite against the real endpoint.
 *
 * The runner cannot send a bearer token, so a local proxy adds one minted
 * through the real consent flow. The plugin itself is never told to skip
 * authentication. Only the protocol-level scenarios apply: the rest assume the
 * SDK's reference tools (image, audio, progress, ...), which an application
 * server has no reason to ship.
 */
const run = promisify( execFile );

const SCENARIOS: Record< string, string[] > = {
	'2026-07-28': [ 'server-stateless', 'tools-list', 'caching' ],
	'2025-11-25': [ 'server-initialize', 'tools-list' ],
};

const BASELINE = join( __dirname, '..', 'conformance-baseline.yml' );

type Check = { id: string; name: string; status: string; errorMessage?: string; description?: string };

let proxy: Server;
let proxyUrl: string;

test.describe( 'Official MCP conformance suite', () => {
	test.describe.configure( { timeout: 180_000 } );

	test.beforeAll( async ( { browser, baseURL } ) => {
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		const { tokens, discovery } = await connect( page, baseURL as string, [ 'products:read' ] );
		await page.close();

		proxy = createServer( async ( incoming, outgoing ) => {
			const chunks: Buffer[] = [];
			for await ( const chunk of incoming ) {
				chunks.push( chunk as Buffer );
			}

			const headers = new Headers();
			for ( const [ name, value ] of Object.entries( incoming.headers ) ) {
				if ( 'string' === typeof value && ! [ 'host', 'content-length', 'connection' ].includes( name ) ) {
					headers.set( name, value );
				}
			}
			headers.set( 'authorization', `Bearer ${ tokens.access_token }` );

			const upstream = await fetch( discovery.resource, {
				method: incoming.method,
				headers,
				body: [ 'GET', 'HEAD' ].includes( incoming.method ?? 'GET' ) ? undefined : Buffer.concat( chunks ),
			} );

			outgoing.writeHead( upstream.status, Object.fromEntries( [ ...upstream.headers ].filter( ( [ name ] ) => ! [ 'content-encoding', 'content-length', 'transfer-encoding' ].includes( name ) ) ) );
			outgoing.end( Buffer.from( await upstream.arrayBuffer() ) );
		} );
		await new Promise< void >( ( resolve ) => proxy.listen( 0, '127.0.0.1', resolve ) );
		const address = proxy.address();
		proxyUrl = `http://127.0.0.1:${ 'object' === typeof address && address ? address.port : 0 }/mcp`;
	} );

	test.afterAll( async () => {
		await new Promise( ( resolve ) => proxy?.close( resolve ) );
	} );

	for ( const [ version, scenarios ] of Object.entries( SCENARIOS ) ) {
		for ( const scenario of scenarios ) {
			test( `${ scenario } at ${ version }`, async () => {
				const output = mkdtempSync( join( tmpdir(), 'counterhand-conformance-' ) );

				// The runner's own verdict: exit 0 only when every failure is one the
				// baseline names, and no baselined check has started passing.
				const exitCode = await run(
					'npx',
					[ 'conformance', 'server', '--url', proxyUrl, '--scenario', scenario, '--spec-version', version, '--expected-failures', BASELINE, '--output-dir', output ],
					{ timeout: 150_000 }
				).then( () => 0, ( error: { code?: number } ) => error.code ?? 1 );

				const checks = readChecks( output );
				const report = checks.map( ( check ) => `  ${ check.status.padEnd( 8 ) } ${ check.id }${ check.errorMessage ? ' — ' + check.errorMessage : '' }` ).join( '\n' );

				expect( checks.length, `${ scenario } produced results` ).toBeGreaterThan( 0 );
				expect( exitCode, `${ scenario } @ ${ version }:\n${ report }` ).toBe( 0 );
			} );
		}
	}
} );

/** The runner writes one directory per scenario, each with a checks.json. */
function readChecks( output: string ): Check[] {
	return readdirSync( output, { withFileTypes: true } )
		.filter( ( entry ) => entry.isDirectory() && existsSync( join( output, entry.name, 'checks.json' ) ) )
		.flatMap( ( entry ) => JSON.parse( readFileSync( join( output, entry.name, 'checks.json' ), 'utf-8' ) ) as Check[] );
}
