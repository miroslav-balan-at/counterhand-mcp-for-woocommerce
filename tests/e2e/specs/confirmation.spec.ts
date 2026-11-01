import { expect, test, type Browser } from '@playwright/test';
import { mcpCall, statelessCall } from '../support/mcp';
import { anonymousContext, authorizationUrl, connect, discover } from '../support/oauth';
import { adminStatePath, patchOption, patchSettings, wp } from '../support/store';

const FORMS = { elicitation: {} };

test.describe( 'A person decides what moves money or reaches a customer', () => {
	let restore: () => void;
	let product: string;
	let order: string;

	test.beforeAll( () => {
		restore = patchSettings( { products_read: true, products_write: true, orders_read: true, orders_write: true, coupons_read: true, coupons_write: true, refunds_read: true, refunds_write: true } );
	} );

	test.afterAll( () => restore() );

	test.beforeEach( () => {
		product = wp( 'wc', 'product', 'create', '--name=E2E gated', '--regular_price=10', '--status=publish', '--user=e2e-admin', '--porcelain' ).trim();
		order = wp( 'wc', 'shop_order', 'create', '--status=processing', '--user=e2e-admin', '--porcelain' ).trim();
	} );

	test.afterEach( () => {
		wp( 'post', 'delete', product, '--force' );
		wp( 'wc', 'shop_order', 'delete', order, '--force=true', '--user=e2e-admin' );
	} );

	const price = () => wp( 'post', 'meta', 'get', product, '_regular_price' );

	async function caller( browser: Browser, baseURL: string ) {
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		const { tokens, discovery } = await connect( page, baseURL, [ 'products:read', 'products:write', 'orders:read', 'orders:write', 'coupons:read', 'coupons:write', 'refunds:read', 'refunds:write' ] );
		await page.close();
		const client = await anonymousContext( baseURL );

		return {
			legacy: async ( name: string, args: Record< string, unknown > ) => ( await ( await mcpCall( client, discovery.resource, tokens.access_token, 'tools/call', { name, arguments: args } ) ).json() ),
			forms: async ( name: string, args: Record< string, unknown >, extra: Record< string, unknown > = {} ) => ( await ( await statelessCall( client, discovery.resource, tokens.access_token, 'tools/call', { name, arguments: args, ...extra }, { capabilities: FORMS } ) ).json() ),
			noForms: async ( name: string, args: Record< string, unknown > ) => ( await ( await statelessCall( client, discovery.resource, tokens.access_token, 'tools/call', { name, arguments: args } ) ).json() ),
		};
	}

	test( 'each risky write refuses without agreement, and the refusal says what to do', async ( { browser, baseURL } ) => {
		const { legacy } = await caller( browser, baseURL as string );

		for ( const [ name, args ] of [
			[ 'update_product', { id: Number( product ), regular_price: '1' } ],
			[ 'update_product', { id: Number( product ), sale_price: '1' } ],
			[ 'update_order_status', { id: Number( order ), status: 'completed' } ],
			[ 'add_order_note', { order_id: Number( order ), note: 'Hello', customer_note: true } ],
			[ 'create_order_refund', { order_id: Number( order ), amount: '1', api_refund: false } ],
			[ 'create_coupon', { code: `e2e-gated-${ Date.now() }`, amount: '5' } ],
		] as const ) {
			const result = ( await legacy( name, args ) ).result;
			expect( result.isError, `${ name } ${ JSON.stringify( args ) }` ).toBe( true );
			expect( result.content[ 0 ].text, name ).toContain( 'Tell the user' );
		}

		expect( price() ).toBe( '10' );
		expect( wp( 'wc', 'shop_order', 'get', order, '--field=status', '--user=e2e-admin' ) ).toBe( 'processing' );
	} );

	test( 'what is not risky asks nothing, on the same tools', async ( { browser, baseURL } ) => {
		const { legacy } = await caller( browser, baseURL as string );

		expect( ( await legacy( 'update_product', { id: Number( product ), name: 'E2E renamed' } ) ).result.isError ).toBeFalsy();
		expect( ( await legacy( 'add_order_note', { order_id: Number( order ), note: 'For the team', customer_note: false } ) ).result.isError ).toBeFalsy();
		expect( ( await legacy( 'update_product', { id: Number( product ), regular_price: '11', confirm: true } ) ).result.isError ).toBeFalsy();
		expect( price() ).toBe( '11' );
	} );

	test( 'a bad argument is an error the model can read, not a protocol failure', async ( { browser, baseURL } ) => {
		const { legacy } = await caller( browser, baseURL as string );

		const wrong = await legacy( 'update_product', { id: 'not a number' } );
		expect( wrong.error ).toBeUndefined();
		expect( wrong.result.isError ).toBe( true );

		expect( ( await legacy( 'no_such_tool', {} ) ).error.code, 'an unknown tool still is one' ).toBe( -32602 );
	} );

	test( 'a client with forms gets a form, and the model cannot approve for the person', async ( { browser, baseURL } ) => {
		const { forms } = await caller( browser, baseURL as string );
		const args = { id: Number( product ), regular_price: '99' };

		const asked = ( await forms( 'update_product', { ...args, confirm: true } ) ).result;
		expect( asked.resultType, 'confirm from the model is ignored' ).toBe( 'input_required' );
		expect( asked.inputRequests.confirm_change.method ).toBe( 'elicitation/create' );
		expect( asked.inputRequests.confirm_change.params.mode ).toBe( 'form' );
		expect( asked.inputRequests.confirm_change.params.message ).toContain( '99' );
		expect( price(), 'nothing yet' ).toBe( '10' );

		const approve = { inputResponses: { confirm_change: { action: 'accept', content: { approve: true } } }, requestState: asked.requestState };

		const declined = ( await forms( 'update_product', args, { ...approve, inputResponses: { confirm_change: { action: 'decline' } } } ) ).result;
		expect( declined.isError ).toBe( true );
		expect( JSON.stringify( declined ) ).toContain( 'did not approve' );
		expect( price() ).toBe( '10' );

		const unticked = ( await forms( 'update_product', args, { ...approve, inputResponses: { confirm_change: { action: 'accept', content: { approve: false } } } } ) ).result;
		expect( unticked.isError ).toBe( true );
		expect( price() ).toBe( '10' );

		expect( ( await forms( 'update_product', { ...args, regular_price: '1' }, approve ) ).error.code, 'an approval of 99 is no approval of 1' ).toBe( -32602 );
		expect( ( await forms( 'update_product', { ...args, id: 999999 }, approve ) ).error.code ).toBe( -32602 );
		expect( ( await forms( 'update_product', args, { ...approve, requestState: `${ asked.requestState }x` } ) ).error.code, 'tampered' ).toBe( -32602 );

		const done = ( await forms( 'update_product', args, approve ) ).result;
		expect( done.isError ).toBeFalsy();
		expect( done.resultType ).toBe( 'complete' );
		expect( price() ).toBe( '99' );

		expect( ( await forms( 'update_product', args, approve ) ).error.code, 'an approval is spent once' ).toBe( -32602 );
	} );

	test( 'an approval is for one connection only', async ( { browser, baseURL } ) => {
		const one = await caller( browser, baseURL as string );
		const other = await caller( browser, baseURL as string );
		const args = { id: Number( product ), regular_price: '50' };

		const asked = ( await one.forms( 'update_product', args ) ).result;
		const stolen = await other.forms( 'update_product', args, { inputResponses: { confirm_change: { action: 'accept', content: { approve: true } } }, requestState: asked.requestState } );

		expect( stolen.error.code ).toBe( -32602 );
		expect( price() ).toBe( '10' );
	} );

	test( 'a client without forms keeps the confirm argument, and a preview asks nobody', async ( { browser, baseURL } ) => {
		const { noForms, forms } = await caller( browser, baseURL as string );

		expect( ( await noForms( 'update_product', { id: Number( product ), regular_price: '20' } ) ).result.isError ).toBe( true );
		expect( ( await noForms( 'update_product', { id: Number( product ), regular_price: '20', confirm: true } ) ).result.isError ).toBeFalsy();
		expect( price() ).toBe( '20' );

		const preview = ( await forms( 'update_product', { id: Number( product ), regular_price: '30', preview: true } ) ).result;
		expect( preview.resultType ).toBe( 'complete' );
		expect( preview.structuredContent.saved ).toBe( false );
		expect( price() ).toBe( '20' );
	} );

	test( 'the chat parks a price change for the button, and leaves a rename alone', async ( { browser, baseURL } ) => {
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		const restoreChat = patchOption( 'counterhand_chat', { provider: 'e2e-scripted', model: 'e2e-model' } );

		await page.goto( '/wp-admin/admin.php?page=counterhand-mcp' );
		await page.locator( '#counterhand-chat-input' ).fill( `set product ${ product } to 77` );
		await page.locator( '#counterhand-chat-send' ).click();
		await expect( page.locator( '.counterhand-confirm' ) ).toContainText( 'Approve this change?' );
		expect( price(), 'nothing until the person approves' ).toBe( '10' );

		await page.locator( '.counterhand-confirm' ).getByRole( 'button', { name: 'Approve' } ).click();
		await expect( page.locator( '#counterhand-chat-log' ) ).toContainText( 'E2E model read the tool result.' );
		expect( price() ).toBe( '77' );

		restoreChat();
		await page.close();
	} );
} );

