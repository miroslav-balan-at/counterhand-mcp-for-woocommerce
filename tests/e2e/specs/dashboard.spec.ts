import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test } from '@playwright/test';
import { mcpCall } from '../support/mcp';
import { anonymousContext, connect } from '../support/oauth';
import { patchSettings, shopManagerStatePath, wp } from '../support/store';

const DASHBOARD = '/wp-admin/admin.php?page=counterhand-mcp-dashboard';
const PLUGINS = '/wp-admin/admin.php?page=counterhand-mcp-integrations';
const SETTINGS = '/wp-admin/admin.php?page=counterhand-mcp-settings';

const stored = () => JSON.parse( wp( 'option', 'get', 'counterhand_settings', '--format=json' ) );

test.describe( 'Design tokens', () => {
	const schemes = [ 'modern', 'fresh', 'ocean' ];

	test.afterAll( () => {
		wp( 'user', 'meta', 'update', 'e2e-admin', 'admin_color', 'modern' );
	} );

	for ( const scheme of schemes ) {
		test( `the accent follows the ${ scheme } scheme, as core's buttons do`, async ( { page } ) => {
			wp( 'user', 'meta', 'update', 'e2e-admin', 'admin_color', scheme );
			await page.goto( SETTINGS );

			const [ button, accent ] = await page.evaluate( () => {
				const probe = document.createElement( 'div' );
				probe.style.background = 'var(--counterhand-accent)';
				document.body.append( probe );

				return [ getComputedStyle( document.querySelector( '.button-primary' ) as Element ).backgroundColor, getComputedStyle( probe ).backgroundColor ];
			} );

			expect( accent ).toBe( button );
		} );
	}

	test( 'every token this plugin reads exists in the installed WordPress', async ( { page } ) => {
		const sheet = readFileSync( join( process.cwd(), 'assets/shared/tokens.css' ), 'utf8' );
		const names = [ ...new Set( sheet.match( /--(?:wpds|wp-admin-theme)-[a-z0-9-]+/g ) ?? [] ) ];
		expect( names.length ).toBeGreaterThan( 20 );

		await page.goto( SETTINGS );
		const missing = await page.evaluate( ( tokens ) => tokens.filter( ( name ) => '' === getComputedStyle( document.body ).getPropertyValue( name ).trim() ), names );

		expect( missing ).toEqual( [] );
	} );

	test( 'muted text keeps AA contrast on a card', async ( { page } ) => {
		await page.goto( SETTINGS );
		const ratio = await page.evaluate( () => {
			const channel = ( value: number ) => ( value / 255 <= 0.03928 ? value / 255 / 12.92 : ( ( value / 255 + 0.055 ) / 1.055 ) ** 2.4 );
			const luminance = ( color: string ) => {
				const [ red, green, blue ] = ( color.match( /[\d.]+/g ) as string[] ).map( Number );

				return ( 0.2126 * channel( red ) ) + ( 0.7152 * channel( green ) ) + ( 0.0722 * channel( blue ) );
			};
			const text = getComputedStyle( document.querySelector( '.counterhand-card__desc' ) as Element ).color;
			const surface = getComputedStyle( document.querySelector( '.counterhand-card' ) as Element ).backgroundColor;
			const [ light, dark ] = [ luminance( text ), luminance( surface ) ].sort( ( a, b ) => b - a );

			return ( light + 0.05 ) / ( dark + 0.05 );
		} );

		expect( ratio ).toBeGreaterThanOrEqual( 4.5 );
	} );

	test( 'the slim header names the screen, links every screen and says whether AI access is on', async ( { page } ) => {
		await page.goto( SETTINGS );
		const head = page.locator( '.counterhand-page-head' );

		await expect( head.getByRole( 'heading', { level: 1 } ) ).toHaveText( 'Access' );
		await expect( head.getByRole( 'navigation' ).getByRole( 'link' ) ).toHaveText( [ 'Overview', 'Chat', 'Rewrites', 'Connect apps', 'Access', 'Activity' ] );
		await expect( head.getByRole( 'link', { name: 'Access' } ) ).toHaveAttribute( 'aria-current', 'page' );
		await expect( head.locator( '.counterhand-pill' ) ).toHaveText( /AI access (on|off)/ );

		const ratio = await page.evaluate( () => {
			const channel = ( value: number ) => ( value / 255 <= 0.03928 ? value / 255 / 12.92 : ( ( value / 255 + 0.055 ) / 1.055 ) ** 2.4 );
			const luminance = ( color: string ) => {
				const [ red, green, blue ] = ( color.match( /[\d.]+/g ) as string[] ).map( Number );

				return ( 0.2126 * channel( red ) ) + ( 0.7152 * channel( green ) ) + ( 0.0722 * channel( blue ) );
			};
			const text = getComputedStyle( document.querySelector( '.counterhand-page-head .counterhand-subtitle' ) as Element ).color;
			const tint = getComputedStyle( document.querySelector( '.counterhand-page-head' ) as Element ).backgroundColor;
			const [ light, dark ] = [ luminance( text ), luminance( tint ) ].sort( ( a, b ) => b - a );

			return ( light + 0.05 ) / ( dark + 0.05 );
		} );
		expect( ratio, 'purpose line on the mint tint' ).toBeGreaterThanOrEqual( 4.5 );
	} );
} );

