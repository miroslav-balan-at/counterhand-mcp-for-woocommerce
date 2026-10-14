import { expect, test } from '@playwright/test';
import { mcpCall, PROTOCOL_VERSION, STATELESS_VERSION, statelessCall } from '../support/mcp';
import { anonymousContext, connect } from '../support/oauth';
import { adminStatePath } from '../support/store';

/*
 * The 2026-07-28 (stateless) era on the real endpoint, beside the legacy one.
 * Every rule asserted here is from the specification or the official
 * conformance suite; see CLAUDE.md, "The MCP protocol".
 */
test.describe( 'MCP protocol (2026-07-28, stateless)', () => {
	let accessToken: string;
	let endpoint: string;

	test.beforeAll( async ( { browser, baseURL } ) => {
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		const { tokens, discovery } = await connect( page, baseURL as string, [ 'products:read', 'reports:read' ] );
		accessToken = tokens.access_token;
		endpoint = discovery.resource;
		await page.close();
	} );

	test( 'server/discover names the stateless revisions, the capabilities and the server', async ( { baseURL } ) => {
		const client = await anonymousContext( baseURL as string );

		const response = await statelessCall( client, endpoint, accessToken, 'server/discover' );
		const result = ( await response.json() ).result;

		expect( response.status() ).toBe( 200 );
		expect( result.resultType ).toBe( 'complete' );
		expect( result.supportedVersions ).toEqual( [ STATELESS_VERSION ] );
		expect( result.capabilities.tools ).toBeDefined();
		expect( result.instructions ).toContain( 'Products' );
		expect( result._meta[ 'io.modelcontextprotocol/serverInfo' ].name ).toBe( 'Counterhand MCP for WooCommerce' );
		expect( result.cacheScope ).toBe( 'private' );
		await client.dispose();
	} );

	test( 'tools/list is complete, private and carries the same tools as the legacy list', async ( { baseURL } ) => {
		const client = await anonymousContext( baseURL as string );

		const stateless = ( await ( await statelessCall( client, endpoint, accessToken, 'tools/list' ) ).json() ).result;
		const legacy = ( await ( await mcpCall( client, endpoint, accessToken, 'tools/list' ) ).json() ).result;

		expect( stateless.resultType ).toBe( 'complete' );
		expect( stateless.cacheScope ).toBe( 'private' );
		expect( stateless.ttlMs ).toBeGreaterThanOrEqual( 0 );
		expect( stateless.tools.map( ( tool: { name: string } ) => tool.name ) ).toEqual( legacy.tools.map( ( tool: { name: string } ) => tool.name ) );
		// The legacy reply is exactly what 2025-era clients always got.
		expect( Object.keys( legacy ) ).toEqual( [ 'tools' ] );
		await client.dispose();
	} );

	test( 'tools/call runs the tool and frames the result as complete', async ( { baseURL } ) => {
		const client = await anonymousContext( baseURL as string );

		const response = await statelessCall( client, endpoint, accessToken, 'tools/call', { name: 'list_products', arguments: { sku: 'e2e-mug' } } );
		const result = ( await response.json() ).result;

		expect( response.status() ).toBe( 200 );
		expect( result.resultType ).toBe( 'complete' );
		expect( result.isError ).toBe( false );
		expect( JSON.stringify( result.structuredContent ) ).toContain( 'E2E Mug' );
		await client.dispose();
	} );

	test( 'an unsupported version is refused with the versions that are supported', async ( { baseURL } ) => {
		const client = await anonymousContext( baseURL as string );

		const response = await statelessCall( client, endpoint, accessToken, 'server/discover', {}, { version: 'v999.0.0' } );
		const body = await response.json();

		expect( response.status() ).toBe( 400 );
		expect( body.error.code ).toBe( -32022 );
		expect( body.error.data ).toEqual( { supported: [ STATELESS_VERSION ], requested: 'v999.0.0' } );
		await client.dispose();
	} );

	test( 'headers that disagree with the body are refused', async ( { baseURL } ) => {
		const client = await anonymousContext( baseURL as string );

		for ( const headers of [ { 'Mcp-Method': 'tools/list' }, { 'Mcp-Name': 'delete_product' }, { 'MCP-Protocol-Version': undefined } ] ) {
			const response = await statelessCall( client, endpoint, accessToken, 'tools/call', { name: 'list_products', arguments: {} }, { headers } );

			expect( response.status(), JSON.stringify( headers ) ).toBe( 400 );
			expect( ( await response.json() ).error.code, JSON.stringify( headers ) ).toBe( -32020 );
		}
		await client.dispose();
	} );

	test( 'a method the server does not serve is a 404, and so is initialize', async ( { baseURL } ) => {
		const client = await anonymousContext( baseURL as string );

		for ( const method of [ 'prompts/list', 'initialize' ] ) {
			const response = await statelessCall( client, endpoint, accessToken, method );

			expect( response.status(), method ).toBe( 404 );
			expect( ( await response.json() ).error.code, method ).toBe( -32601 );
		}
		await client.dispose();
	} );

	test( 'the legacy era keeps HTTP 200 for its errors, so 2025-era clients never mistake it for a missing server', async ( { baseURL } ) => {
		const client = await anonymousContext( baseURL as string );

		const response = await mcpCall( client, endpoint, accessToken, 'prompts/list' );

		expect( response.status() ).toBe( 200 );
		expect( ( await response.json() ).error.code ).toBe( -32601 );
		expect( PROTOCOL_VERSION ).toBe( '2025-11-25' );
		await client.dispose();
	} );
} );
