import { expect, test, type Browser, type Page } from '@playwright/test';
import { mcpCall } from '../support/mcp';
import { anonymousContext, connect } from '../support/oauth';
import { adminStatePath, patchSettings, wp } from '../support/store';

const SALES = 'ui://counterhand/sales-report';
const ORDER = 'ui://counterhand/order';
const PREVIEW = 'ui://counterhand/change-preview';
const MIME_TYPE = 'text/html;profile=mcp-app';

async function connected( browser: Browser, baseURL: string, scopes: string[] ) {
	const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
	const { tokens, discovery } = await connect( page, baseURL, scopes );
	await page.close();
	const client = await anonymousContext( baseURL );

	return async ( method: string, params: Record< string, unknown > = {} ) =>
		( await mcpCall( client, discovery.resource, tokens.access_token, method, params ) ).json();
}

/**
 * A host as the MCP Apps spec describes one: a sandboxed iframe with the default CSP,
 * talking only through postMessage. No released client can run against this store from
 * here, because Claude and ChatGPT connect from their own clouds.
 */
async function hostView( page: Page, html: string, result: unknown ) {
	const view = 'https://view.example.test/';
	const blocked: string[] = [];
	page.on( 'request', ( request ) => {
		if ( ! [ view, 'https://host.example.test/' ].includes( request.url() ) && ! request.url().startsWith( 'data:' ) ) {
			blocked.push( request.url() );
		}
	} );
	await page.route( 'https://host.example.test/', ( route ) => route.fulfill( {
		contentType: 'text/html',
		body: `<!doctype html><iframe id="view" sandbox="allow-scripts" src="${ view }" style="width:600px;height:500px"></iframe><script>
			window.received = [];
			const frame = document.getElementById( 'view' );
			window.addEventListener( 'message', ( event ) => {
				if ( event.source !== frame.contentWindow ) { return; }
				const message = event.data;
				window.received.push( message );
				if ( 'ui/initialize' === message.method ) {
					frame.contentWindow.postMessage( { jsonrpc: '2.0', id: message.id, result: {
						protocolVersion: '2026-01-26', hostInfo: { name: 'test', version: '1' }, hostCapabilities: {},
						hostContext: { theme: 'dark', styles: { variables: { '--color-text-primary': 'rgb(1, 2, 3)' } } },
					} }, '*' );
				}
				if ( 'ui/notifications/initialized' === message.method ) {
					frame.contentWindow.postMessage( { jsonrpc: '2.0', method: 'ui/notifications/tool-result', params: ${ JSON.stringify( result ).replace( /</g, '\\u003c' ) } }, '*' );
				}
			} );
		</script>`,
	} ) );
	await page.route( view, ( route ) => route.fulfill( {
		contentType: 'text/html',
		headers: { 'Content-Security-Policy': "default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src data:; media-src data:; connect-src 'none'; object-src 'none'" },
		body: html,
	} ) );
	await page.goto( 'https://host.example.test/' );

	return { frame: page.frameLocator( '#view' ), blocked };
}

