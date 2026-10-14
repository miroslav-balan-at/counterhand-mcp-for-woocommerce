import { expect, test } from '@playwright/test';
import { listToolNames, mcpCall, PROTOCOL_VERSION } from '../support/mcp';
import { anonymousContext, connect } from '../support/oauth';
import { adminStatePath, patchSettings, wp } from '../support/store';

test.describe( 'MCP protocol (2025-11-25)', () => {
	let accessToken: string;
	let endpoint: string;

	test.beforeAll( async ( { browser, baseURL } ) => {
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		const { tokens, discovery } = await connect( page, baseURL as string, [ 'products:read', 'reports:read' ] );
		accessToken = tokens.access_token;
		endpoint = discovery.resource;
		await page.close();
	} );

	test( 'initialize agrees on the requested version and names the server', async ( { baseURL } ) => {
		const client = await anonymousContext( baseURL as string );

		const body = await ( await mcpCall( client, endpoint, accessToken, 'initialize', {
			protocolVersion: PROTOCOL_VERSION,
			capabilities: {},
			clientInfo: { name: 'counterhand-e2e', version: '0' },
		} ) ).json();

		expect( body.result.protocolVersion ).toBe( PROTOCOL_VERSION );
		expect( body.result.serverInfo.name ).toBe( 'Counterhand MCP for WooCommerce' );
		expect( body.result.capabilities.tools ).toBeDefined();
		// The instructions tell the model what its token reaches, by group label.
		expect( body.result.instructions ).toContain( 'Products' );
		await client.dispose();
	} );

	test( 'an unknown protocol version falls back to the current one', async ( { baseURL } ) => {
		const client = await anonymousContext( baseURL as string );

		const body = await ( await mcpCall( client, endpoint, accessToken, 'initialize', { protocolVersion: '1999-01-01', capabilities: {} } ) ).json();

		expect( body.result.protocolVersion ).toBe( PROTOCOL_VERSION );
		await client.dispose();
	} );

	test( 'notifications get no response body', async ( { baseURL } ) => {
		const client = await anonymousContext( baseURL as string );

		const response = await client.post( endpoint, {
			headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${ accessToken }` },
			data: { jsonrpc: '2.0', method: 'notifications/initialized' },
		} );

		expect( response.status() ).toBe( 202 );
		await client.dispose();
	} );

	test( 'tools/list is well-formed and in a stable order', async ( { baseURL } ) => {
		const client = await anonymousContext( baseURL as string );

		const first = await ( await mcpCall( client, endpoint, accessToken, 'tools/list' ) ).json();
		for ( const tool of first.result.tools ) {
			expect( tool.name ).toMatch( /^[a-z0-9_]+$/ );
			expect( tool.description.length, `${ tool.name } has a description` ).toBeGreaterThan( 0 );
			expect( tool.inputSchema.type, `${ tool.name } input schema` ).toBe( 'object' );
			// All four hints, always: an omitted one defaults to destructive and open-world.
			expect( Object.keys( tool.annotations ).sort(), `${ tool.name } annotations` ).toEqual( [ 'destructiveHint', 'idempotentHint', 'openWorldHint', 'readOnlyHint' ] );
			expect( tool.annotations.openWorldHint ).toBe( false );
		}

		// This token is read-only, so every tool it sees is too.
		expect( first.result.tools.every( ( tool: { annotations: { readOnlyHint: boolean } } ) => tool.annotations.readOnlyHint ) ).toBe( true );

		// Stable order keeps a client's cached list, and the model's prompt cache, valid.
		const second = await listToolNames( client, endpoint, accessToken );
		expect( second ).toEqual( first.result.tools.map( ( tool: { name: string } ) => tool.name ) );
		await client.dispose();
	} );

	test( 'a tool call returns the store data as text and structured content', async ( { baseURL } ) => {
		const client = await anonymousContext( baseURL as string );

		const body = await ( await mcpCall( client, endpoint, accessToken, 'tools/call', { name: 'list_products', arguments: { sku: 'e2e-mug' } } ) ).json();

		expect( body.result.isError ?? false ).toBe( false );
		expect( body.result.content[ 0 ].type ).toBe( 'text' );
		expect( JSON.stringify( body.result.structuredContent ) ).toContain( 'E2E Mug' );
		await client.dispose();
	} );

	test( 'an unsupported method is a JSON-RPC error, not a crash', async ( { baseURL } ) => {
		const client = await anonymousContext( baseURL as string );

		const body = await ( await mcpCall( client, endpoint, accessToken, 'counterhand/no-such-method' ) ).json();

		expect( body.error.code ).toBe( -32601 );
		await client.dispose();
	} );

	test( 'switching a group off makes its tools invisible and uncallable at once', async ( { browser, baseURL } ) => {
		const restore = patchSettings( { coupons_read: true } );
		try {
			const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
			const { tokens } = await connect( page, baseURL as string, [ 'coupons:read' ] );
			await page.close();
			const client = await anonymousContext( baseURL as string );

			expect( await listToolNames( client, endpoint, tokens.access_token ) ).toContain( 'get_coupons' );

			restore();

			// Same token, no reconnect: the store's switch wins over the grant.
			expect( await listToolNames( client, endpoint, tokens.access_token ) ).not.toContain( 'get_coupons' );
			const call = await ( await mcpCall( client, endpoint, tokens.access_token, 'tools/call', { name: 'get_coupons', arguments: {} } ) ).json();
			expect( call.error ?? call.result?.isError, 'the call is refused' ).toBeTruthy();
			await client.dispose();
		} finally {
			restore();
		}
	} );

	test( 'a confirmation-gated write is refused until it carries confirm', async ( { browser, baseURL } ) => {
		const restore = patchSettings( { settings_read: true, settings_write: true } );
		try {
			const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
			const { tokens } = await connect( page, baseURL as string, [ 'settings:read', 'settings:write' ] );
			await page.close();
			const client = await anonymousContext( baseURL as string );
			const before = wp( 'option', 'get', 'woocommerce_store_address' );

			const body = await ( await mcpCall( client, endpoint, tokens.access_token, 'tools/call', {
				name: 'update_setting',
				arguments: { group_id: 'general', id: 'woocommerce_store_address', value: 'E2E Street 1' },
			} ) ).json();

			expect( body.error ?? body.result?.isError, 'the unconfirmed call is refused' ).toBeTruthy();
			expect( JSON.stringify( body ) ).toContain( 'confirm' );
			expect( wp( 'option', 'get', 'woocommerce_store_address' ), 'nothing was written' ).toBe( before );
			await client.dispose();
		} finally {
			restore();
		}
	} );
} );
