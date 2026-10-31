import { expect, test } from '@playwright/test';
import { listToolNames, mcpCall } from '../support/mcp';
import { anonymousContext, authorizationUrl, connect, discover, pkcePair } from '../support/oauth';
import { adminStatePath, patchSettings, shopManagerStatePath, wp } from '../support/store';

const FIXTURE_READ = 'integration:e2e-fixture:read';
const FIXTURE_WRITE = 'integration:e2e-fixture:write';
const OTHER_READ = 'integration:e2e-other:read';

/* Abilities other plugins register, reached through their own per-plugin scopes. */
test.describe( 'Abilities bridge', () => {
	test( 'a plugin stays off until the store enables it, and consent then offers it', async ( { browser, baseURL } ) => {
		const client = await anonymousContext( baseURL as string );
		const restore = patchSettings( { 'integration_e2e-fixture_read': true } );
		try {
			const discovered = ( await ( await client.get( '/.well-known/oauth-authorization-server' ) ).json() ).scopes_supported as string[];
			expect( discovered.filter( ( scope ) => scope.startsWith( 'integration:' ) ), 'discovery names no plugin, so a store cannot be fingerprinted' ).toEqual( [] );

			const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
			const { tokens, discovery } = await connect( page, baseURL as string, [ FIXTURE_READ ] );
			await page.close();

			const names = await listToolNames( client, discovery.resource, tokens.access_token );
			expect( names ).toEqual( expect.arrayContaining( [ 'e2e_fixture_list_notes', 'e2e_fixture_shout' ] ) );
			expect( names, 'no flag, no tool' ).not.toContain( 'e2e_fixture_not_exposed' );
			expect( names, 'a write needs the write scope' ).not.toContain( 'e2e_fixture_rewrite_note' );
			expect( names, 'a grant for one plugin does not reach another' ).not.toContain( 'e2e_other_ping' );
			expect( names.filter( ( name ) => /^(woocommerce|core|mcp_adapter)_/.test( name ) ) ).toEqual( [] );

			const annotated = ( await ( await mcpCall( client, discovery.resource, tokens.access_token, 'tools/list' ) ).json() ).result.tools.find(
				( tool: { name: string } ) => 'e2e_fixture_list_notes' === tool.name
			);
			expect( annotated.annotations.readOnlyHint ).toBe( true );
			expect( annotated.annotations.openWorldHint, 'third-party code promises nothing' ).toBe( true );
			expect( annotated.inputSchema.type ).toBe( 'object' );

			restore();
			expect( await listToolNames( client, discovery.resource, tokens.access_token ), 'the store switch wins over the grant' ).not.toContain( 'e2e_fixture_list_notes' );
		} finally {
			restore();
			await client.dispose();
		}
	} );

	test( 'consent offers an enabled plugin no client could know to ask for, unticked', async ( { browser, baseURL } ) => {
		const restore = patchSettings( { 'integration_e2e-fixture_read': true } );
		const client = await anonymousContext( baseURL as string );
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		try {
			const { challenge } = pkcePair();
			await page.goto( authorizationUrl( await discover( client ), { scopes: [ 'products:read' ], verifierChallenge: challenge, state: 'consent-offer' } ) );

			const offered = page.locator( `input[name="counterhand_scopes[]"][value="${ FIXTURE_READ }"]` );
			await expect( offered ).toHaveCount( 1 );
			await expect( offered ).toBeEnabled();
			await expect( offered ).not.toBeChecked();
			await expect( page.locator( `input[name="counterhand_scopes[]"][value="${ OTHER_READ }"]` ), 'off in Settings' ).toBeDisabled();
		} finally {
			await page.close();
			await client.dispose();
			restore();
		}
	} );

	test( 'a plugin can be picked for the chat', async ( { browser, baseURL } ) => {
		const restore = patchSettings( { 'integration_e2e-fixture_read': true } );
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		try {
			await page.goto( '/wp-admin/admin.php?page=counterhand-mcp' );
			await page.locator( 'details', { has: page.locator( 'input[name="counterhand_chat_groups[]"]' ) } ).first().locator( 'summary' ).click();
			const area = page.locator( 'input[name="counterhand_chat_groups[]"][value="integration:e2e-fixture"]' );
			await area.check();
			await page.getByRole( 'button', { name: 'Save areas' } ).click();
			await expect( page.locator( 'input[name="counterhand_chat_groups[]"][value="integration:e2e-fixture"]' ) ).toBeChecked();
		} finally {
			await page.close();
			wp( 'option', 'delete', 'counterhand_chat' );
			restore();
		}
	} );

	test( 'a call returns a structured object whatever shape the ability answers in', async ( { browser, baseURL } ) => {
		const restore = patchSettings( { 'integration_e2e-fixture_read': true } );
		const client = await anonymousContext( baseURL as string );
		try {
			const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
			const { tokens, discovery } = await connect( page, baseURL as string, [ FIXTURE_READ ] );
			await page.close();
			const call = async ( name: string, args: Record< string, unknown > ) => ( await ( await mcpCall( client, discovery.resource, tokens.access_token, 'tools/call', { name, arguments: args } ) ).json() ).result;

			expect( ( await call( 'e2e_fixture_list_notes', {} ) ).structuredContent ).toEqual( { result: [ 'first', 'second' ] } );
			expect( ( await call( 'e2e_fixture_shout', { input: 'hello' } ) ).structuredContent ).toEqual( { result: 'HELLO' } );
		} finally {
			restore();
			await client.dispose();
		}
	} );

	test( 'an ability of unknown danger is refused until confirmed', async ( { browser, baseURL } ) => {
		const restore = patchSettings( { 'integration_e2e-fixture_read': true, 'integration_e2e-fixture_write': true } );
		const client = await anonymousContext( baseURL as string );
		wp( 'option', 'update', 'counterhand_e2e_note', 'untouched' );
		try {
			const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
			const { tokens, discovery } = await connect( page, baseURL as string, [ FIXTURE_WRITE ] );
			await page.close();
			const call = async ( args: Record< string, unknown > ) => ( await ( await mcpCall( client, discovery.resource, tokens.access_token, 'tools/call', { name: 'e2e_fixture_rewrite_note', arguments: args } ) ).json() );

			const refused = await call( { note: 'changed', confirm: false } );
			expect( refused.result.isError ).toBe( true );
			expect( wp( 'option', 'get', 'counterhand_e2e_note' ) ).toBe( 'untouched' );

			const done = await call( { note: 'changed', confirm: true } );
			expect( done.result.structuredContent ).toEqual( { saved: 'changed' } );
			expect( wp( 'option', 'get', 'counterhand_e2e_note' ) ).toBe( 'changed' );
		} finally {
			wp( 'option', 'delete', 'counterhand_e2e_note' );
			restore();
			await client.dispose();
		}
	} );

	test( 'the ability\'s own permission check holds for a token the store granted', async ( { browser, baseURL } ) => {
		const restore = patchSettings( { 'integration_e2e-fixture_read': true, 'integration_e2e-fixture_write': true } );
		const client = await anonymousContext( baseURL as string );
		wp( 'option', 'update', 'counterhand_e2e_note', 'untouched' );
		try {
			const context = await browser.newContext( { baseURL, storageState: shopManagerStatePath } );
			const page = await context.newPage();
			const { tokens, discovery } = await connect( page, baseURL as string, [ FIXTURE_READ, FIXTURE_WRITE ] );
			await context.close();

			expect( await listToolNames( client, discovery.resource, tokens.access_token ), 'hidden: no input needed, so it can be probed' ).not.toContain( 'e2e_fixture_list_notes' );

			// Needs input, so it is listed unprobed; the call is where it gets refused.
			expect( await listToolNames( client, discovery.resource, tokens.access_token ) ).toContain( 'e2e_fixture_rewrite_note' );
			const refused = await ( await mcpCall( client, discovery.resource, tokens.access_token, 'tools/call', { name: 'e2e_fixture_rewrite_note', arguments: { note: 'changed', confirm: true } } ) ).json();
			expect( refused.result.isError ).toBe( true );
			expect( wp( 'option', 'get', 'counterhand_e2e_note' ) ).toBe( 'untouched' );
		} finally {
			wp( 'option', 'delete', 'counterhand_e2e_note' );
			restore();
			await client.dispose();
		}
	} );

	test( 'the settings screen switches one plugin on, and every call is logged', async ( { browser, baseURL } ) => {
		const restoreLog = patchSettings( { action_log_enabled: false } );
		const client = await anonymousContext( baseURL as string );
		const context = await browser.newContext( { baseURL, storageState: adminStatePath } );
		const page = await context.newPage();
		try {
			await page.goto( '/wp-admin/admin.php?page=counterhand-mcp-settings' );
			await page.locator( '#counterhand-integration_e2e-fixture_read' ).check();
			await expect( page.locator( '#counterhand-integration_e2e-other_read' ) ).not.toBeChecked();
			await page.getByRole( 'button', { name: /save/i } ).first().click();
			await expect( page.locator( '#counterhand-integration_e2e-fixture_read' ) ).toBeChecked();

			const { tokens, discovery } = await connect( page, baseURL as string, [ FIXTURE_READ ] );
			await mcpCall( client, discovery.resource, tokens.access_token, 'tools/call', { name: 'e2e_fixture_list_notes', arguments: {} } );

			await page.goto( '/wp-admin/admin.php?page=counterhand-mcp-log' );
			await expect( page.locator( '.counterhand-log__tool', { hasText: 'e2e_fixture_list_notes' } ).first(), 'logged with the action log off' ).toBeVisible();
		} finally {
			await context.close();
			await client.dispose();
			restoreLog();
			const stored = JSON.parse( wp( 'option', 'get', 'counterhand_settings', '--format=json' ) );
			delete stored[ 'integration_e2e-fixture_read' ];
			wp( 'option', 'update', 'counterhand_settings', JSON.stringify( stored ), '--format=json' );
		}
	} );
} );
