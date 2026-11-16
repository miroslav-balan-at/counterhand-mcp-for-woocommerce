import { writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test, type Browser } from '@playwright/test';
import { mcpCall } from '../support/mcp';
import { anonymousContext, connect } from '../support/oauth';
import { adminStatePath, patchSettings, stateDirectory, wp } from '../support/store';

/*
 * Claude Code sends a list or object argument the tool does not publish as JSON text. The official
 * SDK client sends real objects, which is why no other spec ever saw it: these calls are raw on purpose.
 */
test.describe( 'Arguments sent as JSON text', () => {
	let restore: () => void;
	let product: number;
	let tags: number[];

	const evaluate = ( code: string ) => {
		const script = join( stateDirectory, 'e2e-encoded-eval.php' );
		writeFileSync( script, `<?php ${ code }` );

		return wp( 'eval-file', script );
	};

	async function caller( browser: Browser, baseURL: string ) {
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		const { tokens, discovery } = await connect( page, baseURL, [ 'products:read', 'products:write' ] );
		await page.close();
		const client = await anonymousContext( baseURL );

		return async ( name: string, args: Record< string, unknown > ) => {
			const reply = await ( await mcpCall( client, discovery.resource, tokens.access_token, 'tools/call', { name, arguments: args } ) ).json();
			expect( reply.result, JSON.stringify( reply ) ).toBeDefined();

			return reply.result;
		};
	}

	test.beforeAll( () => {
		restore = patchSettings( { products_read: true, products_write: true } );
	} );

	test.beforeEach( () => {
		product = Number( evaluate( `
			$product = new WC_Product_Simple();
			$product->set_name( 'E2E encoded' );
			$product->set_regular_price( '10' );
			echo $product->save();
		` ) );
		tags = JSON.parse( evaluate( `
			echo wp_json_encode( array_map( static fn ( string $name ): int => (int) wp_insert_term( $name, 'product_tag' )['term_id'], [ 'e2e-encoded-a-' . wp_generate_password( 6, false ), 'e2e-encoded-b-' . wp_generate_password( 6, false ) ] ) );
		` ) );
	} );

	test.afterEach( () => {
		wp( 'post', 'delete', String( product ), '--force' );
		evaluate( `foreach ( ${ JSON.stringify( tags ) } as $tag ) { wp_delete_term( $tag, 'product_tag' ); }` );
	} );

	test.afterAll( () => restore() );

	test( 'tags and dimensions sent as text are stored as a list and an object', async ( { browser, baseURL } ) => {
		const call = await caller( browser, baseURL as string );

		const result = await call( 'update_product', {
			id: product,
			tags: JSON.stringify( tags.map( ( id ) => ( { id } ) ) ),
			dimensions: JSON.stringify( { length: '11.7', width: '11', height: '21.5' } ),
		} );

		expect( result.isError, JSON.stringify( result ) ).toBeFalsy();

		const stored = JSON.parse( evaluate( `
			$product = wc_get_product( ${ product } );
			echo wp_json_encode( [ 'tags' => $product->get_tag_ids(), 'dimensions' => [ $product->get_length(), $product->get_width(), $product->get_height() ] ] );
		` ) );

		expect( [ ...stored.tags ].sort() ).toEqual( [ ...tags ].sort() );
		expect( stored.dimensions ).toEqual( [ '11.7', '11', '21.5' ] );
	} );

	test( 'meta_data sent as text meets the custom field rules and saves nothing', async ( { browser, baseURL } ) => {
		const call = await caller( browser, baseURL as string );

		const result = await call( 'update_product', {
			id: product,
			meta_data: JSON.stringify( [ { key: '_e2e_private', value: 'x' } ] ),
		} );

		expect( result.isError, JSON.stringify( result ) ).toBe( true );
		expect( JSON.stringify( result.content ) ).toContain( '_e2e_private' );
		expect( evaluate( `echo (string) get_post_meta( ${ product }, '_e2e_private', true );` ) ).toBe( '' );
	} );
} );