test.describe( 'Overview', () => {
	test( 'lists what is left to set up, each with the one action that does it', async ( { page } ) => {
		const restore = patchSettings( { products_read: true, action_log_enabled: false } );
		try {
			await page.goto( DASHBOARD );
			const setup = page.locator( 'section', { has: page.getByRole( 'heading', { name: 'Finish setting up' } ) } );
			const area = setup.locator( 'li', { hasText: 'Choose what AI may reach' } );
			await expect( area.getByText( 'Done', { exact: true } ), 'an area can be read' ).toBeVisible();
			const record = setup.locator( 'li', { hasText: 'Record activity' } );
			await expect( record.getByRole( 'link', { name: 'Turn on' } ) ).toHaveAttribute( 'href', /counterhand-mcp-settings/ );
		} finally {
			restore();
		}
	} );

	test( 'says the log is off rather than showing a zero', async ( { page } ) => {
		const restore = patchSettings( { action_log_enabled: false } );
		try {
			await page.goto( DASHBOARD );
			await expect( page.getByText( 'Not recorded', { exact: true } ) ).toBeVisible();
			await expect( page.getByRole( 'link', { name: 'Turn on recording' } ) ).toBeVisible();
		} finally {
			restore();
		}
	} );

	test( 'counts real calls and names a failure', async ( { page, baseURL } ) => {
		const restore = patchSettings( { enabled: true, action_log_enabled: true, products_read: true } );
		const client = await anonymousContext( baseURL as string );
		try {
			wp( 'db', 'query', `TRUNCATE TABLE ${ wp( 'db', 'prefix' ).trim() }counterhand_action_log` );
			const { tokens, discovery } = await connect( page, baseURL as string, [ 'products:read' ] );
			await mcpCall( client, discovery.resource, tokens.access_token, 'tools/call', { name: 'list_products', arguments: { per_page: 1 } } );
			await mcpCall( client, discovery.resource, tokens.access_token, 'tools/call', { name: 'get_product', arguments: { id: 99999999 } } );

			await page.goto( DASHBOARD );
			const card = page.locator( 'section', { has: page.getByRole( 'heading', { name: 'Calls today' } ) } );
			await expect( card.locator( '.counterhand-stat' ) ).toHaveText( '2' );
			await expect( card.getByText( '1 failed.' ) ).toBeVisible();
			await expect( card.locator( 'code' ) ).toHaveText( 'get_product' );
		} finally {
			await client.dispose();
			restore();
		}
	} );

	test( 'asks for a review once an app is connected, and either answer retires it', async ( { page, baseURL } ) => {
		const forget = () => {
			try {
				wp( 'user', 'meta', 'delete', '1', 'counterhand_review_invitation' );
			} catch {
				// Nothing stored yet, which is what a new person looks like.
			}
		};
		const restore = patchSettings( { enabled: true, products_read: true } );
		forget();
		try {
			await connect( page, baseURL as string, [ 'products:read' ] );
			await page.goto( DASHBOARD );
			const card = page.locator( 'section', { has: page.getByRole( 'heading', { name: 'Does Counterhand help your store?' } ) } );
			await expect( card ).toBeVisible();

			await page.context().route( /wordpress\.org/, ( route ) => route.fulfill( { contentType: 'text/html', body: '<p>review form</p>' } ) );
			const [ review ] = await Promise.all( [ page.waitForEvent( 'popup' ), card.getByRole( 'button', { name: 'Write a review' } ).click() ] );
			await review.waitForLoadState();
			expect( review.url() ).toBe( 'https://wordpress.org/support/plugin/counterhand-mcp-for-woocommerce/reviews/#new-post' );
			await page.reload();
			await expect( card, 'asked once is enough' ).toHaveCount( 0 );

			forget();
			await page.reload();
			await card.getByRole( 'button', { name: 'Don’t show this again' } ).click();
			await expect( page ).toHaveURL( /page=counterhand-mcp-dashboard/ );
			await expect( card ).toHaveCount( 0 );
		} finally {
			forget();
			restore();
		}
	} );

	test( 'states the connector, what can be changed and who is on', async ( { page } ) => {
		const restore = patchSettings( { enabled: false, products_write: true, 'integration_e2e-fixture_read': true } );
		try {
			await page.goto( DASHBOARD );
			const connector = page.locator( 'section', { has: page.getByRole( 'heading', { name: 'AI access', exact: true } ) } );
			await expect( connector.locator( '.counterhand-stat' ) ).toHaveText( 'Off' );
			await expect( page.getByText( /AI can change: .*Products/ ) ).toBeVisible();
			await expect( page.locator( 'li', { hasText: 'E2e Fixture' } ).getByText( 'Can read', { exact: true } ) ).toBeVisible();
		} finally {
			restore();
		}
	} );

	test( 'a shop manager reads it, with no link to a save they cannot make', async ( { browser, baseURL } ) => {
		const restore = patchSettings( { action_log_enabled: false } );
		const context = await browser.newContext( { baseURL, storageState: shopManagerStatePath } );
		const page = await context.newPage();
		try {
			await page.goto( DASHBOARD );
			await expect( page.getByRole( 'heading', { name: 'AI access', exact: true } ) ).toBeVisible();
			await expect( page.getByText( 'Not recorded', { exact: true } ) ).toBeVisible();
			await expect( page.locator( 'li', { hasText: 'Record activity' } ).getByText( 'An administrator can set this.' ) ).toBeVisible();
			for ( const name of [ 'Change access', 'Choose areas', 'Turn on recording', 'Choose plugins' ] ) {
				await expect( page.locator( '.counterhand-card__foot' ).getByRole( 'link', { name, exact: true } ) ).toHaveCount( 0 );
			}
			await expect( page.getByRole( 'link', { name: 'Review or revoke them' } ) ).toBeVisible();
		} finally {
			await context.close();
			restore();
		}
	} );

	test( 'a shop manager reads Access and cannot save it', async ( { browser, baseURL } ) => {
		const restore = patchSettings( { 'integration_e2e-fixture_read': true } );
		const context = await browser.newContext( { baseURL, storageState: shopManagerStatePath } );
		const page = await context.newPage();
		try {
			await page.goto( SETTINGS );
			await expect( page.getByText( 'Only a site administrator can change these.' ) ).toHaveCount( 1 );
			await expect( page.locator( '.counterhand-settings-form input[type="checkbox"]' ).first() ).toBeDisabled();
			await expect( page.locator( '.counterhand-settings-form input:not([disabled])' ) ).toHaveCount( 0 );
			await expect( page.getByRole( 'button', { name: /save/i } ) ).toHaveCount( 0 );
		} finally {
			await context.close();
			restore();
		}
	} );

	test( 'an administrator saves the store areas and the other plugins apart', async ( { page } ) => {
		await page.goto( SETTINGS );
		await expect( page.getByText( 'Only a site administrator can change these.' ) ).toHaveCount( 0 );
		await expect( page.getByRole( 'button', { name: 'Save access' } ) ).toBeEnabled();
		await expect( page.getByRole( 'button', { name: 'Save other plugins' } ) ).toBeEnabled();
	} );

	test( 'the old Other plugins address leads to its section on Access', async ( { page } ) => {
		await page.goto( PLUGINS );
		await expect( page ).toHaveURL( /page=counterhand-mcp-settings#counterhand-other-plugins$/ );
		await expect( page.getByText( /Also works with these plugins once they are active: .*WPML/ ), 'names supported plugins that are off, never the store\'s own' ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: 'Other plugins', exact: true } ) ).toBeVisible();
	} );
} );

