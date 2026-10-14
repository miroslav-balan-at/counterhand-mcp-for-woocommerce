import { expect, test } from '@playwright/test';
import { mcpCall } from '../support/mcp';
import { anonymousContext, connect } from '../support/oauth';
import { patchSettings } from '../support/store';

test.describe( 'Action log', () => {
	let restore: () => void;

	test.beforeAll( () => {
		restore = patchSettings( { action_log_enabled: true } );
	} );

	test.afterAll( () => restore() );

	test( 'a tool call is recorded, with personal data masked before storage', async ( { page, baseURL } ) => {
		const { tokens, discovery } = await connect( page, baseURL as string, [ 'products:read' ] );
		const client = await anonymousContext( baseURL as string );
		const marker = `jane.doe+${ Date.now() }@example.test`;

		await mcpCall( client, discovery.resource, tokens.access_token, 'tools/call', { name: 'list_products', arguments: { search: marker } } );
		await client.dispose();

		await page.goto( '/wp-admin/admin.php?page=counterhand-mcp-log' );
		const row = page.locator( 'tbody tr' ).filter( { has: page.locator( '.counterhand-log__tool', { hasText: 'list_products' } ) } ).first();
		await expect( row ).toBeVisible();
		await expect( row.locator( '.counterhand-log__pill--ok' ) ).toBeVisible();

		const recorded = await row.locator( '.counterhand-log-args' ).textContent();
		expect( recorded ).toContain( 'j***@e***.test' );
		expect( recorded, 'the address itself never reaches the log' ).not.toContain( 'jane.doe' );
	} );
} );