test.describe( 'Authorization details', () => {
	test( 'the consent screen shows where access goes, and warns about this computer', async ( { browser, baseURL } ) => {
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		const discovery = await discover( await anonymousContext( baseURL as string ) );

		await page.goto( authorizationUrl( discovery, { scopes: [ 'products:read' ], verifierChallenge: 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', state: 'a b+c/=' } ) );

		await expect( page.locator( 'body' ) ).toContainText( 'Access goes to' );
		await expect( page.locator( 'body' ) ).toContainText( '127.0.0.1' );
		await expect( page.locator( 'body' ) ).toContainText( 'a program on your own computer' );
		expect( await page.locator( 'input[name="state"]' ).inputValue(), 'returned byte for byte' ).toBe( 'a b+c/=' );
		await page.close();
	} );

	test( 'a request with no credentials gets a clean challenge, and the fallback names itself', async ( { baseURL } ) => {
		const client = await anonymousContext( baseURL as string );

		const pretty = await client.post( '/mcp', { data: { jsonrpc: '2.0', id: 1, method: 'tools/list' } } );
		expect( pretty.status() ).toBe( 401 );
		expect( pretty.headers()[ 'www-authenticate' ] ).not.toContain( 'error=' );
		expect( pretty.headers()[ 'www-authenticate' ] ).toContain( 'scope=' );

		const wrong = await client.post( '/mcp', { data: { jsonrpc: '2.0', id: 1, method: 'tools/list' }, headers: { Authorization: 'Bearer nope' } } );
		expect( wrong.headers()[ 'www-authenticate' ] ).toContain( 'error="invalid_token"' );

		const fallback = await client.post( '/wp-json/counterhand/v1/mcp', { data: { jsonrpc: '2.0', id: 1, method: 'tools/list' } } );
		const metadataUrl = /resource_metadata="([^"]+)"/.exec( fallback.headers()[ 'www-authenticate' ] )?.[ 1 ] as string;
		const metadata = await ( await client.get( metadataUrl ) ).json();
		expect( new URL( metadata.resource ).pathname ).toBe( '/wp-json/counterhand/v1/mcp' );
	} );

	test( 'an origin that may not call is refused even for the wrong method, and a stray cursor is refused', async ( { browser, baseURL } ) => {
		const client = await anonymousContext( baseURL as string );
		const refused = await client.get( '/mcp', { headers: { Origin: 'https://evil.example' } } );
		expect( refused.status() ).toBe( 403 );

		const restore = patchSettings( { products_read: true } );
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		const { tokens, discovery } = await connect( page, baseURL as string, [ 'products:read' ] );
		await page.close();
		restore();

		expect( ( await ( await mcpCall( client, discovery.resource, tokens.access_token, 'tools/list', { cursor: 'x' } ) ).json() ).error.code ).toBe( -32602 );

		const unknownVersion = await client.post( discovery.resource, { headers: { Authorization: `Bearer ${ tokens.access_token }`, 'MCP-Protocol-Version': '2024-01-01' }, data: { jsonrpc: '2.0', id: 1, method: 'tools/list' } } );
		expect( unknownVersion.status() ).toBe( 400 );
	} );
} );
