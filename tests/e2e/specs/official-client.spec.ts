import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';
import { expect, test } from '@playwright/test';
import { connect } from '../support/oauth';
import { adminStatePath, patchSettings } from '../support/store';

/**
 * What a real client does with this server, through the official TypeScript SDK rather
 * than requests written to match what the server expects. SDK 1.x opens with the
 * initialize handshake, so a gated write keeps the confirm argument.
 */
test.describe( 'the official MCP client', () => {
	let client: Client;
	let restore: () => void;

	test.beforeAll( async ( { browser, baseURL } ) => {
		restore = patchSettings( { products_write: true } );
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		const { tokens, discovery } = await connect( page, baseURL as string, [ 'products:read', 'products:write' ] );
		await page.close();

		client = new Client( { name: 'counterhand-e2e', version: '0' }, { capabilities: { elicitation: {} } } );
		await client.connect(
			new StreamableHTTPClientTransport( new URL( discovery.resource ), {
				requestInit: { headers: { Authorization: `Bearer ${ tokens.access_token }` } },
			} )
		);
	} );

	test.afterAll( async () => {
		await client.close();
		restore();
	} );

	test( 'connects, lists what it was granted and reads the instructions', async () => {
		const { tools } = await client.listTools();

		expect( client.getServerVersion()?.name ).toBe( 'Counterhand MCP for WooCommerce' );
		expect( client.getInstructions() ).toContain( 'Products' );
		expect( tools.map( ( tool ) => tool.name ) ).toContain( 'update_product' );
		expect( tools.find( ( tool ) => tool.name === 'list_products' )?.annotations?.readOnlyHint ).toBe( true );
	} );

	test( 'a price change is refused until it says it was confirmed', async () => {
		const listed = await client.callTool( { name: 'list_products', arguments: { per_page: 1 } } );
		const product = ( listed.structuredContent as { products: { id: number; regular_price: string }[] } ).products[ 0 ];
		const change = { id: product.id, regular_price: product.regular_price };

		expect( ( await client.callTool( { name: 'update_product', arguments: change } ) ).isError ).toBe( true );
		expect( ( await client.callTool( { name: 'update_product', arguments: { ...change, confirm: true } } ) ).isError ).toBeFalsy();
	} );

	test( 'an argument of the wrong shape comes back as a tool error, which the SDK does not throw', async () => {
		const result = await client.callTool( { name: 'list_products', arguments: { per_page: { many: true } } } );

		expect( result.isError ).toBe( true );
	} );
} );
