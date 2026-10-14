import { randomBytes } from 'node:crypto';
import { expect, test } from '@playwright/test';
import { listToolNames, mcpCall } from '../support/mcp';
import { anonymousContext, connect, discover, exchangeCode, pkcePair, runConsent, uniqueClientId } from '../support/oauth';
import { shopManagerStatePath } from '../support/store';

const state = () => randomBytes( 16 ).toString( 'base64url' );

test.describe( 'OAuth consent', () => {
	test( 'an unauthenticated MCP request is refused with a pointer to the resource metadata', async ( { baseURL } ) => {
		const client = await anonymousContext( baseURL as string );
		const discovery = await discover( client );

		const response = await mcpCall( client, discovery.resource, null, 'tools/list' );

		expect( response.status() ).toBe( 401 );
		expect( response.headers()[ 'www-authenticate' ] ).toContain( 'resource_metadata=' );
		await client.dispose();
	} );

	test( 'the admin narrows the grant, and the token reaches exactly what was approved', async ( { page, baseURL } ) => {
		const client = await anonymousContext( baseURL as string );
		const discovery = await discover( client );
		const { verifier, challenge } = pkcePair();
		const sentState = state();

		const outcome = await runConsent(
			page,
			discovery,
			// coupons:read is switched off in the store, so the screen must offer it disabled.
			{ scopes: [ 'products:read', 'orders:read', 'reports:read', 'coupons:read' ], verifierChallenge: challenge, state: sentState },
			async ( consent ) => {
				await expect( consent.getByRole( 'heading', { name: 'Authorize AI access' } ) ).toBeVisible();
				await expect( consent.getByText( 'E2E Test Client' ).first() ).toBeVisible();
				await expect( consent.locator( 'input[value="coupons:read"]' ) ).toBeDisabled();
				await expect( consent.locator( 'input[value="orders:read"]' ) ).toBeChecked();

				await consent.locator( 'input[value="orders:read"]' ).uncheck();
				await consent.getByRole( 'button', { name: 'Approve access' } ).click();
			}
		);

		expect( outcome.state ).toBe( sentState );
		// RFC 9207: the callback names its issuer so a client can detect mix-up.
		expect( outcome.iss ).toBe( discovery.issuer );

		const response = await exchangeCode( client, discovery, outcome.code as string, verifier );
		expect( response.status() ).toBe( 200 );
		const tokens = await response.json();
		expect( tokens.scope.split( ' ' ).sort() ).toEqual( [ 'products:read', 'reports:read' ] );
		expect( tokens.refresh_token, 'a client that declares refresh_token gets one' ).toBeTruthy();

		const tools = await listToolNames( client, discovery.resource, tokens.access_token );
		expect( tools ).toContain( 'list_products' );
		// get_order_totals stays: it is a Reports tool, and reports:read was granted.
		expect( tools ).toContain( 'get_order_totals' );
		expect( tools, 'no Orders tool without orders:read' ).not.toContain( 'list_orders' );
		expect( tools, 'no tool from a disabled group' ).not.toContain( 'get_coupons' );
		await client.dispose();
	} );

	test( 'a code is single-use and bound to its PKCE verifier', async ( { page, baseURL } ) => {
		const client = await anonymousContext( baseURL as string );
		const discovery = await discover( client );
		const { verifier, challenge } = pkcePair();

		const outcome = await runConsent( page, discovery, { scopes: [ 'products:read' ], verifierChallenge: challenge, state: state() }, async ( consent ) => {
			await consent.getByRole( 'button', { name: 'Approve access' } ).click();
		} );

		const wrongVerifier = await exchangeCode( client, discovery, outcome.code as string, pkcePair().verifier );
		expect( wrongVerifier.status() ).toBe( 400 );
		expect( ( await wrongVerifier.json() ).error ).toBe( 'invalid_grant' );

		// The failed attempt consumed the code: a correct retry must not succeed.
		const replay = await exchangeCode( client, discovery, outcome.code as string, verifier );
		expect( replay.status() ).toBe( 400 );
		expect( ( await replay.json() ).error ).toBe( 'invalid_grant' );
		await client.dispose();
	} );

	test( 'denying sends access_denied back with the original state', async ( { page, baseURL } ) => {
		const client = await anonymousContext( baseURL as string );
		const discovery = await discover( client );
		const sentState = state();

		const outcome = await runConsent( page, discovery, { scopes: [ 'products:read' ], verifierChallenge: pkcePair().challenge, state: sentState }, async ( consent ) => {
			await consent.getByRole( 'button', { name: 'Deny' } ).click();
		} );

		expect( outcome.error ).toBe( 'access_denied' );
		expect( outcome.code ).toBeUndefined();
		expect( outcome.state ).toBe( sentState );
		await client.dispose();
	} );

	test( 'revoking a connection in wp-admin cuts the token off', async ( { page, baseURL } ) => {
		const clientId = uniqueClientId( 'revoke' );
		const { tokens, discovery } = await connect( page, baseURL as string, [ 'products:read' ], clientId );
		const client = await anonymousContext( baseURL as string );
		expect( ( await mcpCall( client, discovery.resource, tokens.access_token, 'tools/list' ) ).status() ).toBe( 200 );

		await page.goto( '/wp-admin/admin.php?page=counterhand-mcp-connect&view=connections' );
		const row = page.getByRole( 'row' ).filter( { hasText: new URL( clientId ).host } );
		await expect( row ).toHaveCount( 1 );
		await row.getByRole( 'button', { name: 'Revoke' } ).click();
		await expect( page ).toHaveURL( /counterhand_revoked=1/ );

		expect( ( await mcpCall( client, discovery.resource, tokens.access_token, 'tools/list' ) ).status() ).toBe( 401 );
		await client.dispose();
	} );

	test( 'a shop manager reaches the consent screen, because consent follows manage_woocommerce', async ( { browser, baseURL } ) => {
		const context = await browser.newContext( { baseURL, storageState: shopManagerStatePath } );
		const page = await context.newPage();
		const client = await anonymousContext( baseURL as string );
		const discovery = await discover( client );

		const outcome = await runConsent( page, discovery, { scopes: [ 'products:read' ], verifierChallenge: pkcePair().challenge, state: state() }, async ( consent ) => {
			await expect( consent.getByRole( 'button', { name: 'Approve access' } ) ).toBeVisible();
			await consent.getByRole( 'button', { name: 'Deny' } ).click();
		} );

		expect( outcome.error ).toBe( 'access_denied' );
		await context.close();
		await client.dispose();
	} );
} );
