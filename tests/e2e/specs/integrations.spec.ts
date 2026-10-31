import { expect, test } from '@playwright/test';
import { listToolNames, mcpCall } from '../support/mcp';
import { anonymousContext, connect } from '../support/oauth';
import { adminStatePath, patchSettings, wp } from '../support/store';

const CONTACT_FORM_READ = 'integration:contact-form-7:read';
const CONTACT_FORM_WRITE = 'integration:contact-form-7:write';
const FLAMINGO_READ = 'integration:flamingo:read';
const FLAMINGO_WRITE = 'integration:flamingo:write';

/*
 * The real plugins, at whatever version is current: CI builds a fresh store daily.
 * These assert what no schema can, so a release that changes behaviour fails here
 * rather than on a store. See CLAUDE.md, "Integrations must survive".
 */
test.describe( 'Integrations with the real plugins', () => {
	let restore: () => void;

	test.beforeAll( () => {
		wp( 'plugin', 'activate', 'contact-form-7', 'flamingo' );
		restore = patchSettings( {
			'integration_contact-form-7_read': true,
			'integration_contact-form-7_write': true,
			integration_flamingo_read: true,
			integration_flamingo_write: true,
		} );
	} );

	test.afterAll( () => {
		restore();
		wp( 'plugin', 'deactivate', 'contact-form-7', 'flamingo' );
	} );

	async function tokenFor( browser: import('@playwright/test').Browser, baseURL: string, scopes: string[] ) {
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		const { tokens, discovery } = await connect( page, baseURL, scopes );
		await page.close();
		const client = await anonymousContext( baseURL );

		return {
			client,
			names: () => listToolNames( client, discovery.resource, tokens.access_token ),
			call: async ( name: string, args: Record< string, unknown > ) => ( await ( await mcpCall( client, discovery.resource, tokens.access_token, 'tools/call', { name, arguments: args } ) ).json() ).result,
		};
	}

	test( 'a Contact Form 7 form can be created, edited, read back and deleted', async ( { browser, baseURL } ) => {
		const { client, call } = await tokenFor( browser, baseURL as string, [ CONTACT_FORM_READ, CONTACT_FORM_WRITE ] );

		const created = ( await call( 'create_contact_form', { title: 'E2E enquiry', form: '[email* your-email] [submit "Send"]', confirm: true } ) ).structuredContent[ 'contact form' ];
		expect( created.id ).toBeGreaterThan( 0 );
		// CF7 only saves when context is "save", so a tool that forgot would still answer with the form.
		expect( wp( 'post', 'get', String( created.id ), '--field=post_title' ) ).toBe( 'E2E enquiry' );

		const unconfirmed = await call( 'update_contact_form', { id: created.id, title: 'Renamed', confirm: false } );
		expect( unconfirmed.isError ).toBe( true );
		expect( wp( 'post', 'get', String( created.id ), '--field=post_title' ) ).toBe( 'E2E enquiry' );

		await call( 'update_contact_form', { id: created.id, title: 'Renamed', mail: { recipient: 'sales@example.test', subject: 'New', sender: 'shop@example.test', body: 'Hello' }, confirm: true } );
		const read = ( await call( 'get_contact_form', { id: created.id } ) ).structuredContent[ 'contact form' ];
		expect( read.title ).toBe( 'Renamed' );
		expect( read.properties.mail.recipient ).toBe( 'sales@example.test' );
		expect( read.properties.form.content, 'a partial edit keeps the rest' ).toContain( 'your-email' );

		const listed = ( await call( 'get_contact_forms', { search: 'Renamed', per_page: 5 } ) ).structuredContent[ 'contact forms' ];
		expect( listed.map( ( form: { id: number } ) => form.id ) ).toContain( created.id );

		const leaking = await call( 'update_contact_form', { id: created.id, mail: { recipient: 'someone@example.test', attachments: 'uploads/wc-logs/secret.log' }, confirm: true } );
		expect( leaking.isError, 'a file on the server cannot be attached' ).toBe( true );
		const hidden = await call( 'update_contact_form', { id: created.id, mail: { recipient: 'someone@example.test', attachments: '[x]\rdebug.log' }, confirm: true } );
		expect( hidden.isError, 'nor hidden behind a carriage return, which CF7 turns into a new line' ).toBe( true );
		expect( wp( 'post', 'meta', 'get', String( created.id ), '_mail' ), 'nothing was stored' ).not.toContain( 'debug.log' );
		const upload = await call( 'update_contact_form', { id: created.id, mail: { recipient: 'sales@example.test', attachments: '[your-file]' }, confirm: true } );
		expect( upload.isError, 'a visitor upload can' ).toBe( false );

		expect( ( await call( 'delete_contact_form', { id: created.id, confirm: false } ) ).isError ).toBe( true );
		expect( ( await call( 'delete_contact_form', { id: created.id, confirm: true } ) ).structuredContent[ 'contact form' ].deleted ).toBe( true );
		await client.dispose();
	} );

	test( 'Flamingo messages can be read and trashed, and no other post can be reached', async ( { browser, baseURL } ) => {
		const id = wp( 'eval', `echo Flamingo_Inbound_Message::add( [ 'channel' => 'e2e', 'subject' => 'Hello', 'from' => 'Jane <jane@example.test>', 'from_name' => 'Jane', 'from_email' => 'jane@example.test', 'fields' => [ 'your-message' => 'Do you ship to Graz?' ], 'meta' => [ 'remote_ip' => '203.0.113.9' ] ] )->id();` ).trim();
		const otherPost = wp( 'post', 'create', '--post_title=Not a message', '--post_status=publish', '--porcelain' ).trim();
		const { client, call } = await tokenFor( browser, baseURL as string, [ FLAMINGO_READ, FLAMINGO_WRITE ] );

		try {
			expect( ( await call( 'list_inbound_messages', { search: '203.0.113.9' } ) ).structuredContent.total, 'search cannot be used to ask about an IP' ).toBe( 0 );

			const listed = ( await call( 'list_inbound_messages', { search: 'jane@example.test' } ) ).structuredContent;
			expect( listed.messages.map( ( message: { id: number } ) => message.id ) ).toContain( Number( id ) );
			expect( listed.total ).toBeGreaterThan( 0 );

			const message = ( await call( 'get_inbound_message', { id: Number( id ) } ) ).structuredContent.message;
			expect( message.from_email ).toBe( 'jane@example.test' );
			expect( message.fields[ 'your-message' ] ).toBe( 'Do you ship to Graz?' );
			expect( JSON.stringify( message ), 'the IP stays in Flamingo' ).not.toContain( '203.0.113.9' );

			expect( ( await call( 'get_inbound_message', { id: Number( otherPost ) } ) ).isError ).toBe( true );
			expect( ( await call( 'trash_inbound_message', { id: Number( otherPost ), confirm: true } ) ).isError ).toBe( true );
			expect( wp( 'post', 'get', otherPost, '--field=post_status' ) ).toBe( 'publish' );

			expect( ( await call( 'trash_inbound_message', { id: Number( id ), confirm: false } ) ).isError ).toBe( true );
			expect( wp( 'post', 'get', id, '--field=post_status' ) ).toBe( 'publish' );
			expect( ( await call( 'trash_inbound_message', { id: Number( id ), confirm: true } ) ).structuredContent.trashed ).toBe( true );
			expect( wp( 'post', 'get', id, '--field=post_status' ) ).toBe( 'trash' );
			expect( ( await call( 'trash_inbound_message', { id: Number( id ), confirm: true } ) ).structuredContent.trashed, 'a retry is not a failure' ).toBe( true );
		} finally {
			wp( 'post', 'delete', id, otherPost, '--force' );
			await client.dispose();
		}
	} );

	test( 'a plugin going away hides its tools and its row, and coming back restores them', async ( { browser, baseURL } ) => {
		const { client, names, call } = await tokenFor( browser, baseURL as string, [ CONTACT_FORM_READ, 'products:read' ] );
		expect( await names() ).toContain( 'get_contact_forms' );

		wp( 'plugin', 'deactivate', 'contact-form-7' );
		try {
			expect( await names() ).not.toContain( 'get_contact_forms' );
			expect( await call( 'get_contact_forms', {} ) ).toBeUndefined();
			expect( ( await call( 'describe_woocommerce_fields', { tool: 'get_contact_forms' } ) ) ?? { isError: true } ).toMatchObject( { isError: true } );

			const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
			await page.goto( '/wp-admin/admin.php?page=counterhand-mcp-settings' );
			await expect( page.locator( '#counterhand-integration_contact-form-7_read' ) ).toHaveCount( 0 );
			await page.close();
		} finally {
			wp( 'plugin', 'activate', 'contact-form-7' );
		}

		expect( await names(), 'the same token, no reconnect' ).toContain( 'get_contact_forms' );
		await client.dispose();
	} );
} );