test.describe( 'Two forms, one option', () => {
	test( 'saving the plugins form leaves the connector and limits alone', async ( { page } ) => {
		const restore = patchSettings( { enabled: true, orders_read: true, rate_limit_per_minute: 7 } );
		try {
			await page.goto( PLUGINS );
			await page.locator( '#counterhand-integration_e2e-fixture_read' ).check();
			await page.getByRole( 'button', { name: 'Save other plugins' } ).click();
			await expect( page.getByText( 'Settings saved.' ), 'the person is told the save worked' ).toBeVisible();
			await expect( page.locator( '.counterhand-page-head .notice' ), 'notices sit below the header, not beside the title' ).toHaveCount( 0 );
			await expect( page.locator( '#counterhand-integration_e2e-fixture_read' ) ).toBeChecked();

			expect( stored() ).toMatchObject( { enabled: true, orders_read: true, rate_limit_per_minute: 7, 'integration_e2e-fixture_read': true } );
		} finally {
			restore();
		}
	} );

	test( 'saving the settings form leaves a plugin switched on', async ( { page } ) => {
		const restore = patchSettings( { enabled: true, 'integration_e2e-fixture_read': true } );
		try {
			await page.goto( SETTINGS );
			await expect( page.locator( '#counterhand-integration_e2e-fixture_read' ), 'the plugin sits in its own form below' ).toBeChecked();
			await page.getByRole( 'button', { name: 'Save access' } ).click();
			await page.waitForURL( /settings-updated=true/ );
			await expect( page.getByText( 'Settings saved.' ), 'the person is told the save worked' ).toBeVisible();
			await expect( page.locator( '.counterhand-page-head .notice' ), 'notices sit below the header, not beside the title' ).toHaveCount( 0 );

			expect( stored()[ 'integration_e2e-fixture_read' ] ).toBe( true );
		} finally {
			restore();
		}
	} );

	test( 'a store that ticked a plugin before this screen existed still has it ticked', async ( { page } ) => {
		// The stored row is the same shape it always was, so an update needs no migration.
		const restore = patchSettings( { 'integration_e2e-fixture_read': true } );
		try {
			await page.goto( PLUGINS );
			await expect( page.locator( '#counterhand-integration_e2e-fixture_read' ) ).toBeChecked();
		} finally {
			restore();
		}
	} );
} );

