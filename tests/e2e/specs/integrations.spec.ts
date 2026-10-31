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
