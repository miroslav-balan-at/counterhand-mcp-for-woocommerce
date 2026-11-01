import { writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test, type Browser } from '@playwright/test';
import { listToolNames, mcpCall } from '../support/mcp';
import { anonymousContext, connect } from '../support/oauth';
import { adminStatePath, patchSettings, stateDirectory, wp } from '../support/store';

/* Preview and undo against a real store: what is sold and shown, not just what a tool answers. */
test.describe( 'Preview and undo', () => {
	const HISTORY = [ 'products:read', 'products:write', 'content:read', 'content:write', 'orders:read', 'orders:write', 'changes:read', 'changes:write' ];
	let restore: () => void;
	let product: number;

	const evaluate = ( code: string ) => {
		const script = join( stateDirectory, 'e2e-undo-eval.php' );
		writeFileSync( script, `<?php ${ code }` );

		return wp( 'eval-file', script );
	};

	const salePrice = () => evaluate( `echo wc_get_product( ${ product } )->get_price();` );

	async function caller( browser: Browser, baseURL: string, scopes: string[] ) {
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		const { tokens, discovery } = await connect( page, baseURL, scopes );
		await page.close();
		const client = await anonymousContext( baseURL );

		return {
			names: () => listToolNames( client, discovery.resource, tokens.access_token ),
			call: async ( name: string, args: Record< string, unknown > ) => {
				// Prices and coupons ask first; what is asked is tested in confirmation.spec.ts.
				const reply = await ( await mcpCall( client, discovery.resource, tokens.access_token, 'tools/call', { name, arguments: { confirm: true, ...args } } ) ).json();
				expect( reply.result, JSON.stringify( reply ) ).toBeDefined();

				return reply.result;
			},
		};
	}

	test.beforeAll( () => {
		restore = patchSettings( { products_read: true, products_write: true, content_read: true, content_write: true, orders_read: true, orders_write: true, changes_read: true, changes_write: true } );
	} );

	test.beforeEach( () => {
		product = Number( evaluate( `
			$product = new WC_Product_Simple();
			$product->set_name( 'E2E undo' );
			$product->set_regular_price( '10' );
			$product->set_status( 'publish' );
			$product->set_description( 'Original text' );
			echo $product->save();
		` ) );
	} );

	test.afterEach( () => {
		wp( 'post', 'delete', String( product ), '--force' );
	} );

	test.afterAll( () => restore() );

	test( 'a preview shows the difference and saves nothing', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, [ 'products:read', 'products:write' ] );

		const preview = ( await call( 'update_product', { id: product, regular_price: '99', preview: true } ) ).structuredContent;

		expect( preview.saved ).toBe( false );
		expect( preview.changes ).toEqual( [ { field: 'regular_price', before: '10', after: '99' } ] );
		expect( salePrice() ).toBe( '10' );
	} );

	test( 'a preview of a post compares raw text and saves nothing', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, [ 'content:read', 'content:write' ] );
		const post = wp( 'post', 'create', '--post_title=E2E gated', '--post_status=draft', '--porcelain' ).trim();

		const preview = await call( 'update_post', { id: Number( post ), title: { raw: 'Previewed' }, preview: true } );
		expect( preview.structuredContent.changes ).toEqual( [ { field: 'title', before: 'E2E gated', after: 'Previewed' } ] );
		expect( wp( 'post', 'get', post, '--field=post_title' ) ).toBe( 'E2E gated' );

		wp( 'post', 'delete', post, '--force' );
	} );

	test( 'asking for an old value reads nothing the tool would not show', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, HISTORY );
		evaluate( `update_post_meta( ${ product }, '_e2e_private', 'hunter2' );` );

		const preview = await call( 'update_product', { id: product, meta_data: [ { key: 'x', value: 'y' } ], preview: true } );
		expect( JSON.stringify( preview ), 'private meta stays private' ).not.toContain( 'hunter2' );
		expect( preview.structuredContent.changes[ 0 ].before ).toBeNull();

		const recorded = await call( 'update_product', { id: product, meta_data: [ { key: 'e2e_public', value: 'y' } ] } );
		expect( recorded.structuredContent.change_id, 'nothing to put back, and the write stands' ).toBeUndefined();
		expect( recorded.structuredContent.undo_unavailable, 'and the agent is told' ).toBeTruthy();
		expect( evaluate( `echo get_post_meta( ${ product }, 'e2e_public', true );` ) ).toBe( 'y' );
		expect( JSON.stringify( await call( 'get_recent_changes', {} ) ) ).not.toContain( 'hunter2' );
	} );

	test( 'a coupon\'s customer emails stay out of a preview', async ( { browser, baseURL } ) => {
		const coupons = patchSettings( { coupons_read: true, coupons_write: true } );
		const { call } = await caller( browser, baseURL as string, [ 'coupons:read', 'coupons:write' ] );
		const coupon = Number( evaluate( `
			$coupon = new WC_Coupon();
			$coupon->set_code( 'e2e-undo-' . wp_rand() );
			$id = $coupon->save();
			add_post_meta( $id, '_used_by', 'victim@example.test' );
			echo $id;
		` ) );
		expect( evaluate( `echo implode( ',', ( new WC_Coupon( ${ coupon } ) )->get_used_by() );` ), 'the fixture really holds it' ).toContain( 'victim@example.test' );

		const preview = await call( 'update_coupon', { id: coupon, used_by: [], preview: true } );
		coupons();
		wp( 'post', 'delete', String( coupon ), '--force' );

		expect( JSON.stringify( preview ) ).not.toContain( 'victim@example.test' );
	} );

	test( 'an undo puts the price customers pay back, by the route', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, HISTORY );

		const changed = ( await call( 'update_product', { id: product, regular_price: '99' } ) ).structuredContent;
		expect( changed.change_id ).toMatch( /^[a-f0-9]{32}$/ );
		expect( salePrice() ).toBe( '99' );

		const unconfirmed = await call( 'undo_change', { change_id: changed.change_id, confirm: false } );
		expect( unconfirmed.isError ).toBe( true );
		expect( salePrice() ).toBe( '99' );

		const undone = ( await call( 'undo_change', { change_id: changed.change_id, confirm: true } ) ).structuredContent;
		expect( undone.undone ).toBe( true );

		// Not just the regular price meta: the trap with a meta write is a stale _price.
		expect( salePrice() ).toBe( '10' );
		expect( evaluate( `echo get_post_meta( ${ product }, '_price', true );` ) ).toBe( '10' );
		const store = await ( await ( await anonymousContext( baseURL as string ) ).get( `/wp-json/wc/store/v1/products/${ product }` ) ).json();
		expect( Number( store.prices.regular_price ) / 10 ** store.prices.currency_minor_unit, 'what the storefront charges' ).toBe( 10 );

		const again = await call( 'undo_change', { change_id: changed.change_id, confirm: true } );
		expect( again.isError ).toBe( true );
		expect( JSON.stringify( again ) ).toContain( 'already been undone' );
	} );

	test( 'an undo leaves other fields alone, and refuses if its own have changed again', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, HISTORY );

		const first = ( await call( 'update_product', { id: product, regular_price: '20' } ) ).structuredContent.change_id;
		await call( 'update_product', { id: product, name: 'E2E undo renamed' } );

		await call( 'update_product', { id: product, regular_price: '30' } );
		const refused = await call( 'undo_change', { change_id: first, confirm: true } );
		expect( refused.isError ).toBe( true );
		expect( JSON.stringify( refused ) ).toContain( 'changed again' );
		expect( salePrice(), 'the later price stands' ).toBe( '30' );

		const second = ( await call( 'update_product', { id: product, regular_price: '40' } ) ).structuredContent.change_id;
		await call( 'undo_change', { change_id: second, confirm: true } );
		expect( salePrice() ).toBe( '30' );
		expect( evaluate( `echo get_the_title( ${ product } );` ), 'the rename stands' ).toBe( 'E2E undo renamed' );
	} );

	test( 'a post\'s raw text comes back, not what filters made of it', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, HISTORY );
		const post = Number( wp( 'post', 'create', '--post_title=E2E story', '--post_content=[gallery] raw text', '--post_status=draft', '--porcelain' ).trim() );

		const changed = await call( 'update_post', { id: post, content: { raw: 'Replaced' }, title: { raw: 'Replaced title' } } );
		expect( changed.structuredContent.change_id ).toBeTruthy();

		const undone = await call( 'undo_change', { change_id: changed.structuredContent.change_id, confirm: true } );
		expect( undone.isError, JSON.stringify( undone ) ).toBeFalsy();
		expect( evaluate( `echo get_post_field( 'post_content', ${ post } );` ) ).toBe( '[gallery] raw text' );
		expect( evaluate( `echo get_the_title( ${ post } );` ) ).toBe( 'E2E story' );

		wp( 'post', 'delete', String( post ), '--force' );
	} );

	test( 'undoing a create deletes it, unless it has been edited since', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, HISTORY );

		const created = ( await call( 'create_product', { name: 'E2E undo created' } ) ).structuredContent;
		const id = created.product.id;
		await call( 'undo_change', { change_id: created.change_id, confirm: true } );
		expect( evaluate( `echo get_post( ${ id } ) ? 'there' : 'gone';` ) ).toBe( 'gone' );

		const kept = ( await call( 'create_product', { name: 'E2E undo kept' } ) ).structuredContent;
		evaluate( `wp_update_post( [ 'ID' => ${ kept.product.id }, 'post_modified' => '2030-01-01 00:00:00', 'post_modified_gmt' => '2030-01-01 00:00:00' ] ); global $wpdb; $wpdb->update( $wpdb->posts, [ 'post_modified' => '2030-01-01 00:00:00', 'post_modified_gmt' => '2030-01-01 00:00:00' ], [ 'ID' => ${ kept.product.id } ] ); clean_post_cache( ${ kept.product.id } );` );
		const refused = await call( 'undo_change', { change_id: kept.change_id, confirm: true } );
		expect( refused.isError ).toBe( true );
		expect( evaluate( `echo get_post( ${ kept.product.id } ) ? 'there' : 'gone';` ) ).toBe( 'there' );
		wp( 'post', 'delete', String( kept.product.id ), '--force' );
	} );

	test( 'another connection can neither see nor undo a change, nor a guessed id', async ( { browser, baseURL } ) => {
		const owner = await caller( browser, baseURL as string, HISTORY );
		const other = await caller( browser, baseURL as string, HISTORY );

		const changed = ( await owner.call( 'update_product', { id: product, regular_price: '99' } ) ).structuredContent.change_id;

		const refused = await other.call( 'undo_change', { change_id: changed, confirm: true } );
		expect( refused.isError ).toBe( true );
		expect( JSON.stringify( refused ) ).toContain( 'No change' );
		expect( salePrice() ).toBe( '99' );

		const seen = ( await other.call( 'get_recent_changes', {} ) ).structuredContent.changes;
		expect( seen.map( ( change: { change_id: string } ) => change.change_id ) ).not.toContain( changed );
		const mine = ( await owner.call( 'get_recent_changes', {} ) ).structuredContent.changes;
		expect( mine[ 0 ].change_id ).toBe( changed );
		expect( mine[ 0 ].before ).toEqual( { regular_price: '10' } );
	} );

	test( 'a store that switched a group off blocks undoing what it covered', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, HISTORY );
		const changed = ( await call( 'update_product', { id: product, regular_price: '99' } ) ).structuredContent.change_id;

		const putBack = patchSettings( { products_write: false } );
		const refused = await call( 'undo_change', { change_id: changed, confirm: true } );
		putBack();

		expect( refused.isError ).toBe( true );
		expect( JSON.stringify( refused ) ).toContain( 'can no longer use' );
		expect( salePrice() ).toBe( '99' );
	} );

	test( 'preview on a tool that has none is refused, and nothing is saved', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, HISTORY );

		const meta = await call( 'set_product_meta', { id: product, key: 'e2e_preview', value: 'written', preview: true } );
		expect( meta.isError ).toBe( true );
		expect( evaluate( `echo get_post_meta( ${ product }, 'e2e_preview', true );` ), 'a dry run must never be the real write' ).toBe( '' );

		const order = evaluate( `echo wc_create_order( [ 'status' => 'pending' ] )->get_id();` );
		const status = await call( 'update_order_status', { id: Number( order ), status: 'on-hold', confirm: true, preview: true } );
		expect( status.isError ).toBe( true );
		expect( evaluate( `echo wc_get_order( ${ order } )->get_status();` ) ).toBe( 'pending' );
		evaluate( `wc_get_order( ${ order } )->delete( true );` );

		const unconfirmedPreview = await call( 'update_product', { id: product, regular_price: '5', preview: 'true' } );
		expect( salePrice(), 'a preview written as a string is no write either' ).toBe( '10' );
		expect( unconfirmedPreview.structuredContent?.saved ?? false ).toBe( false );
	} );

	test( 'protected meta cannot be written through an open tool', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, HISTORY );

		const refused = await call( 'update_product', { id: product, meta_data: [ { key: '_e2e_protected', value: 'written' } ] } );
		expect( refused.isError ).toBe( true );
		expect( evaluate( `echo get_post_meta( ${ product }, '_e2e_protected', true );` ) ).toBe( '' );

		const coupon = patchSettings( { coupons_read: true, coupons_write: true } );
		const allowed = await call( 'update_product', { id: product, meta_data: [ { key: 'e2e_public', value: 'fine' } ] } );
		coupon();
		expect( allowed.isError ).toBeFalsy();
	} );

	test( 'a store that switched history off records nothing, whatever the grant', async ( { browser, baseURL } ) => {
		const { call } = await caller( browser, baseURL as string, HISTORY );
		const putBack = patchSettings( { changes_write: false } );
		const changed = ( await call( 'update_product', { id: product, regular_price: '11' } ) ).structuredContent;
		putBack();

		expect( changed.change_id ).toBeUndefined();
	} );

	test.describe( 'a whole record comes back', () => {
		const record = ( route: string ) => JSON.parse( evaluate( `
			wp_set_current_user( 1 );
			$request = new WP_REST_Request( 'GET', '${ route }' );
			$request->set_param( 'context', 'edit' );
			$data = json_decode( wp_json_encode( rest_do_request( $request )->get_data() ), true );
			foreach ( [ 'date_modified', 'date_modified_gmt', 'modified', 'modified_gmt', '_links', 'guid', 'permalink_template', 'generated_slug', 'related_ids' ] as $volatile ) {
				unset( $data[ $volatile ] );
			}
			echo wp_json_encode( $data );
		` ) );

		const roundTrip = async ( call: ( name: string, args: Record< string, unknown > ) => Promise< any >, route: string, tool: string, args: Record< string, unknown > ) => {
			const before = record( route );
			const changed = ( await call( tool, args ) ).structuredContent;
			expect( changed.change_id, JSON.stringify( changed ) ).toBeTruthy();
			expect( record( route ), 'the write changed something' ).not.toEqual( before );

			const undone = await call( 'undo_change', { change_id: changed.change_id, confirm: true } );
			expect( undone.isError, JSON.stringify( undone ) ).toBeFalsy();
			expect( record( route ) ).toEqual( before );
		};

		test( 'a product, through every kind of field', async ( { browser, baseURL } ) => {
			const { call } = await caller( browser, baseURL as string, HISTORY );
			const route = `/wc/v3/products/${ product }`;
			evaluate( `
				$product = wc_get_product( ${ product } );
				$product->set_sale_price( '8' );
				$product->set_date_on_sale_from( '2026-01-01' );
				$product->set_date_on_sale_to( '2030-01-01' );
				$product->set_manage_stock( true );
				$product->set_stock_quantity( 5 );
				$product->save();
			` );

			for ( const fields of [
				{ regular_price: '99' },
				{ regular_price: '6' },
				{ sale_price: '' },
				{ date_on_sale_from: '2027-01-01T00:00:00' },
				{ name: 'New', slug: 'new-e2e-undo' },
				{ status: 'draft' },
				{ description: 'plain', short_description: 'short' },
				{ stock_quantity: 50 },
				{ manage_stock: false },
				{ weight: '9', dimensions: { length: '1', width: '2', height: '3' } },
				{ virtual: true, tax_status: 'none' },
				{ attributes: [ { name: 'Local', options: [ 'a', 'b' ], visible: true } ] },
			] ) {
				await roundTrip( call, route, 'update_product', { id: product, ...fields } );
			}

			const retyped = ( await call( 'update_product', { id: product, type: 'external', external_url: 'https://example.test', button_text: 'Go' } ) ).structuredContent;
			expect( retyped.change_id, 'a new type can delete what the old one held' ).toBeUndefined();
			expect( retyped.undo_unavailable ).toBeTruthy();
		} );

		test( 'a sale cleared by a lower price comes back', async ( { browser, baseURL } ) => {
			const { call } = await caller( browser, baseURL as string, HISTORY );
			evaluate( `$product = wc_get_product( ${ product } ); $product->set_sale_price( '8' ); $product->save();` );

			const changed = ( await call( 'update_product', { id: product, regular_price: '5' } ) ).structuredContent;
			expect( evaluate( `echo wc_get_product( ${ product } )->get_sale_price();` ), 'WooCommerce cleared it' ).toBe( '' );
			await call( 'undo_change', { change_id: changed.change_id, confirm: true } );

			expect( salePrice() ).toBe( '8' );
		} );

		test( 'a variation, a category, a coupon and a post', async ( { browser, baseURL } ) => {
			const coupons = patchSettings( { coupons_read: true, coupons_write: true, variations_read: true, variations_write: true, taxonomy_read: true, taxonomy_write: true } );
			const { call } = await caller( browser, baseURL as string, [ ...HISTORY, 'coupons:read', 'coupons:write', 'variations:read', 'variations:write', 'taxonomy:read', 'taxonomy:write' ] );
			const [ parent, variation, category, coupon, post ] = JSON.parse( evaluate( `
				$parent = new WC_Product_Variable();
				$parent->set_name( 'E2E undo parent' );
				$parent_id = $parent->save();
				$variation = new WC_Product_Variation();
				$variation->set_parent_id( $parent_id );
				$variation->set_regular_price( '5' );
				$category = wp_insert_term( 'E2E undo cat ' . wp_rand(), 'product_cat' );
				$coupon = new WC_Coupon();
				$coupon->set_code( 'e2e-undo-' . wp_rand() );
				$coupon->set_amount( 5 );
				echo wp_json_encode( [ $parent_id, $variation->save(), $category['term_id'], $coupon->save(), wp_insert_post( [ 'post_title' => 'E2E undo post', 'post_status' => 'publish', 'post_content' => 'text' ] ) ] );
			` ) ) as number[];

			await roundTrip( call, `/wc/v3/products/${ parent }/variations/${ variation }`, 'update_product_variation', { product_id: parent, id: variation, regular_price: '6', description: 'y', stock_status: 'outofstock' } );
			await roundTrip( call, `/wc/v3/products/categories/${ category }`, 'update_product_category', { id: category, name: 'E2E renamed', description: 'd', display: 'products' } );
			await roundTrip( call, `/wc/v3/coupons/${ coupon }`, 'update_coupon', { id: coupon, amount: '50', discount_type: 'percent', product_ids: [ product ] } );
			await roundTrip( call, `/wc/v3/coupons/${ coupon }`, 'update_coupon', { id: coupon, date_expires: '2031-01-01T00:00:00' } );
			await roundTrip( call, `/wp/v2/posts/${ post }`, 'update_post', { id: post, title: { raw: 'T' }, content: { raw: 'C' }, excerpt: { raw: 'E' }, status: 'draft', slug: 'e2e-undo-slug' } );

			coupons();
			evaluate( `wp_delete_post( ${ parent }, true ); wp_delete_post( ${ variation }, true ); wp_delete_term( ${ category }, 'product_cat' ); wp_delete_post( ${ coupon }, true ); wp_delete_post( ${ post }, true );` );
		} );

		test( 'shipping and tax either come back or say they did not', async ( { browser, baseURL } ) => {
			const shipping = patchSettings( { shipping_read: true, shipping_write: true, taxes_read: true, taxes_write: true } );
			const { call } = await caller( browser, baseURL as string, [ ...HISTORY, 'shipping:read', 'shipping:write', 'taxes:read', 'taxes:write' ] );
			const [ zone, method, rate ] = JSON.parse( evaluate( `
				$zone = new WC_Shipping_Zone();
				$zone->set_zone_name( 'E2E undo zone' );
				$zone_id = $zone->save();
				$method = $zone->add_shipping_method( 'flat_rate' );
				$rate = WC_Tax::_insert_tax_rate( [ 'tax_rate_country' => 'AT', 'tax_rate' => '20.0000', 'tax_rate_name' => 'E2E', 'tax_rate_priority' => 1, 'tax_rate_compound' => 0, 'tax_rate_shipping' => 1, 'tax_rate_order' => 0, 'tax_rate_class' => '' ] );
				echo wp_json_encode( [ $zone_id, $method, $rate ] );
			` ) ) as number[];

			await roundTrip( call, `/wc/v3/shipping/zones/${ zone }`, 'update_shipping_zone', { id: zone, name: 'E2E zone 2', order: 3 } );
			await roundTrip( call, `/wc/v3/taxes/${ rate }`, 'update_tax_rate', { id: rate, rate: '10', name: 'E2E2', shipping: false } );

			const method_change = ( await call( 'update_shipping_zone_method', { zone_id: zone, instance_id: method, settings: { title: 'Express', cost: '12' } } ) ).structuredContent;
			expect( method_change.change_id, 'read and write shapes differ, so it promises no undo' ).toBeUndefined();
			expect( method_change.undo_unavailable ).toBeTruthy();

			const postcodes = ( await call( 'update_tax_rate', { id: rate, postcodes: [ '1010' ] } ) ).structuredContent;
			const undone = await call( 'undo_change', { change_id: postcodes.change_id, confirm: true } );
			const now = record( `/wc/v3/taxes/${ rate }` );
			if ( undone.isError ) {
				expect( JSON.stringify( undone ), 'an undo that did not take must say so' ).toContain( 'partly undone' );
			} else {
				expect( now.postcodes ).toEqual( [] );
			}

			shipping();
			evaluate( `( new WC_Shipping_Zone( ${ zone } ) )->delete(); WC_Tax::_delete_tax_rate( ${ rate } );` );
		} );

		test( 'what was created and has since changed is not deleted', async ( { browser, baseURL } ) => {
			const taxonomy = patchSettings( { taxonomy_read: true, taxonomy_write: true } );
			const { call } = await caller( browser, baseURL as string, [ ...HISTORY, 'taxonomy:read', 'taxonomy:write' ] );

			const category = ( await call( 'create_product_category', { name: `E2E undo created ${ Date.now() }` } ) ).structuredContent;
			const id = category[ 'product category' ].id;
			evaluate( `wp_set_object_terms( ${ product }, [ ${ id } ], 'product_cat' );` );
			const inUse = await call( 'undo_change', { change_id: category.change_id, confirm: true } );
			expect( inUse.isError, 'it is on a product now' ).toBe( true );
			evaluate( `wp_delete_term( ${ id }, 'product_cat' );` );

			const renamed = ( await call( 'create_product_category', { name: `E2E undo renamed ${ Date.now() }` } ) ).structuredContent;
			const renamedId = renamed[ 'product category' ].id;
			evaluate( `wp_update_term( ${ renamedId }, 'product_cat', [ 'name' => 'Edited by a person' ] );` );
			expect( ( await call( 'undo_change', { change_id: renamed.change_id, confirm: true } ) ).isError ).toBe( true );
			evaluate( `wp_delete_term( ${ renamedId }, 'product_cat' );` );

			const sold = ( await call( 'create_product', { name: 'E2E undo sold' } ) ).structuredContent;
			evaluate( `update_post_meta( ${ sold.product.id }, 'total_sales', 1 ); wc_delete_product_transients( ${ sold.product.id } );` );
			expect( ( await call( 'undo_change', { change_id: sold.change_id, confirm: true } ) ).isError, 'it has been sold' ).toBe( true );
			wp( 'post', 'delete', String( sold.product.id ), '--force' );

			const zone = patchSettings( { shipping_read: true, shipping_write: true } );
			taxonomy();
			zone();
		} );

		test( 'reviews, which hold people\'s names, are not kept', async ( { browser, baseURL } ) => {
			const reviews = patchSettings( { reviews_read: true, reviews_write: true } );
			const { call } = await caller( browser, baseURL as string, [ ...HISTORY, 'reviews:read', 'reviews:write' ] );
			const review = Number( evaluate( `echo wp_insert_comment( [ 'comment_post_ID' => ${ product }, 'comment_author' => 'A', 'comment_author_email' => 'reviewer@example.test', 'comment_content' => 'Good', 'comment_type' => 'review', 'comment_approved' => 1 ] );` ) );

			const changed = await call( 'update_product_review', { id: review, review: 'Changed', reviewer_email: 'other@example.test' } );
			reviews();

			expect( changed.structuredContent.change_id ).toBeUndefined();
			expect( JSON.stringify( await call( 'get_recent_changes', {} ) ) ).not.toContain( 'reviewer@example.test' );
		} );
	} );

	test( 'no history means no change id, and what cannot be put back is not recorded', async ( { browser, baseURL } ) => {
		const without = await caller( browser, baseURL as string, [ 'products:read', 'products:write' ] );
		const plain = ( await without.call( 'update_product', { id: product, regular_price: '50' } ) ).structuredContent;
		expect( plain.change_id ).toBeUndefined();
		expect( await without.names() ).not.toContain( 'undo_change' );

		const withHistory = await caller( browser, baseURL as string, HISTORY );
		const order = evaluate( `echo wc_create_order( [ 'status' => 'pending' ] )->get_id();` );
		const status = await withHistory.call( 'update_order_status', { id: Number( order ), status: 'on-hold', confirm: true } );
		expect( status.structuredContent?.change_id, 'an order\'s emails cannot be taken back' ).toBeUndefined();
		evaluate( `wc_get_order( ${ order } )->delete( true );` );
	} );
} );
