import { createHash, randomBytes } from 'node:crypto';
import { expect, request, type APIRequestContext, type Page } from '@playwright/test';

/** Must match the fixture in mu-plugins/counterhand-e2e-fixtures.php. */
export const CLIENT_ID = 'https://client.example.test/oauth/client-metadata.json';
export const REDIRECT_URI = 'http://127.0.0.1:47321/callback';

/**
 * A client of its own, so the connection it makes is the only row with its name
 * under Connected apps. Connections list newest first, and two made in the same
 * second have no defined order.
 */
export function uniqueClientId( label: string ): string {
	return `https://${ label }-${ randomBytes( 4 ).toString( 'hex' ) }.client.example.test/oauth/client-metadata.json`;
}

export type Discovery = {
	resource: string;
	issuer: string;
	authorizationEndpoint: string;
	tokenEndpoint: string;
};

export type Tokens = {
	access_token: string;
	refresh_token?: string;
	token_type: string;
	expires_in: number;
	scope: string;
};

const base64url = ( bytes: Buffer ): string => bytes.toString( 'base64url' );

/**
 * A request context with no cookies: what a real MCP client is. Anything the
 * admin's browser session could lend it would make a passing test meaningless.
 */
export async function anonymousContext( baseURL: string ): Promise< APIRequestContext > {
	return request.newContext( { baseURL, storageState: { cookies: [], origins: [] } } );
}

/** Follows RFC 9728 → RFC 8414 the way a client does, rather than hardcoding paths. */
export async function discover( client: APIRequestContext ): Promise< Discovery > {
	const resourceResponse = await client.get( '/.well-known/oauth-protected-resource' );
	expect( resourceResponse.status() ).toBe( 200 );
	const resource = await resourceResponse.json();

	const serverResponse = await client.get( new URL( '/.well-known/oauth-authorization-server', resource.authorization_servers[ 0 ] ).toString() );
	expect( serverResponse.status() ).toBe( 200 );
	const server = await serverResponse.json();

	return {
		resource: resource.resource,
		issuer: server.issuer,
		authorizationEndpoint: server.authorization_endpoint,
		tokenEndpoint: server.token_endpoint,
	};
}

export function pkcePair(): { verifier: string; challenge: string } {
	const verifier = base64url( randomBytes( 32 ) );

	return { verifier, challenge: base64url( createHash( 'sha256' ).update( verifier ).digest() ) };
}

/** The URL a client sends the admin's browser to. */
export function authorizationUrl(
	discovery: Discovery,
	options: { scopes: string[]; verifierChallenge: string; state: string; clientId?: string }
): string {
	const authorize = new URL( discovery.authorizationEndpoint );
	authorize.search = new URLSearchParams( {
		response_type: 'code',
		client_id: options.clientId ?? CLIENT_ID,
		redirect_uri: REDIRECT_URI,
		code_challenge: options.verifierChallenge,
		code_challenge_method: 'S256',
		state: options.state,
		resource: discovery.resource,
		scope: options.scopes.join( ' ' ),
	} ).toString();

	return authorize.toString();
}

export type ConsentOutcome = { code?: string; error?: string; state: string | null; iss: string | null };

/**
 * Opens the consent screen as the logged-in admin, lets `decide` work the form,
 * and captures where the store sends the browser back to. The loopback
 * callback is answered by the test, standing in for the client's listener.
 */
export async function runConsent(
	page: Page,
	discovery: Discovery,
	options: { scopes: string[]; verifierChallenge: string; state: string; clientId?: string },
	decide: ( page: Page ) => Promise< void >
): Promise< ConsentOutcome > {
	await page.goto( authorizationUrl( discovery, options ) );

	// Nothing listens on the loopback port, so the navigation itself fails; the
	// request it starts is what a client's listener would have received. Routes
	// cannot answer it, because they are not consulted for redirect targets.
	const callbackRequest = page.waitForRequest( ( candidate ) => candidate.url().startsWith( REDIRECT_URI ), { timeout: 15_000 } );
	await decide( page );
	const received = new URL( ( await callbackRequest ).url() );

	return {
		code: received.searchParams.get( 'code' ) ?? undefined,
		error: received.searchParams.get( 'error' ) ?? undefined,
		state: received.searchParams.get( 'state' ),
		iss: received.searchParams.get( 'iss' ),
	};
}

export async function exchangeCode( client: APIRequestContext, discovery: Discovery, code: string, verifier: string, clientId: string = CLIENT_ID ) {
	return client.post( discovery.tokenEndpoint, {
		form: {
			grant_type: 'authorization_code',
			code,
			code_verifier: verifier,
			client_id: clientId,
			redirect_uri: REDIRECT_URI,
			resource: discovery.resource,
		},
	} );
}

/** The whole happy path, for specs that only need a working token. */
export async function connect( page: Page, baseURL: string, scopes: string[], clientId: string = CLIENT_ID, whileConsentIsOpen: () => void = () => {} ): Promise< { tokens: Tokens; discovery: Discovery } > {
	const client = await anonymousContext( baseURL );
	const discovery = await discover( client );
	const { verifier, challenge } = pkcePair();

	const outcome = await runConsent( page, discovery, { scopes, verifierChallenge: challenge, state: base64url( randomBytes( 16 ) ), clientId }, async ( consent ) => {
		// Advanced scopes are collapsed and never pre-ticked, so an admin who
		// wants them opens the section and ticks them by hand; so does this.
		const collapsed = consent.locator( 'details.counterhand-scopes__section--advanced:not([open]) > summary' );
		while ( ( await collapsed.count() ) > 0 ) {
			await collapsed.first().click();
		}
		for ( const scope of scopes ) {
			await consent.locator( `input[name="counterhand_scopes[]"][value="${ scope }"]` ).check();
		}
		whileConsentIsOpen();
		await consent.getByRole( 'button', { name: 'Approve access' } ).click();
	} );
	expect( outcome.code, 'authorization code' ).toBeTruthy();

	const response = await exchangeCode( client, discovery, outcome.code as string, verifier, clientId );
	expect( response.status() ).toBe( 200 );
	const tokens = ( await response.json() ) as Tokens;
	await client.dispose();

	return { tokens, discovery };
}
