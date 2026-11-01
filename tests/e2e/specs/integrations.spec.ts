import { existsSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test } from '@playwright/test';
import { listToolNames, mcpCall } from '../support/mcp';
import { anonymousContext, connect } from '../support/oauth';
import { adminStatePath, patchOption, patchSettings, shopManagerStatePath, stateDirectory, wp } from '../support/store';

const hasPaidPlugin = ( slug: string ) => existsSync( join( stateDirectory, 'wordpress', 'wp-content', 'plugins', slug ) );

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

			for ( const change of [ { recipient: 'attacker@example.test' }, { recipient: '[your-email]' }, { additional_headers: 'Bcc: attacker@example.test' } ] ) {
				const redirected = await call( 'update_contact_form', { id, mail: { ...mail, attachments: 'uploads/brochure.pdf', ...change }, confirm: true } );
				expect( redirected.isError, `it cannot be sent somewhere new: ${ JSON.stringify( change ) }` ).toBe( true );
			}
			expect( wp( 'post', 'meta', 'get', String( id ), '_mail' ), 'nothing was stored' ).not.toContain( 'attacker' );

			const moved = await call( 'update_contact_form', { id, mail: { ...mail, attachments: '[your-file]', recipient: 'someone@example.test' }, confirm: true } );
			expect( moved.isError, 'with no server file the recipient is free' ).toBe( false );
		} finally {
			wp( 'option', 'delete', 'wpcf7' );
			await call( 'delete_contact_form', { id, confirm: true } );
			await client.dispose();
		}
	} );

	test( 'a Flamingo field can use the form\'s own fields and nothing else', async ( { browser, baseURL } ) => {
		const { client, call } = await tokenFor( browser, baseURL as string, [ CONTACT_FORM_READ, CONTACT_FORM_WRITE ] );
		const id = ( await call( 'create_contact_form', { title: 'E2E flamingo', form: '[email* your-email] [text your-subject]', confirm: true } ) ).structuredContent[ 'contact form' ].id;

		try {
			for ( const settings of [ 'flamingo_email: [_remote_ip]', 'flamingo_email: [wpcf7.remote_ip]', 'demo: on\rflamingo_email: [_remote_ip]', 'flamingo_name: [_user_agent]', 'flamingo_subject: [_raw_your-subject]' ] ) {
				expect( ( await call( 'update_contact_form', { id, additional_settings: settings, confirm: true } ) ).isError, settings ).toBe( true );
			}
			expect( wp( 'post', 'meta', 'list', String( id ), '--format=csv' ) ).not.toContain( 'flamingo_' );

			expect( ( await call( 'update_contact_form', { id, additional_settings: 'flamingo_email: [your-email]\nflamingo_subject: [your-subject]', confirm: true } ) ).isError ).toBe( false );
			expect( wp( 'post', 'meta', 'get', String( id ), '_additional_settings' ) ).toContain( 'flamingo_email: [your-email]' );
		} finally {
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

	test( 'a password inside a group cannot be overwritten, while the fields beside it can', async ( { browser, baseURL } ) => {
		const { client, call } = await caller( browser, baseURL as string, [ ACF_READ, ACF_WRITE ] );
		wp( 'post', 'meta', 'set', post, 'nested_box_inner_secret', 'nested-hunter2' );
		wp( 'post', 'meta', 'set', post, '_nested_box_inner_secret', 'field_inner_secret' );

		const written = await call( 'update_post_custom_fields', { id: Number( post ), acf: { nested_box: { inner_note: 'beside it', inner_secret: 'changed' } } } );

		expect( written.isError ).toBe( false );
		expect( wp( 'post', 'meta', 'get', post, 'nested_box_inner_secret' ) ).toBe( 'nested-hunter2' );
		expect( wp( 'post', 'meta', 'get', post, 'nested_box_inner_note' ) ).toBe( 'beside it' );
		await client.dispose();
	} );

	test( 'a field stored under a protected name cannot be written, even in a group shown in REST', async ( { browser, baseURL } ) => {
		const { client, call } = await caller( browser, baseURL as string, [ ACF_READ, ACF_WRITE ] );
		const location = [ [ { param: 'post_type', operator: '==', value: 'product' } ] ];
		wp( 'eval', `acf_import_field_group( json_decode( '${ JSON.stringify( { key: 'group_e2e_price', title: 'price', show_in_rest: 1, location, fields: [ { key: 'field_e2e_price', label: 'price', name: '_price', type: 'text' } ] } ) }', true ) );` );
		wp( 'post', 'meta', 'set', product, '_price', '10' );

		const refused = await call( 'update_product_custom_fields', { id: Number( product ), acf: { _price: '0.01' } } );

		expect( refused.isError ).toBe( true );
		expect( wp( 'post', 'meta', 'get', product, '_price' ) ).toBe( '10' );
		await client.dispose();
	} );

	test( 'ACF\'s own AI access adds no tool to an ACF grant, because it reaches past our guards', async ( { browser, baseURL } ) => {
		wp( 'option', 'update', 'counterhand_e2e_acf_ai', '1' );
		try {
			expect( wp( 'eval', 'echo implode( chr( 44 ), array_keys( wp_get_abilities() ) );' ), 'ACF really is offering them' ).toContain( 'acf/' );

			const { client, names } = await caller( browser, baseURL as string, [ ACF_READ, ACF_WRITE ] );
			expect( ( await names() ).sort() ).toEqual( [ 'get_page_custom_fields', 'get_post_custom_fields', 'get_product_custom_fields', 'update_page_custom_fields', 'update_post_custom_fields', 'update_product_custom_fields' ] );
			await client.dispose();
		} finally {
			wp( 'option', 'delete', 'counterhand_e2e_acf_ai' );
		}
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

test.describe( 'Germanized with the real plugin', () => {
	let restore: () => void;
	let product: string;
	let deliveryTime: number;

	const READ = 'integration:germanized:read';
	const WRITE = 'integration:germanized:write';

	test.beforeAll( () => {
		wp( 'plugin', 'activate', 'woocommerce-germanized' );
		restore = patchSettings( { integration_germanized_read: true, integration_germanized_write: true, customers_read: true, orders_read: true, products_read: true, products_write: true } );
		product = wp( 'wc', 'product', 'create', '--name=Legal product', '--regular_price=10', '--status=publish', '--user=e2e-admin', '--porcelain' ).trim();
	} );

	test.afterAll( () => {
		wp( 'post', 'delete', product, '--force' );
		restore();
		wp( 'plugin', 'deactivate', 'woocommerce-germanized' );
	} );

	async function caller( browser: import('@playwright/test').Browser, baseURL: string, scopes: string[] ) {
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		const { tokens, discovery } = await connect( page, baseURL, scopes );
		await page.close();
		const client = await anonymousContext( baseURL );

		return {
			client,
			names: () => listToolNames( client, discovery.resource, tokens.access_token ),
			published: async ( name: string ) => Object.keys( ( await ( await mcpCall( client, discovery.resource, tokens.access_token, 'tools/list' ) ).json() ).result.tools.find( ( tool: { name: string } ) => tool.name === name ).inputSchema.properties ),
			call: async ( name: string, args: Record< string, unknown > ) => ( await ( await mcpCall( client, discovery.resource, tokens.access_token, 'tools/call', { name, arguments: args } ) ).json() ).result,
		};
	}

	test( 'a delivery time is created and set on a product, then found, then cleared', async ( { browser, baseURL } ) => {
		const { client, call } = await caller( browser, baseURL as string, [ READ, WRITE ] );

		const name = `E2E 2-3 days ${ Date.now() }`;
		deliveryTime = ( await call( 'create_delivery_time', { name } ) ).structuredContent[ 'delivery time' ].id;
		expect( deliveryTime ).toBeGreaterThan( 0 );

		const listed = ( await call( 'get_delivery_times', { search: name } ) ).structuredContent[ 'delivery times' ];
		expect( listed.map( ( term: { id: number } ) => term.id ) ).toContain( deliveryTime );

		const before = ( await call( 'get_products_legal_details', { include: [ Number( product ) ] } ) ).structuredContent.products[ 0 ];
		expect( before.delivery_time, 'how an agent finds a product missing one' ).toEqual( [] );

		const unconfirmed = await call( 'update_product_legal_details', { id: Number( product ), delivery_time: { id: deliveryTime }, confirm: false } );
		expect( unconfirmed.isError ).toBe( true );

		const abroad = ( await call( 'create_delivery_time', { name: `${ name } abroad` } ) ).structuredContent[ 'delivery time' ].id;
		await call( 'update_product_legal_details', { id: Number( product ), delivery_time: { id: deliveryTime }, country_specific_delivery_times: [ { country: 'CH', id: abroad } ], confirm: true } );
		const after = ( await call( 'get_product_legal_details', { id: Number( product ) } ) ).structuredContent.product;
		expect( after.delivery_time.id ).toBe( deliveryTime );
		expect( after.country_specific_delivery_times.map( ( row: { country: string } ) => row.country ) ).toContain( 'CH' );

		await call( 'update_product_legal_details', { id: Number( product ), country_specific_delivery_times: [ { country: 'AT', id: deliveryTime } ], confirm: true } );
		const merged = ( await call( 'get_product_legal_details', { id: Number( product ) } ) ).structuredContent.product.country_specific_delivery_times.map( ( row: { country: string } ) => row.country );
		expect( merged, 'countries merge' ).toContain( 'CH' );
		expect( merged, 'and one matching the main delivery time is not stored, as the description says' ).not.toContain( 'AT' );

		await call( 'update_product_legal_details', { id: Number( product ), mini_desc: 'Keep dry', confirm: true } );
		expect( ( await call( 'get_product_legal_details', { id: Number( product ) } ) ).structuredContent.product.delivery_time.id, 'only what is sent changes' ).toBe( deliveryTime );

		const clearing = await call( 'update_product_legal_details', { id: Number( product ), delivery_time: null, country_specific_delivery_times: [], confirm: true } );
		expect( clearing.isError, JSON.stringify( clearing ) ).toBe( false );
		const cleared = ( await call( 'get_product_legal_details', { id: Number( product ) } ) ).structuredContent.product;
		expect( cleared.delivery_time?.id ).toBeUndefined();
		expect( cleared.country_specific_delivery_times ).toEqual( [] );

		await call( 'delete_delivery_time', { id: abroad, confirm: true } );
		expect( ( await call( 'delete_delivery_time', { id: deliveryTime, confirm: false } ) ).isError ).toBe( true );
		expect( ( await call( 'delete_delivery_time', { id: deliveryTime, confirm: true } ) ).isError ).toBe( false );
		expect( wp( 'term', 'list', 'product_delivery_time', '--format=ids' ) ).not.toContain( String( deliveryTime ) );
		await client.dispose();
	} );

	test( 'a term that does not exist is refused, because Germanized would create or clear one and report success', async ( { browser, baseURL } ) => {
		const { client, call } = await caller( browser, baseURL as string, [ READ, WRITE ] );
		const before = wp( 'term', 'list', 'product_delivery_time', '--format=count' );

		for ( const delivery_time of [ { id: 999999 }, { name: '2-3 days' }, { slug: 'no-such-term' } ] ) {
			const refused = await call( 'update_product_legal_details', { id: Number( product ), delivery_time, confirm: true } );
			expect( refused.isError, JSON.stringify( delivery_time ) ).toBe( true );
		}
		expect( ( await call( 'update_product_legal_details', { id: Number( product ), country_specific_delivery_times: [ { country: 'AT', id: 999999 } ], confirm: true } ) ).isError ).toBe( true );
		expect( ( await call( 'update_product_legal_details', { id: Number( product ), unit: { id: 999999 }, confirm: true } ) ).isError ).toBe( true );
		expect( wp( 'term', 'list', 'product_delivery_time', '--format=count' ), 'no term appeared' ).toBe( before );
		await client.dispose();
	} );

	test( 'the legal tools read what is stored, not the store\'s default', async ( { browser, baseURL } ) => {
		const { client, call } = await caller( browser, baseURL as string, [ READ, WRITE ] );
		const fallback = ( await call( 'create_delivery_time', { name: `E2E fallback ${ Date.now() }` } ) ).structuredContent[ 'delivery time' ];
		wp( 'option', 'update', 'woocommerce_gzd_default_delivery_time', String( fallback.id ) );

		try {
			const updated = await call( 'update_product_legal_details', { id: Number( product ), delivery_time: null, confirm: true } );
			expect( updated.isError, JSON.stringify( updated ) ).toBe( false );
			const stored = ( await call( 'get_product_legal_details', { id: Number( product ) } ) ).structuredContent.product;
			expect( stored.delivery_time?.id, 'a product with none has none' ).toBeUndefined();

			const listed = ( await call( 'get_products_legal_details', { include: [ Number( product ) ] } ) ).structuredContent.products[ 0 ];
			expect( listed.delivery_time?.id ).toBeUndefined();
		} finally {
			wp( 'option', 'delete', 'woocommerce_gzd_default_delivery_time' );
			await call( 'delete_delivery_time', { id: fallback.id, confirm: true } );
			await client.dispose();
		}
	} );

	test( 'an age check survives every tool that writes to a product, each of which Germanized would clear it through', async ( { browser, baseURL } ) => {
		const { client, call } = await caller( browser, baseURL as string, [ READ, WRITE, 'products:read', 'products:write' ] );
		const age = () => wp( 'post', 'meta', 'list', product, '--keys=_min_age', '--fields=meta_value', '--format=csv' ).replace( 'meta_value', '' ).trim();

		await call( 'update_product_legal_details', { id: Number( product ), min_age: '18', confirm: true } );
		expect( age() ).toBe( '18' );

		await call( 'update_product_legal_details', { id: Number( product ), gtin: '4006381333931', confirm: true } );
		expect( age(), 'by its own tool' ).toBe( '18' );

		await call( 'update_product', { id: Number( product ), short_description: 'Short' } );
		expect( age(), 'by update_product' ).toBe( '18' );

		await call( 'set_product_meta', { id: Number( product ), key: 'e2e_note', value: 'x', confirm: true } );
		expect( age(), 'by a custom field write' ).toBe( '18' );

		await call( 'update_product_legal_details', { id: Number( product ), min_age: '', confirm: true } );
		expect( age(), 'it can still be cleared on purpose' ).toBe( '' );

		const variable = wp( 'wc', 'product', 'create', '--name=Variable legal', '--type=variable', '--status=publish', '--user=e2e-admin', '--porcelain' ).trim();
		const variation = wp( 'wc', 'product_variation', 'create', variable, '--regular_price=3', '--user=e2e-admin', '--porcelain' ).trim();
		wp( 'post', 'meta', 'set', variation, '_min_age', '16' );
		await call( 'update_product_variation', { product_id: Number( variable ), id: Number( variation ), sku: `E2E-${ Date.now() }` } );
		expect( wp( 'post', 'meta', 'get', variation, '_min_age' ), 'by a variation update' ).toBe( '16' );

		wp( 'post', 'delete', variable, '--force' );
		await client.dispose();
	} );

	test( 'a Germanized grant cannot change a price, a status or anything else about a product', async ( { browser, baseURL } ) => {
		const { client, names, call } = await caller( browser, baseURL as string, [ READ, WRITE ] );

		expect( await names(), 'no product tool comes with it' ).not.toContain( 'update_product' );

		await call( 'update_product_legal_details', { id: Number( product ), mini_desc: 'x', regular_price: '0.01', status: 'draft', name: 'Hijacked', confirm: true } );
		expect( wp( 'post', 'meta', 'get', product, '_regular_price' ) ).toBe( '10' );
		expect( wp( 'post', 'get', product, '--field=post_status' ) ).toBe( 'publish' );
		expect( wp( 'post', 'get', product, '--field=post_title' ) ).toBe( 'Legal product' );

		const read = ( await call( 'get_product_legal_details', { id: Number( product ) } ) ).structuredContent.product;
		expect( read, 'and the read is legal details, not the catalog' ).not.toHaveProperty( 'regular_price' );
		await client.dispose();
	} );

	test( 'a Germanized grant cannot switch off a product\'s tax, and sees that it is off', async ( { browser, baseURL } ) => {
		const { client, call } = await caller( browser, baseURL as string, [ READ, WRITE ] );
		wp( 'post', 'meta', 'set', product, '_tax_status', 'taxable' );

		await call( 'update_product_legal_details', { id: Number( product ), differential_taxation: true, photovoltaic_system: true, mini_desc: 'x', confirm: true } );

		expect( wp( 'post', 'meta', 'get', product, '_tax_status' ) ).toBe( 'taxable' );
		const read = ( await call( 'get_product_legal_details', { id: Number( product ) } ) ).structuredContent.product;
		expect( read.differential_taxation ).toBe( false );
		expect( read.photovoltaic_system ).toBe( false );
		await client.dispose();
	} );

	test( 'a stale profile on /products cannot publish what a Germanized grant must not reach', async ( { browser, baseURL } ) => {
		const { client, published } = await caller( browser, baseURL as string, [ READ, WRITE ] );
		const fields = await published( 'update_product_legal_details' );

		expect( fields ).toContain( 'id' );
		expect( fields ).not.toContain( 'regular_price' );
		await client.dispose();
	} );

	test( 'script from an administrator\'s token does not reach what the shop displays', async ( { browser, baseURL } ) => {
		const { client, call } = await caller( browser, baseURL as string, [ READ, WRITE ] );

		await call( 'update_product_legal_details', { id: Number( product ), mini_desc: '<script>window.counterhandProbe=1</script>Keep dry', confirm: true } );

		expect( wp( 'post', 'meta', 'get', product, '_mini_desc' ) ).not.toContain( '<script' );
		await client.dispose();
	} );

	test( 'bank details stay out of every tool, with real Germanized adding them to the responses', async ( { browser, baseURL } ) => {
		const customer = wp( 'user', 'create', 'e2e-gzd-debit', 'e2e-gzd-debit@example.test', '--role=customer', '--porcelain' ).trim();
		wp( 'user', 'meta', 'set', customer, 'direct_debit_holder', 'Jane Holder' );
		wp( 'user', 'meta', 'set', customer, 'direct_debit_iban', 'AT00E2EPROBE' );
		const order = wp( 'wc', 'shop_order', 'create', '--user=e2e-admin', '--status=on-hold', '--porcelain' ).trim();
		wp( 'wc', 'shop_order', 'update', order, '--user=e2e-admin', `--meta_data=${ JSON.stringify( [ { key: '_direct_debit_holder', value: 'Jane Holder' }, { key: '_direct_debit_iban', value: 'AT00E2EPROBE' } ] ) }` );
		const { client, call } = await caller( browser, baseURL as string, [ READ, 'customers:read', 'orders:read' ] );

		try {
			const unfiltered = wp( 'wc', 'shop_order', 'get', order, '--user=e2e-admin', '--format=json' );
			expect( unfiltered, 'Germanized really does add them to an order' ).toContain( 'direct_debit' );

			const responses = [
				await call( 'get_customer', { id: Number( customer ) } ),
				await call( 'list_customers', {} ),
				await call( 'get_customer_meta', { id: Number( customer ) } ),
				await call( 'get_order', { id: Number( order ) } ),
				await call( 'list_orders', {} ),
				await call( 'get_order_meta', { id: Number( order ) } ),
			];
			expect( responses.every( ( response ) => ! response.isError ), JSON.stringify( responses ) ).toBe( true );
			const text = JSON.stringify( responses );
			expect( text ).not.toContain( 'AT00E2EPROBE' );
			expect( text ).not.toContain( 'Jane Holder' );
			expect( text ).not.toContain( 'direct_debit' );
		} finally {
			wp( 'user', 'delete', customer, '--yes' );
			wp( 'wc', 'shop_order', 'delete', order, '--force=true', '--user=e2e-admin' );
			await client.dispose();
		}
	} );

	test( 'deposit types change what customers pay, so edits need confirmation', async ( { browser, baseURL } ) => {
		const { client, call } = await caller( browser, baseURL as string, [ READ, WRITE ] );
		const id = ( await call( 'create_deposit_type', { name: `E2E bottle ${ Date.now() }`, deposit: 0.25, packaging_type: 'reusable' } ) ).structuredContent[ 'deposit type' ].id;

		expect( ( await call( 'update_deposit_type', { id, deposit: 9.99, confirm: false } ) ).isError ).toBe( true );
		expect( ( await call( 'update_deposit_type', { id, deposit: 0.5, confirm: true } ) ).structuredContent[ 'deposit type' ].deposit ).toBeTruthy();

		await call( 'delete_deposit_type', { id, confirm: true } );
		await client.dispose();
	} );

	test( 'editing a deposit amount keeps what it is packed in, which Germanized would reset', async ( { browser, baseURL } ) => {
		const { client, call } = await caller( browser, baseURL as string, [ READ, WRITE ] );
		const created = async ( fields: Record< string, unknown > ) => ( await call( 'create_deposit_type', { name: `E2E ${ Math.random() }`, deposit: 0.25, packaging_type: 'reusable', ...fields } ) ).structuredContent[ 'deposit type' ];
		const crate = await created( { is_packaging: true, packaging_number_of_contents: 6 } );
		const bottle = await created( { packaging: crate.slug } );

		const crateAfter = ( await call( 'update_deposit_type', { id: crate.id, deposit: 3, confirm: true } ) ).structuredContent[ 'deposit type' ];
		const bottleAfter = ( await call( 'update_deposit_type', { id: bottle.id, deposit: 0.5, confirm: true } ) ).structuredContent[ 'deposit type' ];

		expect( crateAfter.is_packaging ).toBe( true );
		expect( crateAfter.packaging_number_of_contents ).toBe( 6 );
		expect( bottleAfter.packaging ).toBe( crate.slug );

		await call( 'delete_deposit_type', { id: bottle.id, confirm: true } );
		await call( 'delete_deposit_type', { id: crate.id, confirm: true } );
		await client.dispose();
	} );

	test( 'every shipped name is one the running plugin declares', async ( { browser, baseURL } ) => {
		const { client, call, names, published } = await caller( browser, baseURL as string, [ READ, WRITE, 'products:read' ] );

		const update = await published( 'update_product_legal_details' );
		expect( update ).toEqual( expect.arrayContaining( [ 'id', 'delivery_time', 'country_specific_delivery_times', 'sale_price_label', 'sale_price_regular_label', 'manufacturer', 'unit', 'unit_price', 'mini_desc', 'defect_description', 'free_shipping', 'safety_attachment_ids', 'safety_instructions', 'min_age', 'warranty_attachment_id', 'guarantee_length', 'gtin', 'mpn', 'service', 'used_good', 'defective_copy', 'is_food', 'food', 'wireless_electronic_device', 'device_contains_power_supply', 'device_charging_supports_usb_pd', 'device_charging_watt_min', 'device_charging_watt_max' ] ) );
		expect( update ).not.toContain( 'regular_price' );
		expect( update ).not.toContain( 'differential_taxation' );

		expect( await published( 'create_deposit_type' ) ).toEqual( expect.arrayContaining( [ 'name', 'deposit', 'packaging_type', 'tax_status' ] ) );

		const described = await call( 'describe_woocommerce_fields', { tool: 'update_product_legal_details' } );
		expect( described.isError, 'a token cannot learn from this which plugins the store runs' ).toBe( true );

		for ( const tool of [ 'get_units', 'get_price_labels', 'get_deposit_types', 'get_manufacturers', 'get_nutrients', 'get_allergens' ] ) {
			expect( ( await call( tool, {} ) ).isError, tool ).toBe( false );
		}
		expect( ( await names() ).filter( ( name ) => name.startsWith( 'woocommerce_germanized_' ) ), 'no abilities to bridge yet' ).toEqual( [] );
		await client.dispose();
	} );

	test( 'Germanized going away hides the product tools even though /products remains', async ( { browser, baseURL } ) => {
		const { client, names } = await caller( browser, baseURL as string, [ READ ] );
		expect( await names() ).toContain( 'get_products_legal_details' );

		wp( 'plugin', 'deactivate', 'woocommerce-germanized' );
		try {
			const off = await names();
			expect( off ).not.toContain( 'get_products_legal_details' );
			expect( off ).not.toContain( 'get_delivery_times' );
		} finally {
			wp( 'plugin', 'activate', 'woocommerce-germanized' );
		}
		await client.dispose();
	} );
} );

/*
 * WooCommerce Multilingual is free, so a fresh store has the current release. WPML is
 * paid and is not, so everything below that needs both runs only on a store given a
 * licensed copy (COUNTERHAND_E2E_PAID_PLUGINS, see the README). These two hold what is
 * left to the daily run to the release: the hooks we rely on are still there, and the
 * tools stay away while it runs without WPML, where it ignores lang.
 */
test.describe( 'WooCommerce Multilingual without WPML', () => {
	test.beforeAll( () => {
		wp( 'plugin', 'activate', 'woocommerce-multilingual' );
	} );

	test.afterAll( () => {
		wp( 'plugin', 'deactivate', 'woocommerce-multilingual' );
	} );

	test( 'the current release still answers what these tools rely on', () => {
		const source = ( file: string ) => wp( 'eval', `echo file_get_contents( WP_PLUGIN_DIR . '/woocommerce-multilingual/classes/Rest/${ file }' );` );

		expect( source( 'Functions.php' ) ).toContain( "'woocommerce_rest_is_request_to_rest_api'" );
		expect( source( 'Wrapper/Products/Products.php' ) ).toContain( "'translation_of'" );
		expect( source( 'Wrapper/ProductTerms.php' ) ).toContain( "'translation_of'" );
		expect( source( 'Hooks.php' ) ).toContain( 'woocommerce_rest_prepare_{$type}_object' );
	} );

	test( 'no tool is offered, because it would ignore lang and answer success', async ( { browser, baseURL } ) => {
		const restore = patchSettings( { integration_wpml_read: true, integration_wpml_write: true } );
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		await page.goto( '/wp-admin/' );
		const { tokens, discovery } = await connect( page, baseURL as string, [ 'products:read' ] );
		await page.close();
		const names = await listToolNames( await anonymousContext( baseURL as string ), discovery.resource, tokens.access_token );
		restore();

		expect( names ).toContain( 'list_products' );
		expect( names ).not.toContain( 'get_product_translations' );
	} );
} );

test.describe( 'WPML with the real plugins', () => {
	const READ = 'integration:wpml:read';
	const WRITE = 'integration:wpml:write';

	let restore: () => void;
	let original: number;
	let translated: number;
	let untranslated: number;
	let variable: number;

	const installed = hasPaidPlugin( 'sitepress-multilingual-cms' );

	// In a file, because devbox hands arguments to a shell, which would expand $wpdb.
	const evaluate = ( code: string ) => {
		const script = join( stateDirectory, 'e2e-wpml-eval.php' );
		writeFileSync( script, `<?php ${ code }` );

		return wp( 'eval-file', script );
	};
	const language = ( id: number ) => evaluate( `global $sitepress; echo $sitepress->get_language_for_element( ${ id }, 'post_product' );` );

	test.beforeAll( () => {
		if ( ! installed ) {
			return;
		}

		wp( 'plugin', 'activate', 'sitepress-multilingual-cms', 'woocommerce-multilingual', 'wpml-string-translation' );
		evaluate( `
			global $sitepress, $wpdb, $woocommerce_wpml;
			$wpdb->query( "UPDATE {$wpdb->prefix}icl_languages SET active = 0" );
			$wpdb->query( "UPDATE {$wpdb->prefix}icl_languages SET active = 1 WHERE code IN ('de','en')" );
			$sitepress->set_setting( 'default_language', 'en' );
			$sitepress->set_setting( 'active_languages', [ 'de', 'en' ] );
			$sitepress->set_setting( 'setup_complete', 1 );
			$sync = (array) $sitepress->get_setting( 'custom_posts_sync_option' );
			$sync['product'] = 1; $sync['product_variation'] = 1;
			$sitepress->set_setting( 'custom_posts_sync_option', $sync );
			$tax = (array) $sitepress->get_setting( 'taxonomies_sync_option' );
			$tax['product_cat'] = 1; $tax['product_tag'] = 1;
			$sitepress->set_setting( 'taxonomies_sync_option', $tax );
			$sitepress->save_settings();
			$settings = $woocommerce_wpml->get_settings();
			$settings['set_up_wizard_run'] = 1;
			$settings['set_up_wizard_splash'] = 1;
			$settings['enable_multi_currency'] = 2;
			$settings['currency_options']['EUR'] = [ 'rate' => 0.9, 'position' => 'left', 'thousand_sep' => ',', 'decimal_sep' => '.', 'num_decimals' => 2, 'rounding' => 'disabled', 'rounding_increment' => 1, 'auto_subtract' => 0, 'languages' => [ 'de' => 1, 'en' => 1 ] ];
			$woocommerce_wpml->update_settings( $settings );
		` );
		restore = patchSettings( { integration_wpml_read: true, integration_wpml_write: true, products_read: true, products_write: true } );

		const [ first, second, third, fourth ] = JSON.parse( evaluate( `
			global $sitepress;
			$make = static function ( string $name, string $lang, $trid = null, $source = null, string $class = 'WC_Product_Simple' ) use ( $sitepress ) {
				$product = new $class();
				$product->set_name( $name );
				$product->set_regular_price( '10' );
				$product->set_status( 'publish' );
				$id = $product->save();
				$sitepress->set_element_language_details( $id, 'post_product', $trid, $lang, $source );
				return $id;
			};
			$original = $make( 'E2E WPML EN', 'en' );
			$translation = $make( 'E2E WPML DE', 'de', $sitepress->get_element_trid( $original, 'post_product' ), 'en' );
			$solo = $make( 'E2E WPML only EN', 'en' );
			$variable = $make( 'E2E WPML variable', 'en', null, null, 'WC_Product_Variable' );
			echo wp_json_encode( [ $original, $translation, $solo, $variable ] );
		` ) ) as number[];
		original = first;
		translated = second;
		untranslated = third;
		variable = fourth;
	} );

	test.afterAll( () => {
		if ( ! installed ) {
			return;
		}

		evaluate( `
			global $wpdb;
			foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_title LIKE 'E2E WPML%'" ) as $id ) {
				wp_delete_post( (int) $id, true );
			}
			foreach ( get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'search' => 'E2E WPML', 'suppress_filter' => true ] ) as $term ) {
				wp_delete_term( $term->term_id, 'product_cat' );
			}
		` );
		restore();
		wp( 'plugin', 'deactivate', 'wpml-string-translation', 'woocommerce-multilingual', 'sitepress-multilingual-cms' );
	} );

	async function caller( browser: import('@playwright/test').Browser, baseURL: string, scopes: string[] ) {
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		const { tokens, discovery } = await connect( page, baseURL, scopes );
		await page.close();
		const client = await anonymousContext( baseURL );

		return {
			names: () => listToolNames( client, discovery.resource, tokens.access_token ),
			call: async ( name: string, args: Record< string, unknown > ) => ( await ( await mcpCall( client, discovery.resource, tokens.access_token, 'tools/call', { name, arguments: args } ) ).json() ).result,
		};
	}

	test.beforeEach( () => {
		test.skip( ! installed, 'WPML is paid: set COUNTERHAND_E2E_PAID_PLUGINS to run these' );
	} );

	test( 'a price changed through update_product reaches the translation', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, [ 'products:read', 'products:write' ] );

		await call( 'update_product', { id: original, regular_price: '77' } );

		expect( evaluate( `echo get_post_meta( ${ translated }, '_regular_price', true );` ) ).toBe( '77' );
	} );

	test( 'so does one made through the REST fallback', async ( { browser, baseURL } ) => {
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		const { tokens } = await connect( page, baseURL as string, [ 'products:read', 'products:write' ] );
		await page.close();
		const client = await anonymousContext( baseURL as string );

		const reply = await mcpCall( client, new URL( '/wp-json/counterhand/v1/mcp', baseURL ).href, tokens.access_token, 'tools/call', { name: 'update_product', arguments: { id: original, regular_price: '78' } } );

		expect( reply.status(), 'WooCommerce\'s authentication must not take this for its own' ).toBe( 200 );
		expect( evaluate( `echo get_post_meta( ${ translated }, '_regular_price', true );` ) ).toBe( '78' );
	} );

	test( 'and one made in the admin chat', async ( { browser, baseURL } ) => {
		const restoreChat = patchOption( 'counterhand_chat', { provider: 'e2e-scripted', model: 'e2e-model' } );
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		await page.goto( '/wp-admin/admin.php?page=counterhand-mcp' );
		await page.locator( '#counterhand-chat-input' ).fill( `set product ${ original } to 79` );
		await page.locator( '#counterhand-chat-send' ).click();
		await expect( page.locator( '#counterhand-chat-log' ) ).toContainText( 'E2E model read the tool result.' );
		await page.close();
		restoreChat();

		expect( evaluate( `echo get_post_meta( ${ translated }, '_regular_price', true );` ) ).toBe( '79' );
	} );

	test( 'a translation is listed by language, created as a draft and linked', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, [ READ, WRITE ] );

		const inEnglish = ( await call( 'get_product_translations', { search: 'E2E WPML' } ) ).structuredContent.products;
		expect( inEnglish.map( ( row: { id: number } ) => row.id ) ).toContain( untranslated );
		expect( inEnglish.map( ( row: { id: number } ) => row.id ), 'the default language only' ).not.toContain( translated );
		const missing = inEnglish.find( ( row: { id: number } ) => row.id === untranslated );
		expect( missing.lang ).toBe( 'en' );
		expect( Object.keys( missing.translations ) ).not.toContain( 'de' );

		const inGerman = ( await call( 'get_product_translations', { search: 'E2E WPML', lang: 'de' } ) ).structuredContent.products;
		expect( inGerman.map( ( row: { id: number } ) => row.id ) ).toEqual( [ translated ] );

		const created = ( await call( 'create_product_translation', { translation_of: untranslated, lang: 'de', name: 'E2E WPML only DE', description: 'Auf Deutsch<script>alert(1)</script>' } ) ).structuredContent.product;
		expect( created.status ).toBe( 'draft' );
		expect( created.lang ).toBe( 'de' );
		expect( created.translations.en ).toBe( String( untranslated ) );
		expect( created.description, 'a token stores no script' ).not.toContain( '<script' );
		expect( language( created.id ) ).toBe( 'de' );
		expect( evaluate( `echo get_post_meta( ${ created.id }, '_regular_price', true );` ), 'the price comes from the original' ).toBe( '10' );

		const again = await call( 'create_product_translation', { translation_of: untranslated, lang: 'de', name: 'E2E WPML second' } );
		expect( again.isError, 'a second would orphan the first' ).toBe( true );

		const updated = ( await call( 'update_product_translation', { id: created.id, name: 'E2E WPML renamed' } ) ).structuredContent.product;
		expect( updated.name ).toBe( 'E2E WPML renamed' );
		expect( language( created.id ) ).toBe( 'de' );
		expect( evaluate( `echo get_the_title( ${ untranslated } );` ), 'the original keeps its text' ).toBe( 'E2E WPML only EN' );

		const unconfirmed = await call( 'publish_product_translation', { id: created.id, confirm: false } );
		expect( unconfirmed.isError ).toBe( true );
		expect( evaluate( `echo get_post_status( ${ created.id } );` ) ).toBe( 'draft' );
		await call( 'publish_product_translation', { id: created.id, confirm: true } );
		expect( evaluate( `echo get_post_status( ${ created.id } );` ) ).toBe( 'publish' );
	} );

	test( 'a translation of a variable product is variable too', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, [ READ, WRITE ] );

		const created = ( await call( 'create_product_translation', { translation_of: variable, lang: 'de', name: 'E2E WPML variable DE' } ) ).structuredContent.product;

		expect( evaluate( `echo wc_get_product( ${ created.id } )->get_type();` ) ).toBe( 'variable' );
	} );

	test( 'an original cannot be changed or published, and a price cannot be sent', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, [ READ, WRITE ] );
		const before = evaluate( `echo get_the_title( ${ original } );` );

		expect( ( await call( 'update_product_translation', { id: original, name: 'E2E WPML hijacked' } ) ).isError ).toBe( true );
		expect( ( await call( 'publish_product_translation', { id: original, confirm: true } ) ).isError ).toBe( true );
		expect( evaluate( `echo get_the_title( ${ original } );` ) ).toBe( before );

		await call( 'update_product_translation', { id: translated, regular_price: '1', status: 'draft' } );
		expect( evaluate( `echo get_post_meta( ${ translated }, '_regular_price', true );` ), 'dropped, because the tool is closed' ).not.toBe( '1' );
		expect( evaluate( `echo get_post_status( ${ translated } );` ) ).toBe( 'publish' );
	} );

	test( 'a category translation is linked, and one with no language is refused before anything is saved', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, [ READ, WRITE ] );

		const source = Number( evaluate( `
			global $sitepress;
			$term = wp_insert_term( 'E2E WPML Category ' . wp_rand(), 'product_cat' );
			$sitepress->set_element_language_details( $term['term_taxonomy_id'], 'tax_product_cat', null, 'en' );
			echo $term['term_id'];
		` ) );

		const created = ( await call( 'create_product_category_translation', { translation_of: source, lang: 'de', name: `E2E WPML Kategorie ${ Date.now() }` } ) ).structuredContent[ 'product category' ];
		expect( created.lang ).toBe( 'de' );
		expect( Number( created.translations.en ) ).toBe( source );

		expect( ( await call( 'create_product_category_translation', { translation_of: source, lang: 'de', name: 'E2E WPML second' } ) ).isError ).toBe( true );

		const renamed = `E2E WPML Renamed ${ Date.now() }`;
		expect( ( await call( 'update_product_category_translation', { id: created.id, name: renamed } ) ).structuredContent[ 'product category' ].name ).toBe( renamed );
		expect( ( await call( 'update_product_category_translation', { id: source, name: 'E2E WPML hijacked' } ) ).isError ).toBe( true );

		const unrecorded = Number( evaluate( `
			global $wpdb;
			$term = wp_insert_term( 'E2E WPML Unrecorded ' . wp_rand(), 'product_cat' );
			$wpdb->delete( $wpdb->prefix . 'icl_translations', [ 'element_type' => 'tax_product_cat', 'element_id' => $term['term_taxonomy_id'] ] );
			echo $term['term_id'];
		` ) );
		const before = evaluate( `echo wp_count_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'suppress_filter' => true ] );` );
		expect( ( await call( 'create_product_category_translation', { translation_of: unrecorded, lang: 'de', name: 'E2E WPML orphan' } ) ).isError ).toBe( true );
		expect( evaluate( `echo wp_count_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'suppress_filter' => true ] );` ) ).toBe( before );
	} );

	test( 'a translated variable product gets its variations, and only their text can be changed', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, [ READ, WRITE ] );
		const ids = JSON.parse( evaluate( `
			$product = new WC_Product_Variable();
			$product->set_name( 'E2E WPML with variation' );
			$product->set_status( 'publish' );
			$id = $product->save();
			global $sitepress;
			$sitepress->set_element_language_details( $id, 'post_product', null, 'en' );
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $id );
			$variation->set_regular_price( '9' );
			$variation->set_description( 'In English' );
			$variation_id = $variation->save();
			$sitepress->set_element_language_details( $variation_id, 'post_product_variation', null, 'en' );
			echo wp_json_encode( [ $id, $variation_id ] );
		` ) ) as number[];

		const created = ( await call( 'create_product_translation', { translation_of: ids[ 0 ], lang: 'de', name: 'E2E WPML mit Variante' } ) ).structuredContent.product;
		const listed = ( await call( 'get_product_variation_translations', { product_id: created.id, lang: 'de' } ) ).structuredContent.variations;
		expect( listed ).toHaveLength( 1 );
		expect( listed[ 0 ].lang ).toBe( 'de' );

		const updated = ( await call( 'update_product_variation_translation', { product_id: created.id, id: listed[ 0 ].id, description: 'Auf Deutsch' } ) ).structuredContent[ 'product variation' ];
		expect( updated.description ).toContain( 'Auf Deutsch' );
		expect( evaluate( `echo get_post_meta( ${ ids[ 1 ] }, '_variation_description', true );` ), 'the original keeps its text' ).toBe( 'In English' );

		expect( ( await call( 'update_product_variation_translation', { product_id: ids[ 0 ], id: ids[ 1 ], description: 'hijacked' } ) ).isError ).toBe( true );
	} );

	test( 'an attribute term translation is linked, and a term in the default language is refused', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, [ READ, WRITE ] );
		const attribute = Number( evaluate( `
			global $wpdb;
			$id = (int) $wpdb->get_var( "SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = 'e2ewpml'" );
			if ( ! $id ) {
				$id = wc_create_attribute( [ 'name' => 'e2ewpml', 'slug' => 'e2ewpml' ] );
			}
			global $sitepress;
			$sync = (array) $sitepress->get_setting( 'taxonomies_sync_option' );
			$sync['pa_e2ewpml'] = 1;
			$sitepress->set_setting( 'taxonomies_sync_option', $sync, true );
			echo $id;
		` ) );
		const source = Number( evaluate( `
			global $sitepress;
			register_taxonomy( 'pa_e2ewpml', 'product' );
			$term = wp_insert_term( 'Red ' . wp_rand(), 'pa_e2ewpml' );
			$sitepress->set_element_language_details( $term['term_taxonomy_id'], 'tax_pa_e2ewpml', null, 'en' );
			echo $term['term_id'];
		` ) );

		const created = ( await call( 'create_attribute_term_translation', { attribute_id: attribute, translation_of: source, lang: 'de', name: `Rot ${ Date.now() }` } ) ).structuredContent[ 'attribute term' ];
		expect( created.lang ).toBe( 'de' );
		expect( Number( created.translations.en ) ).toBe( source );

		expect( ( await call( 'update_attribute_term_translation', { attribute_id: attribute, id: source, name: 'hijacked' } ) ).isError ).toBe( true );
		expect( ( await call( 'create_attribute_term_translation', { attribute_id: attribute, translation_of: source, lang: 'de', name: 'second' } ) ).isError, 'a second would orphan the first' ).toBe( true );
	} );

	test( 'a string is listed and translated, and payment settings stay out of reach', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, [ READ, WRITE ] );
		const stamp = Date.now();
		const [ title, bank, gateway ] = JSON.parse( evaluate( `
			global $wpdb;
			$title = icl_register_string( 'woocommerce', 'e2e_title_${ stamp }', 'E2E Title ${ stamp }' );
			$bank = icl_register_string( 'admin_texts_woocommerce_bacs_accounts', 'e2e_iban_${ stamp }', 'AT00 E2E ${ stamp }' );
			$gateway = icl_register_string( 'admin_texts_woocommerce_gateways', 'e2e_gateway_${ stamp }', 'E2E Gateway ${ stamp }' );
			echo wp_json_encode( [ $title, $bank, $gateway ] );
		` ) ) as number[];

		const missing = ( await call( 'get_translatable_strings', { language: 'de', search: `${ stamp }`, only_missing: true } ) ).structuredContent.strings;
		expect( missing.map( ( text: { id: number } ) => text.id ), 'only what is reachable' ).toEqual( [ title ] );

		const unconfirmed = await call( 'set_string_translation', { id: title, language: 'de', value: 'E2E Titel', confirm: false } );
		expect( unconfirmed.isError ).toBe( true );

		await call( 'set_string_translation', { id: title, language: 'de', value: 'E2E Titel<script>alert(1)</script>', confirm: true } );
		const translated = evaluate( `echo apply_filters( 'wpml_translate_single_string', 'E2E Title ${ stamp }', 'woocommerce', 'e2e_title_${ stamp }', 'de' );` );
		expect( translated ).toContain( 'E2E Titel' );
		expect( translated, 'a token stores no script' ).not.toContain( '<script' );

		expect( ( await call( 'get_translatable_strings', { language: 'de', search: `${ stamp }`, only_missing: true } ) ).structuredContent.strings ).toEqual( [] );

		for ( const id of [ bank, gateway ] ) {
			expect( ( await call( 'set_string_translation', { id, language: 'de', value: 'AT99 ATTACKER', confirm: true } ) ).isError, 'money would go elsewhere' ).toBe( true );
		}
		expect( ( await call( 'set_string_translation', { id: title, language: 'fr', value: 'x', confirm: true } ) ).isError, 'not an active language' ).toBe( true );
	} );

	test( 'a translated text field is set, and what is not text cannot be', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, [ READ, WRITE ] );
		evaluate( `update_post_meta( ${ original }, '_purchase_note', 'Thanks for buying' ); update_post_meta( ${ original }, '_downloadable_files', [ 'a' => [ 'file' => 'https://example.test/real.zip' ] ] ); update_post_meta( ${ translated }, '_purchase_note', '' );` );

		const fields = ( await call( 'get_product_translation_fields', { id: translated } ) ).structuredContent.fields;
		const note = fields.find( ( field: { key: string } ) => field.key === '_purchase_note' );
		expect( note ).toEqual( { key: '_purchase_note', original: 'Thanks for buying', translation: '' } );

		await call( 'set_product_translation_field', { id: translated, key: '_purchase_note', value: 'Danke<script>alert(1)</script>' } );
		const stored = evaluate( `echo get_post_meta( ${ translated }, '_purchase_note', true );` );
		expect( stored ).toContain( 'Danke' );
		expect( stored ).not.toContain( '<script' );
		expect( evaluate( `echo get_post_meta( ${ original }, '_purchase_note', true );` ) ).toBe( 'Thanks for buying' );

		for ( const key of [ '_downloadable_files', '_product_url', '_price', '_regular_price', '_yoast_wpseo_title', 'direct_debit_iban', 'wp_capabilities' ] ) {
			expect( ( await call( 'set_product_translation_field', { id: translated, key, value: 'x' } ) ).isError, key ).toBe( true );
		}
		expect( ( await call( 'set_product_translation_field', { id: original, key: '_purchase_note', value: 'hijacked' } ) ).isError, 'an original' ).toBe( true );
		expect( ( await call( 'get_product_translation_fields', { id: original } ) ).isError, 'an original' ).toBe( true );
	} );

	test( 'a product\'s own currency prices are set and kept, and what the route would ignore is refused', async ( { browser, baseURL } ) => {
		const { call, names } = await caller( browser, baseURL as string, [ 'products:read', 'products:write' ] );
		expect( await names() ).toContain( 'set_product_currency_prices' );

		const before = ( await call( 'get_product_currency_prices', { id: untranslated } ) ).structuredContent;
		expect( before.own_prices ).toBe( false );

		const set = ( await call( 'set_product_currency_prices', { id: untranslated, prices: { EUR: { regular_price: '33', sale_price: '30' } } } ) ).structuredContent;
		expect( set.own_prices ).toBe( true );
		expect( set.prices.EUR ).toEqual( { regular_price: '33', sale_price: '30' } );
		expect( evaluate( `echo get_post_meta( ${ untranslated }, '_price_EUR', true );` ) ).toBe( '30' );

		await call( 'update_product', { id: untranslated, name: 'E2E WPML only EN' } );
		expect( ( await call( 'get_product_currency_prices', { id: untranslated } ) ).structuredContent.prices.EUR.regular_price, 'kept by a write that omits it' ).toBe( '33' );

		expect( ( await call( 'set_product_currency_prices', { id: untranslated, prices: { XXX: { regular_price: '1' } } } ) ).isError, 'not set up' ).toBe( true );
		expect( ( await call( 'set_product_currency_prices', { id: variable, prices: { EUR: { regular_price: '1' } } } ) ).isError, 'variable' ).toBe( true );
		expect( ( await call( 'set_product_currency_prices', { id: untranslated, prices: { EUR: { regular_price: 'free' } } } ) ).isError, 'not a number' ).toBe( true );
	} );

	test( 'a translation of an unpublished original cannot go on sale', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, [ READ, WRITE ] );
		const draft = Number( evaluate( `
			global $sitepress;
			$product = new WC_Product_Simple();
			$product->set_name( 'E2E WPML unreleased' );
			$product->set_status( 'draft' );
			$id = $product->save();
			$sitepress->set_element_language_details( $id, 'post_product', null, 'en' );
			echo $id;
		` ) );
		const created = ( await call( 'create_product_translation', { translation_of: draft, lang: 'de', name: 'E2E WPML unveröffentlicht' } ) ).structuredContent.product;

		expect( ( await call( 'publish_product_translation', { id: created.id, confirm: true } ) ).isError ).toBe( true );
		expect( evaluate( `echo get_post_status( ${ created.id } );` ) ).toBe( 'draft' );
	} );

	test( 'all languages are not offered, because that would leak into the rest of a chat turn', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, [ READ, WRITE ] );

		expect( ( await call( 'get_product_translations', { lang: 'all' } ) ).isError ).toBe( true );
		expect( ( await call( 'get_product_translations', { lang: 'xx' } ) ).isError ).toBe( true );
	} );

	test( 'a string translation that could break a page or lose an order number is refused', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, [ READ, WRITE ] );
		const stamp = Date.now();
		const id = Number( evaluate( `echo icl_register_string( 'woocommerce', 'e2e_rules_${ stamp }', 'Order {order_number} for %s' );` ) );

		for ( const value of [ '', '   ', 'Bestellung', 'Bestellung {order_number}', 'Bestellung {order_number} für %s %s', '50% Rabatt {order_number} %s' ] ) {
			expect( ( await call( 'set_string_translation', { id, language: 'de', value, confirm: true } ) ).isError, JSON.stringify( value ) ).toBe( true );
		}

		expect( ( await call( 'set_string_translation', { id, language: 'de', value: 'Bestellung {order_number} für %s', confirm: true } ) ).isError ).toBeFalsy();
	} );

	test( 'an undone price reaches the translation too', async ( { browser, baseURL } ) => {
		const undo = patchSettings( { changes_read: true, changes_write: true } );
		const { call } = await caller( browser, baseURL as string, [ 'products:read', 'products:write', 'changes:write' ] );
		const before = evaluate( `echo get_post_meta( ${ translated }, '_regular_price', true );` );

		const changed = ( await call( 'update_product', { id: original, regular_price: '123' } ) ).structuredContent;
		expect( evaluate( `echo get_post_meta( ${ translated }, '_regular_price', true );` ) ).toBe( '123' );
		await call( 'undo_change', { change_id: changed.change_id, confirm: true } );
		undo();

		expect( evaluate( `echo get_post_meta( ${ translated }, '_regular_price', true );` ) ).toBe( before );
	} );

	test( 'a currency with no price of its own still follows the exchange rate', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, [ 'products:read', 'products:write' ] );

		await call( 'set_product_currency_prices', { id: untranslated, prices: { EUR: { regular_price: '33' } } } );
		expect( ( await call( 'set_product_currency_prices', { id: untranslated, prices: { EUR: { regular_price: '-1' } } } ) ).isError ).toBe( true );
	} );

	test( 'a WPML grant reaches nothing else', async ( { browser, baseURL } ) => {
		const { names } = await caller( browser, baseURL as string, [ READ, WRITE ] );
		const offered = await names();

		expect( offered ).toContain( 'create_product_translation' );
		expect( offered ).not.toContain( 'update_product' );
		expect( offered ).not.toContain( 'list_products' );
		expect( offered, 'prices sit under products' ).not.toContain( 'set_product_currency_prices' );
	} );
} );

