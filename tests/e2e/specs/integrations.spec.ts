import { expect, test } from '@playwright/test';
import { listToolNames, mcpCall } from '../support/mcp';
import { anonymousContext, connect } from '../support/oauth';
import { adminStatePath, patchSettings, shopManagerStatePath, wp } from '../support/store';

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

	test( 'what cannot be stored safely is refused, and what get returns cannot be sent back by mistake', async ( { browser, baseURL } ) => {
		const { client, call } = await tokenFor( browser, baseURL as string, [ CONTACT_FORM_READ, CONTACT_FORM_WRITE ] );
		const created = ( await call( 'create_contact_form', { title: 'E2E guards', form: '[text your-name] [submit]', confirm: true } ) ).structuredContent[ 'contact form' ];
		const id = created.id;
		const form = () => wp( 'post', 'meta', 'get', String( id ), '_form' );

		const read = ( await call( 'get_contact_form', { id } ) ).structuredContent[ 'contact form' ];
		expect( Object.keys( read.properties ).sort() ).toEqual( [ 'additional_settings', 'form', 'mail', 'mail_2', 'messages' ] );

		// A wrong type used to be stored as the word "Array", or blank the whole mail, and report success.
		const sentBack = await call( 'update_contact_form', { id, form: read.properties.form, confirm: true } );
		expect( sentBack, 'an object where a string belongs is refused' ).toBeUndefined();
		expect( form() ).toContain( 'your-name' );
		const mailAsText = await call( 'update_contact_form', { id, mail: 'send it to sales@example.test', confirm: true } );
		expect( mailAsText ).toBeUndefined();
		expect( wp( 'post', 'meta', 'get', String( id ), '_mail', '--format=json' ) ).not.toContain( '"recipient":""' );

		const hash = await call( 'update_contact_form', { id, form: '[hidden h default:user_user_pass][submit]', confirm: true } );
		expect( hash.isError, 'a default that prints the password hash' ).toBe( true );
		expect( form() ).not.toContain( 'user_user_pass' );

		const ip = await call( 'update_contact_form', { id, additional_settings: 'flamingo_subject: [_remote_ip]', confirm: true } );
		expect( ip.isError, 'an IP copied into what Flamingo returns' ).toBe( true );

		// An administrator holds unfiltered_html, but a token should not inherit it.
		await call( 'update_contact_form', { id, form: '<script>window.counterhandProbe=1</script>[submit]', confirm: true } );
		expect( form() ).not.toContain( '<script' );

		await call( 'delete_contact_form', { id, confirm: true } );
		await client.dispose();
	} );

	test( 'a form keeps its Brevo settings and its existing attachment through an edit', async ( { browser, baseURL } ) => {
		const { client, call } = await tokenFor( browser, baseURL as string, [ CONTACT_FORM_READ, CONTACT_FORM_WRITE ] );
		const id = ( await call( 'create_contact_form', { title: 'E2E keep', confirm: true } ) ).structuredContent[ 'contact form' ].id;
		wp( 'eval', `WPCF7::update_option( 'sendinblue', array( 'api_key' => 'dummy' ) );` );
		wp( 'post', 'meta', 'update', String( id ), '_sendinblue', JSON.stringify( { enable_contact_list: true, contact_lists: [ 7 ], enable_transactional_email: true, email_template: 3 } ), '--format=json' );
		const mail = JSON.parse( wp( 'post', 'meta', 'get', String( id ), '_mail', '--format=json' ) );
		wp( 'post', 'meta', 'update', String( id ), '_mail', JSON.stringify( { ...mail, attachments: 'uploads/brochure.pdf' } ), '--format=json' );

		try {
			const renamed = await call( 'update_contact_form', { id, title: 'E2E kept', mail: { ...mail, attachments: 'uploads/brochure.pdf' }, confirm: true } );
			expect( renamed.isError, 'a path the form already had may stay' ).toBe( false );
			expect( JSON.parse( wp( 'post', 'meta', 'get', String( id ), '_sendinblue', '--format=json' ) ).email_template ).toBe( 3 );

			const added = await call( 'update_contact_form', { id, mail: { ...mail, attachments: 'uploads/brochure.pdf\ndebug.log' }, confirm: true } );
			expect( added.isError, 'a new one may not' ).toBe( true );
		} finally {
			wp( 'option', 'delete', 'wpcf7' );
			await call( 'delete_contact_form', { id, confirm: true } );
			await client.dispose();
		}
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

test.describe( 'Advanced Custom Fields with the real plugin', () => {
	let restore: () => void;
	let post: string;
	let product: string;

	const ACF_READ = 'integration:acf:read';
	const ACF_WRITE = 'integration:acf:write';

	test.beforeAll( () => {
		wp( 'plugin', 'activate', 'advanced-custom-fields' );
		restore = patchSettings( { integration_acf_read: true, integration_acf_write: true } );
		const location = [ [ { param: 'post_type', operator: '==', value: 'post' } ], [ { param: 'post_type', operator: '==', value: 'product' } ] ];
		const text = ( key: string, name: string, type = 'text' ) => ( { key: `field_${ key }`, label: key, name, type } );
		const group = ( key: string, showInRest: number, fields: object[] ) => `acf_import_field_group( json_decode( '${ JSON.stringify( { key: `group_${ key }`, title: key, show_in_rest: showInRest, location, fields } ) }', true ) );`;
		wp( 'eval', group( 'e2e_open', 1, [
			text( 'care', 'care_notes' ),
			text( 'secret', 'api_secret', 'password' ),
			{ ...text( 'nested', 'nested_box', 'group' ), sub_fields: [ text( 'inner_note', 'inner_note' ), text( 'inner_secret', 'inner_secret', 'password' ) ] },
		] ) + group( 'e2e_closed', 0, [ text( 'cost', 'supplier_cost' ) ] ) );
		post = wp( 'post', 'create', '--post_title=ACF post', '--post_status=publish', '--porcelain' ).trim();
		product = wp( 'post', 'create', '--post_type=product', '--post_title=ACF product', '--post_status=publish', '--porcelain' ).trim();
		wp( 'post', 'meta', 'set', post, 'supplier_cost', '12.50' );
	} );

	test.afterAll( () => {
		wp( 'post', 'delete', post, product, '--force' );
		wp( 'post', 'delete', ...wp( 'post', 'list', '--post_type=acf-field-group,acf-field', '--post_status=any', '--field=ID' ).split( /\s+/ ).filter( Boolean ), '--force' );
		restore();
		wp( 'plugin', 'deactivate', 'advanced-custom-fields' );
	} );

	async function caller( browser: import('@playwright/test').Browser, baseURL: string, scopes: string[] ) {
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

	test( 'field values are read and changed over MCP, on posts and products', async ( { browser, baseURL } ) => {
		const { client, call } = await caller( browser, baseURL as string, [ ACF_READ, ACF_WRITE ] );

		await call( 'update_post_custom_fields', { id: Number( post ), acf: { care_notes: 'Keep dry' } } );
		expect( wp( 'post', 'meta', 'get', post, 'care_notes' ) ).toBe( 'Keep dry' );
		expect( ( await call( 'get_post_custom_fields', { id: Number( post ) } ) ).structuredContent.post.acf.care_notes ).toBe( 'Keep dry' );

		await call( 'update_product_custom_fields', { id: Number( product ), acf: { care_notes: 'Fragile' } } );
		expect( ( await call( 'get_product_custom_fields', { id: Number( product ) } ) ).structuredContent.product.acf.care_notes ).toBe( 'Fragile' );
		await client.dispose();
	} );

	test( 'a field group not shown in REST stays out', async ( { browser, baseURL } ) => {
		const { client, call } = await caller( browser, baseURL as string, [ ACF_READ, ACF_WRITE ] );

		const read = ( await call( 'get_post_custom_fields', { id: Number( post ) } ) ).structuredContent.post.acf;
		expect( read ).not.toHaveProperty( 'supplier_cost' );

		await call( 'update_post_custom_fields', { id: Number( post ), acf: { supplier_cost: '0.01' } } );
		expect( wp( 'post', 'meta', 'get', post, 'supplier_cost' ) ).toBe( '12.50' );
		await client.dispose();
	} );

	test( 'keys ACF honours inside acf cannot reach a group not shown in REST', async ( { browser, baseURL } ) => {
		const { client, call } = await caller( browser, baseURL as string, [ ACF_READ, ACF_WRITE ] );

		await call( 'update_post_custom_fields', { id: Number( post ), acf: { _acf_field_group_scope: [ 'group_e2e_closed' ], supplier_cost: '0.01' } } );
		await call( 'update_post_custom_fields', { id: Number( post ), acf: { _acf_field_key_map: { care_notes: 'field_cost' }, care_notes: '0.02' } } );
		await call( 'update_post_custom_fields', { id: Number( post ), acf: { field_cost: '0.03' } } );

		expect( wp( 'post', 'meta', 'get', post, 'supplier_cost' ) ).toBe( '12.50' );
		await client.dispose();
	} );

	test( 'password fields are neither returned nor set, at any depth, and ACF\'s own REST API is untouched', async ( { browser, baseURL } ) => {
		const { client, call } = await caller( browser, baseURL as string, [ ACF_READ, ACF_WRITE ] );
		wp( 'post', 'meta', 'set', post, 'api_secret', 'hunter2' );
		wp( 'post', 'meta', 'set', post, '_api_secret', 'field_secret' );
		wp( 'post', 'meta', 'set', post, 'nested_box_inner_secret', 'nested-hunter2' );
		wp( 'post', 'meta', 'set', post, '_nested_box_inner_secret', 'field_inner_secret' );

		const read = ( await call( 'get_post_custom_fields', { id: Number( post ) } ) ).structuredContent;
		expect( JSON.stringify( read ) ).not.toContain( 'hunter2' );
		expect( read.post.acf ).not.toHaveProperty( 'api_secret' );

		const written = ( await call( 'update_post_custom_fields', { id: Number( post ), acf: { api_secret: 'changed' } } ) ).structuredContent;
		expect( JSON.stringify( written ) ).not.toContain( 'hunter2' );
		expect( wp( 'post', 'meta', 'get', post, 'api_secret' ) ).toBe( 'hunter2' );

		// Same request as above in shape, without the marker: the guard must not leak out of its own calls.
		const own = wp( 'eval', `wp_set_current_user( 1 ); echo wp_json_encode( rest_do_request( new WP_REST_Request( chr( 71 ) . chr( 69 ) . chr( 84 ), chr( 47 ) . implode( chr( 47 ), array( chr( 119 ) . chr( 112 ), chr( 118 ) . 2, chr( 112 ) . chr( 111 ) . chr( 115 ) . chr( 116 ) . chr( 115 ), ${ post } ) ) ) )->get_data() );` );
		expect( own ).toContain( 'hunter2' );
		await client.dispose();
	} );

	test( 'null clears a field', async ( { browser, baseURL } ) => {
		const { client, call } = await caller( browser, baseURL as string, [ ACF_READ, ACF_WRITE ] );

		await call( 'update_post_custom_fields', { id: Number( post ), acf: { care_notes: 'Temporary' } } );
		await call( 'update_post_custom_fields', { id: Number( post ), acf: { care_notes: null } } );

		expect( ( await call( 'get_post_custom_fields', { id: Number( post ) } ) ).structuredContent.post.acf.care_notes ?? '' ).toBe( '' );
		await client.dispose();
	} );

	test( 'a shop manager is held to what WordPress lets them do with each item', async ( { browser, baseURL } ) => {
		const context = await browser.newContext( { baseURL, storageState: shopManagerStatePath } );
		const page = await context.newPage();
		const { tokens, discovery } = await connect( page, baseURL as string, [ ACF_READ, ACF_WRITE ] );
		await context.close();
		const client = await anonymousContext( baseURL as string );
		const call = async ( name: string, args: Record< string, unknown > ) => ( await ( await mcpCall( client, discovery.resource, tokens.access_token, 'tools/call', { name, arguments: args } ) ).json() ).result;

		expect( ( await call( 'get_post_custom_fields', { id: Number( post ) } ) ).isError ).toBe( false );

		const missing = await call( 'get_post_custom_fields', { id: 99999999 } );
		expect( missing.isError ).toBe( true );
		await client.dispose();
	} );

	test( 'an ACF grant reaches nothing the content and products scopes gate', async ( { browser, baseURL } ) => {
		const { client, names, call } = await caller( browser, baseURL as string, [ ACF_READ, ACF_WRITE ] );
		const before = await names();

		expect( before, 'no other tool comes with it' ).not.toContain( 'update_post' );
		await call( 'update_post_custom_fields', { id: Number( post ), acf: { care_notes: 'x' }, title: 'Hijacked', status: 'draft', content: 'Hijacked' } );
		expect( wp( 'post', 'get', post, '--field=post_title' ) ).toBe( 'ACF post' );
		expect( wp( 'post', 'get', post, '--field=post_status' ) ).toBe( 'publish' );

		await call( 'update_product_custom_fields', { id: Number( product ), acf: { care_notes: 'x' }, title: 'Hijacked', status: 'draft' } );
		expect( wp( 'post', 'get', product, '--field=post_title' ) ).toBe( 'ACF product' );
		expect( wp( 'post', 'get', product, '--field=post_status' ) ).toBe( 'publish' );

		const wrongType = await call( 'update_post_custom_fields', { id: Number( product ), acf: { care_notes: 'crossed' } } );
		expect( wrongType.isError, 'an id of another post type is refused by the route' ).toBe( true );
		await client.dispose();
	} );

	test( 'ACF going away hides the tools even though the routes remain', async ( { browser, baseURL } ) => {
		const { client, names } = await caller( browser, baseURL as string, [ ACF_READ ] );
		expect( await names() ).toContain( 'get_post_custom_fields' );

		wp( 'plugin', 'deactivate', 'advanced-custom-fields' );
		try {
			expect( await names() ).not.toContain( 'get_post_custom_fields' );
		} finally {
			wp( 'plugin', 'activate', 'advanced-custom-fields' );
		}
		await client.dispose();
	} );
} );
