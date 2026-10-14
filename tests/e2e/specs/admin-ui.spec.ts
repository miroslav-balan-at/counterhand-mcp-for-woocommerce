import { expect, test, type Page } from '@playwright/test';
import { anonymousContext, authorizationUrl, discover, pkcePair } from '../support/oauth';

const screens = [
	{ name: 'Chat', path: '/wp-admin/admin.php?page=counterhand-mcp' },
	{ name: 'Connect AI apps', path: '/wp-admin/admin.php?page=counterhand-mcp-connect' },
	{ name: 'Connected apps', path: '/wp-admin/admin.php?page=counterhand-mcp-connect&view=connections' },
	{ name: 'Settings', path: '/wp-admin/admin.php?page=counterhand-mcp-settings' },
	{ name: 'Action log', path: '/wp-admin/admin.php?page=counterhand-mcp-log' },
];

/** Collects what a screen must never produce: script errors and remote requests. */
function watch( page: Page, origin: string ) {
	const problems: string[] = [];
	page.on( 'pageerror', ( error ) => problems.push( `page error: ${ error.message }` ) );
	page.on( 'console', ( message ) => {
		if ( message.type() === 'error' ) {
			problems.push( `console error: ${ message.text() }` );
		}
	} );
	// WordPress.org forbids loading assets from elsewhere; every request stays on the store.
	page.on( 'request', ( request ) => {
		const url = request.url();
		if ( /^https?:/.test( url ) && new URL( url ).origin !== origin ) {
			problems.push( `remote request: ${ url }` );
		}
	} );

	return problems;
}

for ( const width of [ 1280, 400 ] ) {
	test.describe( `Admin screens at ${ width }px`, () => {
		test.use( { viewport: { width, height: 900 } } );

		for ( const screen of screens ) {
			test( `${ screen.name } renders cleanly`, async ( { page, baseURL } ) => {
				const problems = watch( page, new URL( baseURL as string ).origin );

				const response = await page.goto( screen.path );
				expect( response?.status() ).toBe( 200 );
				await expect( page.locator( '.counterhand-wrap' ) ).toBeVisible();
				await page.waitForLoadState( 'networkidle' );

				expect( problems ).toEqual( [] );

				// Only tables, diagrams and code may scroll sideways, inside their own box.
				const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
				expect( overflow, 'the page itself does not scroll horizontally' ).toBeLessThanOrEqual( 1 );
			} );
		}
	} );
}

test( 'the consent screen prints only the plugin\'s own stylesheets', async ( { page, baseURL } ) => {
	// A consent screen is a security surface: no other plugin or theme may restyle it (1.2.1).
	const client = await anonymousContext( baseURL as string );
	const discovery = await discover( client );
	await client.dispose();
	const stylesheets: string[] = [];
	page.on( 'request', ( request ) => {
		if ( request.resourceType() === 'stylesheet' ) {
			stylesheets.push( request.url() );
		}
	} );

	await page.goto( authorizationUrl( discovery, { scopes: [ 'products:read' ], verifierChallenge: pkcePair().challenge, state: 'stylesheets' } ) );
	await expect( page.getByRole( 'heading', { name: 'Authorize AI access' } ) ).toBeVisible();

	expect( stylesheets.length, 'the screen loads its own styles' ).toBeGreaterThan( 0 );
	for ( const url of stylesheets ) {
		expect( url ).toContain( '/wp-content/plugins/counterhand-mcp-for-woocommerce/' );
	}
} );