/* Paid, so these run only on a store given a licensed copy. See the README. */
test.describe( 'Paid plugins', () => {
	async function caller( browser: import('@playwright/test').Browser, baseURL: string, scopes: string[] ) {
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		const { tokens, discovery } = await connect( page, baseURL, scopes );
		await page.close();
		const client = await anonymousContext( baseURL );

		return {
			names: () => listToolNames( client, discovery.resource, tokens.access_token ),
			call: async ( name: string, args: Record< string, unknown > ) => {
				const reply = await ( await mcpCall( client, discovery.resource, tokens.access_token, 'tools/call', { name, arguments: args } ) ).json();
				expect( reply.result, JSON.stringify( reply ) ).toBeDefined();

				return reply.result;
			},
		};
	}

	const evaluate = ( code: string ) => {
		const script = join( stateDirectory, 'e2e-paid-eval.php' );
		writeFileSync( script, `<?php ${ code }` );

		return wp( 'eval-file', script );
	};

	// Left behind, a newer product than the fixtures would be what chat.spec lists first.
	test.afterAll( () => {
		evaluate( `
			foreach ( get_posts( [ 'post_type' => 'product', 'post_status' => 'any', 'title' => 'E2E paid', 'numberposts' => -1 ] ) as $product ) {
				wp_delete_post( $product->ID, true );
			}
			foreach ( wc_get_orders( [ 'billing_email' => 'buyer@example.test', 'limit' => -1 ] ) as $order ) {
				$order->delete( true );
			}
			delete_option( 'e2e_paid_product' );
		` );
	} );

	const newOrder = () => Number( evaluate( `
		$product = new WC_Product_Simple();
		$product->set_name( 'E2E paid' );
		$product->set_regular_price( '20' );
		$product->update_meta_data( '_wc_cog_cost', '7.5' );
		$id = $product->save();
		$order = wc_create_order( [ 'status' => 'processing' ] );
		$order->add_product( wc_get_product( $id ), 2 );
		$order->set_billing_email( 'buyer@example.test' );
		$order->calculate_totals();
		$order->save();
		update_option( 'e2e_paid_product', $id );
		echo $order->get_id();
	` ) );

	test.describe( 'Shipment Tracking', () => {
		const READ = 'integration:woocommerce-shipment-tracking:read';
		const WRITE = 'integration:woocommerce-shipment-tracking:write';
		let restore: () => void;
		let order: number;

		test.beforeAll( () => {
			if ( ! hasPaidPlugin( 'woocommerce-shipment-tracking' ) ) {
				return;
			}
			wp( 'plugin', 'activate', 'woocommerce-shipment-tracking' );
			restore = patchSettings( { 'integration_woocommerce-shipment-tracking_read': true, 'integration_woocommerce-shipment-tracking_write': true } );
			order = newOrder();
		} );

		test.afterAll( () => {
			if ( hasPaidPlugin( 'woocommerce-shipment-tracking' ) ) {
				restore();
				wp( 'plugin', 'deactivate', 'woocommerce-shipment-tracking' );
			}
		} );

		test( 'a tracking number is added and removed, and one that would show nothing is refused', async ( { browser, baseURL } ) => {
			test.skip( ! hasPaidPlugin( 'woocommerce-shipment-tracking' ), 'paid' );
			const { call, names } = await caller( browser, baseURL as string, [ READ, WRITE ] );

			expect( await names(), 'the plugin\'s abilities are not a second way in' ).not.toContain( 'woocommerce-shipment-tracking_list-tracking-items' );

			const unconfirmed = await call( 'add_order_tracking', { order_id: order, tracking_provider: 'dhl', tracking_number: 'E2E1', confirm: false } );
			expect( unconfirmed.isError ).toBe( true );

			const added = await call( 'add_order_tracking', { order_id: order, tracking_provider: 'dhl', tracking_number: 'E2E1', confirm: true } );
			expect( added.isError, JSON.stringify( added ) ).toBeFalsy();
			const stored = ( await call( 'get_order_trackings', { order_id: order } ) ).structuredContent[ 'tracking numbers' ];
			expect( stored.map( ( row: { tracking_number: string } ) => row.tracking_number ) ).toEqual( [ 'E2E1' ] );
			expect( stored[ 0 ].tracking_link ).toContain( 'E2E1' );

			expect( ( await call( 'add_order_tracking', { order_id: order, tracking_provider: 'dhl', tracking_number: 'E2E1', confirm: true } ) ).isError, 'customers would see it twice' ).toBe( true );

			for ( const args of [ { tracking_provider: 'no-such' }, { tracking_provider: 'dhl', date_shipped: 'tomorrow' }, { custom_tracking_provider: 'X', custom_tracking_link: 'http://evil.test' }, { custom_tracking_provider: 'X', custom_tracking_link: 'javascript:alert(1)' } ] ) {
				expect( ( await call( 'add_order_tracking', { order_id: order, tracking_number: 'E2E2', confirm: true, ...args } ) ).isError, JSON.stringify( args ) ).toBe( true );
			}
			expect( ( await call( 'get_order_trackings', { order_id: order } ) ).structuredContent[ 'tracking numbers' ] ).toHaveLength( 1 );

			await call( 'delete_order_tracking', { order_id: order, id: stored[ 0 ].tracking_id, confirm: true } );
			expect( ( await call( 'get_order_trackings', { order_id: order } ) ).structuredContent[ 'tracking numbers' ] ).toEqual( [] );
		} );
	} );

	test.describe( 'Cost of Goods', () => {
		const READ = 'integration:woocommerce-cost-of-goods:read';
		const WRITE = 'integration:woocommerce-cost-of-goods:write';
		let restore: () => void;
		let order: number;
		let product: number;

		test.beforeAll( () => {
			if ( ! hasPaidPlugin( 'woocommerce-cost-of-goods' ) ) {
				return;
			}
			wp( 'plugin', 'activate', 'woocommerce-cost-of-goods' );
			restore = patchSettings( { 'integration_woocommerce-cost-of-goods_read': true, 'integration_woocommerce-cost-of-goods_write': true } );
			order = newOrder();
			product = Number( evaluate( `echo get_option( 'e2e_paid_product' );` ) );
		} );

		test.afterAll( () => {
			if ( hasPaidPlugin( 'woocommerce-cost-of-goods' ) ) {
				restore();
				wp( 'plugin', 'deactivate', 'woocommerce-cost-of-goods' );
			}
		} );

		test( 'a cost is read and set without touching the price, and an order shows its profit', async ( { browser, baseURL } ) => {
			test.skip( ! hasPaidPlugin( 'woocommerce-cost-of-goods' ), 'paid' );
			const { call } = await caller( browser, baseURL as string, [ READ, WRITE ] );

			const read = ( await call( 'get_product_cost', { id: product } ) ).structuredContent;
			expect( read.cost ).toBe( '7.5' );
			expect( read.price ).toBe( '20' );

			const set = ( await call( 'set_product_cost', { id: product, cost: '8.25' } ) ).structuredContent;
			expect( set.cost ).toBe( '8.25' );
			expect( set.price, 'the price stays' ).toBe( '20' );

			const earned = ( await call( 'get_order_cost', { id: order } ) ).structuredContent;
			expect( earned.lines ).toHaveLength( 1 );
			expect( earned.lines[ 0 ].cost, 'as recorded when it was placed' ).toBe( 15 );
			expect( earned.profit ).toBe( 25 );
			expect( JSON.stringify( earned ), 'no customer details' ).not.toContain( 'buyer@example.test' );

			evaluate( `
				$order = wc_get_order( ${ order } );
				foreach ( $order->get_items() as $item ) {
					wc_create_refund( [ 'order_id' => ${ order }, 'amount' => '40', 'line_items' => [ $item->get_id() => [ 'qty' => 2, 'refund_total' => 40 ] ] ] );
				}
			` );
			expect( ( await call( 'get_order_cost', { id: order } ) ).structuredContent.profit, 'a refunded order earned nothing' ).toBe( 0 );

			const noCost = Number( evaluate( `
				$product = new WC_Product_Simple();
				$product->set_name( 'E2E paid' );
				$product->set_regular_price( '20' );
				$order = wc_create_order( [ 'status' => 'processing' ] );
				$order->add_product( wc_get_product( $product->save() ), 1 );
				$order->set_billing_email( 'buyer@example.test' );
				$order->calculate_totals();
				$order->save();
				echo $order->get_id();
			` ) );
			const unknown = ( await call( 'get_order_cost', { id: noCost } ) ).structuredContent;
			expect( unknown.profit, 'an unset cost is not free' ).toBeNull();
			expect( unknown.profit_not_stated ).toContain( 'no cost' );

			const yen = Number( evaluate( `
				$order = wc_get_order( ${ order } );
				$copy = wc_create_order( [ 'status' => 'processing' ] );
				$copy->set_currency( 'JPY' );
				$copy->add_product( wc_get_product( ${ product } ), 1 );
				$copy->set_billing_email( 'buyer@example.test' );
				$copy->calculate_totals();
				$copy->save();
				echo $copy->get_id();
			` ) );
			expect( ( await call( 'get_order_cost', { id: yen } ) ).structuredContent.profit, 'yen less dollars is no number' ).toBeNull();

			expect( ( await call( 'set_product_cost', { id: product, cost: '-1' } ) ).isError ).toBe( true );
			expect( ( await call( 'get_product_cost', { id: 99999999 } ) ).isError ).toBe( true );
			expect( ( await call( 'get_product_cost', { id: order } ) ).isError, 'an order is no product' ).toBe( true );
		} );

		test( 'a read grant offers no write and reaches nothing else', async ( { browser, baseURL } ) => {
			test.skip( ! hasPaidPlugin( 'woocommerce-cost-of-goods' ), 'paid' );
			const { names } = await caller( browser, baseURL as string, [ READ ] );
			const offered = await names();

			expect( offered ).toContain( 'get_product_cost' );
			expect( offered ).not.toContain( 'set_product_cost' );
			expect( offered ).not.toContain( 'get_order' );
		} );
	} );

	test.describe( 'URL Coupons', () => {
		const READ = 'integration:woocommerce-url-coupons:read';
		const WRITE = 'integration:woocommerce-url-coupons:write';
		let restore: () => void;
		let coupon: number;
		let page: number;

		test.beforeAll( () => {
			if ( ! hasPaidPlugin( 'woocommerce-url-coupons' ) ) {
				return;
			}
			wp( 'plugin', 'activate', 'woocommerce-url-coupons' );
			restore = patchSettings( { 'integration_woocommerce-url-coupons_read': true, 'integration_woocommerce-url-coupons_write': true } );
			[ coupon, page ] = JSON.parse( evaluate( `
				$coupon = new WC_Coupon();
				$coupon->set_code( 'e2e' . wp_rand() );
				$coupon->set_amount( 5 );
				echo wp_json_encode( [ $coupon->save(), wc_get_page_id( 'shop' ) ] );
			` ) ) as number[];
		} );

		test.afterAll( () => {
			if ( hasPaidPlugin( 'woocommerce-url-coupons' ) ) {
				restore();
				wp( 'plugin', 'deactivate', 'woocommerce-url-coupons' );
			}
		} );

		test( 'a link applies a coupon and sends visitors on, and cannot take over a real page', async ( { browser, baseURL } ) => {
			test.skip( ! hasPaidPlugin( 'woocommerce-url-coupons' ), 'paid' );
			const { call } = await caller( browser, baseURL as string, [ READ, WRITE ] );
			const slug = `e2e-deal-${ Date.now() }`;

			expect( ( await call( 'set_coupon_url', { id: coupon, url: slug, redirect_page: page, confirm: false } ) ).isError ).toBe( true );

			const set = await call( 'set_coupon_url', { id: coupon, url: slug, redirect_page: page, confirm: true } );
			expect( set.isError, JSON.stringify( set ) ).toBeFalsy();
			const listed = ( await call( 'get_coupon_urls', {} ) ).structuredContent.links;
			expect( listed.find( ( link: { coupon_id: number } ) => link.coupon_id === coupon ).url ).toBe( slug );

			const visitor = await anonymousContext( baseURL as string );
			const visit = await visitor.get( `/${ slug }`, { maxRedirects: 0 } );
			expect( visit.status() ).toBe( 302 );
			expect( visit.headers().location ).toContain( '/shop' );

			for ( const url of [ 'shop', 'sho', 'checkout', 'checkou', 'product', 'prod', 'product-category', 'wp-admin', 'wp-json', 'mcp', '/leading', 'UPPER', '../up' ] ) {
				expect( ( await call( 'set_coupon_url', { id: coupon, url, redirect_page: page, confirm: true } ) ).isError, url ).toBe( true );
			}
			expect( ( await call( 'set_coupon_url', { id: coupon, url: `${ slug }-2`, redirect_page: 99999999, confirm: true } ) ).isError, 'no such page' ).toBe( true );

			await call( 'set_coupon_url', { id: coupon, url: '', confirm: true } );
			expect( ( await call( 'get_coupon_urls', {} ) ).structuredContent.links.find( ( link: { coupon_id: number } ) => link.coupon_id === coupon ) ).toBeUndefined();
		} );
	} );

	test.describe( 'Germanized Pro invoices', () => {
		const READ = 'integration:germanized-pro:read';
		let restore: () => void;

		test.beforeAll( () => {
			if ( ! hasPaidPlugin( 'woocommerce-germanized-pro' ) ) {
				return;
			}
			wp( 'plugin', 'activate', 'woocommerce-germanized', 'woocommerce-germanized-pro' );
			wp( 'option', 'update', 'woocommerce_gzdp_invoice_enable', 'yes' );
			restore = patchSettings( { 'integration_germanized-pro_read': true } );
		} );

		test.afterAll( () => {
			if ( hasPaidPlugin( 'woocommerce-germanized-pro' ) ) {
				restore();
				wp( 'option', 'delete', 'woocommerce_gzdp_invoice_enable' );
				wp( 'plugin', 'deactivate', 'woocommerce-germanized-pro', 'woocommerce-germanized' );
			}
		} );

		test( 'an invoice search cannot confirm an address', async ( { browser, baseURL } ) => {
			test.skip( ! hasPaidPlugin( 'woocommerce-germanized-pro' ), 'paid' );
			const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
			const { tokens, discovery } = await connect( page, baseURL as string, [ READ ] );
			await page.close();
			const tools = ( await ( await mcpCall( await anonymousContext( baseURL as string ), discovery.resource, tokens.access_token, 'tools/list' ) ).json() ).result.tools;

			expect( Object.keys( tools.find( ( tool: { name: string } ) => tool.name === 'get_invoices' ).inputSchema.properties ) ).not.toContain( 'search' );
		} );

		test( 'invoices are listed with no address or file, and nothing can write', async ( { browser, baseURL } ) => {
			test.skip( ! hasPaidPlugin( 'woocommerce-germanized-pro' ), 'paid' );
			const { call, names } = await caller( browser, baseURL as string, [ READ ] );

			expect( await names() ).toEqual( expect.arrayContaining( [ 'get_invoices', 'get_invoice' ] ) );

			const order = newOrder();
			evaluate( `
				$sync = \\Vendidero\\StoreaBill\\WooCommerce\\Helper::get_order( wc_get_order( ${ order } ) );
				$sync->sync_order();
				$sync->finalize( true );
			` );

			const listed = ( await call( 'get_invoices', {} ) ).structuredContent.invoices;
			expect( listed.length, 'the invoice just issued' ).toBeGreaterThan( 0 );
			const invoice = listed[ 0 ].id;

			const one = ( await call( 'get_invoice', { id: invoice } ) ).structuredContent.invoice;
			expect( one.total ).toBeDefined();
			for ( const field of [ 'address', 'path', 'filename', 'relative_path', 'primary_path', 'formatted_address' ] ) {
				expect( one[ field ], field ).toBeUndefined();
			}
		} );
	} );
} );
