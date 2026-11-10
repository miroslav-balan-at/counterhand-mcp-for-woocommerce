import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test, type Page } from '@playwright/test';
import { patchOption, patchSettings, shopManagerStatePath, stateDirectory, wp } from '../support/store';

const REWRITES = '/wp-admin/admin.php?page=counterhand-mcp-rewrites';

const evaluate = ( code: string ) => {
	const script = join( stateDirectory, 'e2e-rewrites-eval.php' );
	writeFileSync( script, `<?php ${ code }` );

	return wp( 'eval-file', script );
};

const description = ( id: number ) => evaluate( `echo wc_get_product( ${ id } )->get_description( 'edit' );` );

const waiting = () => Number( evaluate( `global $wpdb; echo (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}counterhand_rewrites WHERE status = 'queued'" );` ) );

/*
 * The queue runs in the background on a real store; here it runs when the test says so.
 * A page load can start Action Scheduler's own runner, which then holds the actions and
 * fails this command, so the test waits for the queue rather than for the command.
 */
const runQueue = () => {
	for ( let attempt = 0; attempt < 30 && waiting() > 0; attempt++ ) {
		try {
			wp( 'action-scheduler', 'run', '--hooks=counterhand_rewrite_item', '--group=counterhand-rewrites', '--force' );
		} catch {
			execFileSync( 'sleep', [ '2' ] );
		}
	}
	expect( waiting(), 'every queued rewrite ran' ).toBe( 0 );
};

/* A bulk rewrite against a real store: what ends up in the product, not what the screen says. */
test.describe( 'Bulk rewrites', () => {
	let restoreSettings: () => void;
	let restoreChat: () => void;
	let category: number;
	let products: number[];

	async function startJob( page: Page ) {
		await page.goto( REWRITES );
		await page.locator( '#counterhand_rewrite_category' ).selectOption( String( category ) );
		await page.locator( '#counterhand_rewrite_field' ).selectOption( 'description' );
		await page.locator( '#counterhand_rewrite_instruction' ).fill( 'Shorter, please.' );
		await page.getByRole( 'button', { name: 'Write new texts' } ).click();
		await expect( page.locator( '.counterhand-rewrites__notice' ) ).toContainText( '2 products are queued' );
		runQueue();
		await page.goto( REWRITES );
	}

	const row = ( page: Page, id: number ) => page.locator( `tr[data-product="${ id }"]` );

	test.beforeAll( () => {
		restoreSettings = patchSettings( { products_read: true, products_write: true, changes_read: true, changes_write: true } );
		restoreChat = patchOption( 'counterhand_chat', { provider: 'e2e-scripted', model: 'e2e-model' } );
	} );

	test.beforeEach( () => {
		category = Number( wp( 'term', 'create', 'product_cat', `E2E rewrites ${ Date.now() }`, '--porcelain' ) );
		products = [ 'One', 'Two' ].map( ( name ) =>
			Number(
				evaluate( `
					$product = new WC_Product_Simple();
					$product->set_name( 'E2E rewrite ${ name }' );
					$product->set_status( 'publish' );
					$product->set_description( 'Original ${ name }' );
					$product->set_category_ids( [ ${ category } ] );
					echo $product->save();
				` )
			)
		);
	} );

	test.afterEach( () => {
		products.forEach( ( id ) => wp( 'post', 'delete', String( id ), '--force' ) );
		wp( 'term', 'delete', 'product_cat', String( category ) );
	} );

	test.afterAll( () => {
		restoreChat();
		restoreSettings();
	} );

	test( 'only the approved text reaches its product, cleaned, and undo puts it back', async ( { page } ) => {
		await startJob( page );

		await expect( row( page, products[ 0 ] ) ).toContainText( 'Ready to review' );
		await expect( row( page, products[ 0 ] ).locator( '.counterhand-rewrites__text--new' ) ).toHaveText( 'E2E rewrite' );
		expect( await page.evaluate( () => ( window as unknown as { counterhandE2eRan?: boolean } ).counterhandE2eRan ) ).toBeUndefined();
		await expect( page.locator( '.counterhand-card__desc' ).last() ).toContainText( '240 tokens in, 60 tokens out' );

		await row( page, products[ 0 ] ).getByRole( 'checkbox' ).check();
		await page.getByRole( 'button', { name: 'Apply ticked texts' } ).click();

		await expect( row( page, products[ 0 ] ) ).toContainText( 'Applied' );
		expect( description( products[ 0 ] ) ).toBe( '<p>E2E rewrite</p>' );
		expect( description( products[ 1 ] ) ).toBe( 'Original Two' );

		await row( page, products[ 0 ] ).getByRole( 'checkbox' ).check();
		await page.getByRole( 'button', { name: 'Undo ticked texts' } ).click();

		await expect( row( page, products[ 0 ] ) ).toContainText( 'Undone' );
		expect( description( products[ 0 ] ) ).toBe( 'Original One' );
	} );

	test( 'a text edited after the model read it is not overwritten', async ( { page } ) => {
		await startJob( page );
		evaluate( `$product = wc_get_product( ${ products[ 0 ] } ); $product->set_description( 'Edited by hand' ); $product->save();` );

		await row( page, products[ 0 ] ).getByRole( 'checkbox' ).check();
		await page.getByRole( 'button', { name: 'Apply ticked texts' } ).click();

		await expect( row( page, products[ 0 ] ) ).toContainText( 'changed after the model read it' );
		expect( description( products[ 0 ] ) ).toBe( 'Edited by hand' );
	} );

	test( 'a store that keeps products read-only starts nothing', async ( { page } ) => {
		const restore = patchSettings( { products_write: false } );

		try {
			await page.goto( REWRITES );
			await expect( page.locator( '.counterhand-chip--warn' ) ).toContainText( 'Products cannot be changed by AI' );
			await expect( page.getByRole( 'button', { name: 'Write new texts' } ) ).toHaveCount( 0 );
		} finally {
			restore();
		}
	} );

	test( 'another person cannot see, apply or undo an administrator’s texts, even by posting their ids', async ( { page, browser } ) => {
		await startJob( page );
		const pending = await row( page, products[ 1 ] ).getByRole( 'checkbox' ).getAttribute( 'value' );
		await row( page, products[ 0 ] ).getByRole( 'checkbox' ).check();
		await page.getByRole( 'button', { name: 'Apply ticked texts' } ).click();
		const applied = await row( page, products[ 0 ] ).getByRole( 'checkbox' ).getAttribute( 'value' );

		const manager = await browser.newPage( { storageState: shopManagerStatePath } );
		await startJob( manager );
		await expect( manager.locator( 'tr[data-product]' ) ).toHaveCount( 2 );

		// The manager's own form and nonce, carrying the administrator's item ids.
		for ( const [ id, button ] of [ [ pending, 'Apply ticked texts' ], [ applied, 'Undo ticked texts' ] ] as const ) {
			const box = row( manager, products[ 0 ] ).getByRole( 'checkbox' );
			await box.evaluate( ( element, value ) => element.setAttribute( 'value', String( value ) ), id );
			await box.check();
			await manager.getByRole( 'button', { name: button } ).click();
		}
		await manager.close();

		expect( description( products[ 0 ] ) ).toBe( '<p>E2E rewrite</p>' );
		expect( description( products[ 1 ] ) ).toBe( 'Original Two' );
		await page.goto( REWRITES );
		await expect( row( page, products[ 0 ] ) ).toContainText( 'Applied' );
		await expect( row( page, products[ 1 ] ) ).toContainText( 'Ready to review' );
	} );
} );