test( 'the chat sends a plugin that is switched off to the screen that holds its switch', async ( { page } ) => {
	const restore = patchSettings( { 'integration_e2e-fixture_read': true } );
	try {
		await page.goto( '/wp-admin/admin.php?page=counterhand-mcp' );
		await page.locator( 'details', { has: page.locator( 'input[name="counterhand_chat_groups[]"]' ) } ).first().locator( 'summary' ).click();
		await page.locator( 'input[name="counterhand_chat_groups[]"][value="integration:e2e-fixture"]' ).check();
		await page.getByRole( 'button', { name: 'Save areas' } ).click();
		await page.waitForLoadState();
	} finally {
		restore();
	}
	try {
		await page.goto( '/wp-admin/admin.php?page=counterhand-mcp' );
		await page.locator( 'details', { has: page.locator( 'input[name="counterhand_chat_groups[]"]' ) } ).first().locator( 'summary' ).click();
		await expect( page.locator( 'a[href$="#counterhand-other-plugins"]', { hasText: 'Switch it on in Access' } ), 'a plugin leads to its section on Access' ).toBeVisible();
	} finally {
		wp( 'option', 'delete', 'counterhand_chat' );
	}
} );

test( 'Connect marks its current view without colour alone', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=counterhand-mcp-connect&view=connections' );
	await expect( page.getByRole( 'link', { name: /Connected apps/ } ).and( page.locator( '[aria-current="page"]' ) ) ).toBeVisible();
	await expect( page.locator( '.nav-tab' ) ).toHaveCount( 0 );
} );