test.describe( 'MCP Apps views', () => {
	let restore: () => void;

	test.beforeAll( () => {
		restore = patchSettings( { enabled: true, reports_read: true, orders_read: true, products_read: true, products_write: true } );
	} );

	test.afterAll( () => restore() );

	test( 'a tool names its view, and only a connection reaching that tool can read it', async ( { browser, baseURL } ) => {
		const reports = await connected( browser, baseURL as string, [ 'reports:read' ] );
		const tools = ( await reports( 'tools/list' ) ).result.tools;
		const sales = tools.find( ( tool: { name: string } ) => 'get_sales_report' === tool.name );
		expect( sales._meta.ui.resourceUri ).toBe( SALES );
		expect( sales._meta[ 'ui/resourceUri' ] ).toBe( SALES );
		expect( tools.find( ( tool: { name: string } ) => 'get_top_sellers' === tool.name )._meta ).toBeUndefined();

		const read = ( await reports( 'resources/read', { uri: SALES } ) ).result.contents[ 0 ];
		expect( read.uri ).toBe( SALES );
		expect( read.mimeType ).toBe( MIME_TYPE );
		expect( read.text ).toContain( '<!doctype html>' );

		const listed = ( await reports( 'resources/list' ) ).result.resources.map( ( resource: { uri: string } ) => resource.uri );
		expect( listed.filter( ( uri: string ) => uri.startsWith( 'ui://' ) ), 'views stay out of the list' ).toEqual( [] );

		const outOfReach = await reports( 'resources/read', { uri: ORDER } );
		const unknown = await reports( 'resources/read', { uri: 'ui://counterhand/no-such-view' } );
		expect( outOfReach.error.code ).toBe( unknown.error.code );
		expect( outOfReach.error.message ).toBe( unknown.error.message );
		expect( ( await reports( 'resources/read', { uri: 'ui://counterhand/../sales-report' } ) ).error ).toBeDefined();
	} );

	test( 'a view loads nothing from elsewhere', async ( { browser, baseURL } ) => {
		const call = await connected( browser, baseURL as string, [ 'reports:read', 'orders:read', 'products:read', 'products:write' ] );
		for ( const uri of [ SALES, ORDER, PREVIEW ] ) {
			const html: string = ( await call( 'resources/read', { uri } ) ).result.contents[ 0 ].text;
			expect( html ).not.toMatch( /<(?:script|link|img)[^>]+(?:src|href)=/ );
			expect( html ).not.toMatch( /https?:\/\//i );
		}
	} );

	test( 'the sales view shows the tool\'s own numbers and follows the host', async ( { browser, baseURL, page } ) => {
		const call = await connected( browser, baseURL as string, [ 'reports:read' ] );
		const result = ( await call( 'tools/call', { name: 'get_sales_report', arguments: {} } ) ).result;
		const html = ( await call( 'resources/read', { uri: SALES } ) ).result.contents[ 0 ].text;

		const { frame, blocked } = await hostView( page, html, result );
		await expect( frame.getByRole( 'heading', { name: 'Sales' } ) ).toBeVisible();
		await expect( frame.getByText( 'Total sales' ) ).toBeVisible();
		await expect( frame.getByText( 'Items sold' ) ).toBeVisible();
		const report = result.structuredContent.sales[ 0 ];
		await expect( frame.locator( 'div', { hasText: 'Orders' } ).locator( 'dd' ) ).toHaveText( String( report.total_orders ) );
		await expect( frame.locator( 'div', { hasText: 'Items sold' } ).locator( 'dd' ) ).toHaveText( String( report.total_items ) );

		const received = await page.evaluate( () => ( window as unknown as { received: { method?: string; params?: Record< string, unknown > }[] } ).received );
		const initialize = received.find( ( message ) => 'ui/initialize' === message.method );
		expect( initialize?.params ).toMatchObject( { protocolVersion: '2026-01-26', appInfo: { name: expect.any( String ), version: expect.any( String ) }, appCapabilities: expect.any( Object ) } );
		expect( received.map( ( message ) => message.method ) ).toContain( 'ui/notifications/initialized' );
		await expect.poll( async () => ( await page.evaluate( () => ( window as unknown as { received: { method?: string }[] } ).received ) ).some( ( message ) => 'ui/notifications/size-changed' === message.method ) ).toBe( true );

		await expect( frame.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'dark' );
		await expect( frame.locator( 'body' ) ).toHaveCSS( 'color', 'rgb(1, 2, 3)' );
		expect( blocked ).toEqual( [] );
	} );

	test( 'text a customer wrote stays text', async ( { browser, baseURL, page } ) => {
		const hostile = '<img src=x onerror="window.parent.postMessage({jsonrpc:\'2.0\',method:\'stolen\'},\'*\')">';
		const id = wp( 'eval', `echo wc_create_order( [ 'customer_note' => base64_decode( '${ Buffer.from( hostile ).toString( 'base64' ) }' ) ] )->get_id();` ).trim();
		try {
			const call = await connected( browser, baseURL as string, [ 'orders:read' ] );
			const result = ( await call( 'tools/call', { name: 'get_order', arguments: { id: Number( id ) } } ) ).result;
			expect( result.structuredContent.order.customer_note ).toContain( 'onerror' );
			const html = ( await call( 'resources/read', { uri: ORDER } ) ).result.contents[ 0 ].text;

			const { frame, blocked } = await hostView( page, html, result );
			await expect( frame.getByRole( 'heading', { name: new RegExp( `Order #${ id }` ) } ) ).toBeVisible();
			await expect( frame.getByText( hostile, { exact: false } ) ).toBeVisible();
			await expect( frame.locator( 'img' ) ).toHaveCount( 0 );
			await expect( frame.locator( 'div', { hasText: 'Status' } ).locator( 'dd' ) ).toHaveText( result.structuredContent.order.status );
			await expect( frame.locator( 'div', { hasText: 'Total' } ).locator( 'dd' ) ).toContainText( '0.00' );
			expect( await page.evaluate( () => ( window as unknown as { received: { method?: string }[] } ).received.some( ( message ) => 'stolen' === message.method ) ) ).toBe( false );
			expect( blocked ).toEqual( [] );
		} finally {
			wp( 'eval', `wp_delete_post( ${ id }, true );` );
		}
	} );

	test( 'a preview shows before and after and saves nothing; a failure shows its message', async ( { browser, baseURL, page } ) => {
		const id = wp( 'wc', 'product', 'create', '--name=Apps preview', '--regular_price=10', '--user=e2e-admin', '--porcelain' ).trim();
		try {
			const call = await connected( browser, baseURL as string, [ 'products:read', 'products:write' ] );
			const result = ( await call( 'tools/call', { name: 'update_product', arguments: { id: Number( id ), regular_price: '12', preview: true } } ) ).result;
			const html = ( await call( 'resources/read', { uri: PREVIEW } ) ).result.contents[ 0 ].text;

			const { frame } = await hostView( page, html, result );
			await expect( frame.getByRole( 'heading', { name: 'Proposed change, not saved' } ) ).toBeVisible();
			const row = frame.getByRole( 'row' ).filter( { hasText: 'regular_price' } );
			await expect( row ).toContainText( '10' );
			await expect( row ).toContainText( '12' );
			expect( wp( 'wc', 'product', 'get', id, '--field=regular_price', '--user=e2e-admin' ).trim() ).toBe( '10' );

			const saved = ( await call( 'tools/call', { name: 'update_product', arguments: { id: Number( id ), regular_price: '12', confirm: true } } ) ).result;
			const written = await hostView( page, html, saved );
			await expect( written.frame.getByRole( 'heading', { name: 'Change saved' } ) ).toBeVisible();
			await expect( written.frame.getByText( 'Apps preview' ) ).toBeVisible();
			await expect( written.frame.locator( 'dd' ) ).toContainText( '12' );

			const failed = await hostView( page, html, { isError: true, content: [ { type: 'text', text: 'Refused for a test.' } ] } );
			await expect( failed.frame.getByText( 'Refused for a test.' ) ).toBeVisible();
		} finally {
			wp( 'wc', 'product', 'delete', id, '--force=1', '--user=e2e-admin' );
		}
	} );
} );
