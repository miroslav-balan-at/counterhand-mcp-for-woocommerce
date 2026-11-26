import { expect, test } from '@playwright/test';
import { listToolNames } from '../support/mcp';
import { anonymousContext, CLIENT_ID, connect } from '../support/oauth';

/*
 * Several processes of one client share a stored credential and refresh it at
 * the same moment (Claude Code with several sessions open). Before 2.1.3 the
 * loser got invalid_grant and the client dropped the connection; a request
 * sent with the access token another process had just rotated got a 401.
 */
test.describe( 'OAuth refresh races', () => {
	test( 'two refreshes with the same token both get the same new pair, and the old access token still answers', async ( { page, baseURL } ) => {
		const { tokens, discovery } = await connect( page, baseURL as string, [ 'products:read' ] );
		const client = await anonymousContext( baseURL as string );
		const refresh = () =>
			client.post( discovery.tokenEndpoint, {
				form: { grant_type: 'refresh_token', refresh_token: tokens.refresh_token as string, client_id: CLIENT_ID, resource: discovery.resource },
			} );

		const [ first, second ] = await Promise.all( [ refresh(), refresh() ] );

		expect( first.status() ).toBe( 200 );
		expect( second.status() ).toBe( 200 );
		const firstPair = await first.json();
		const secondPair = await second.json();
		expect( secondPair.access_token ).toBe( firstPair.access_token );
		expect( secondPair.refresh_token ).toBe( firstPair.refresh_token );

		expect( await listToolNames( client, discovery.resource, tokens.access_token ), 'a request in flight with the retired token' ).toContain( 'list_products' );
		expect( await listToolNames( client, discovery.resource, firstPair.access_token ) ).toContain( 'list_products' );
		await client.dispose();
	} );
} );
