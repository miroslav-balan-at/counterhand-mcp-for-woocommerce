import { expect, test, type Browser } from '@playwright/test';
import { mcpCall } from '../support/mcp';
import { anonymousContext, connect } from '../support/oauth';
import { adminStatePath, patchSettings, wp } from '../support/store';

test.describe( 'An unpaid order can be corrected, a paid one cannot', () => {
	let restore: () => void;
	let product: string;
	const orders: string[] = [];

	test.beforeAll( () => {
		restore = patchSettings( { orders_read: true, orders_write: true } );
		product = wp( 'wc', 'product', 'create', '--name=E2E order line', '--regular_price=10', '--status=publish', '--user=e2e-admin', '--porcelain' ).trim();
	} );

	test.afterAll( () => {
		for ( const order of orders ) {
			wp( 'wc', 'shop_order', 'delete', order, '--force=true', '--user=e2e-admin' );
		}
		wp( 'post', 'delete', product, '--force' );
		restore();
	} );

	function order( status: string ): string {
		const id = wp( 'wc', 'shop_order', 'create', `--status=${ status }`, `--line_items=[{"product_id":${ product },"quantity":1}]`, '--user=e2e-admin', '--porcelain' ).trim();
		orders.push( id );

		return id;
	}

	const stored = ( id: string ) => JSON.parse( wp( 'wc', 'shop_order', 'get', id, '--format=json', '--user=e2e-admin' ) );

	async function caller( browser: Browser, baseURL: string ) {
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		const { tokens, discovery } = await connect( page, baseURL, [ 'orders:read', 'orders:write' ] );
		await page.close();
		const client = await anonymousContext( baseURL );

		return async ( args: Record< string, unknown > ) => ( await ( await mcpCall( client, discovery.resource, tokens.access_token, 'tools/call', { name: 'update_order', arguments: args } ) ).json() ).result;
	}

	test( 'shipping is added, a line changed and removed by its id, and the totals follow', async ( { browser, baseURL } ) => {
		const call = await caller( browser, baseURL as string );
		const id = order( 'pending' );
		const shipping = [ { method_id: 'flat_rate', method_title: 'Post', total: '5.00' } ];

		const unconfirmed = await call( { id: Number( id ), shipping_lines: shipping } );
		expect( unconfirmed.isError ).toBe( true );
		expect( stored( id ).shipping_lines ).toHaveLength( 0 );

		expect( ( await call( { id: Number( id ), shipping_lines: shipping, confirm: true } ) ).isError ).toBeFalsy();
		const withShipping = stored( id );
		expect( withShipping.shipping_lines ).toHaveLength( 1 );
		expect( Number( withShipping.total ) ).toBeCloseTo( Number( withShipping.line_items[ 0 ].total ) + Number( withShipping.line_items[ 0 ].total_tax ) + 5 + Number( withShipping.shipping_tax ), 2 );

		const line = withShipping.line_items[ 0 ].id;
		expect( ( await call( { id: Number( id ), line_items: [ { id: line, quantity: 2 } ], confirm: true } ) ).isError ).toBeFalsy();
		expect( stored( id ).line_items[ 0 ].quantity ).toBe( 2 );

		const shippingLine = withShipping.shipping_lines[ 0 ].id;
		expect( ( await call( { id: Number( id ), shipping_lines: [ { id: shippingLine, method_id: null } ], confirm: true } ) ).isError ).toBeFalsy();
		expect( stored( id ).shipping_lines ).toHaveLength( 0 );
		expect( stored( id ).status ).toBe( 'pending' );
	} );

	test( 'a paid order is refused, agreement or not, and stays as it was', async ( { browser, baseURL } ) => {
		const call = await caller( browser, baseURL as string );
		const id = order( 'processing' );
		const before = stored( id );

		const refused = await call( { id: Number( id ), shipping_lines: [ { method_id: 'flat_rate', method_title: 'Post', total: '5.00' } ], confirm: true } );
		expect( refused.isError ).toBe( true );
		expect( refused.content[ 0 ].text ).toContain( 'not waiting for payment' );
		expect( stored( id ).total ).toBe( before.total );
		expect( stored( id ).shipping_lines ).toHaveLength( 0 );
	} );
} );
