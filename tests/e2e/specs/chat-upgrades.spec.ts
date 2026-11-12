import { writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test, type Page } from '@playwright/test';
import { patchOption, patchSettings, shopManagerStatePath, stateDirectory, wp } from '../support/store';

const CHAT = '/wp-admin/admin.php?page=counterhand-mcp';

const evaluate = ( code: string ) => {
	const script = join( stateDirectory, 'e2e-chat-eval.php' );
	writeFileSync( script, `<?php ${ code }` );

	return wp( 'eval-file', script );
};

async function ask( page: Page, text: string ) {
	await page.locator( '#counterhand-chat-input' ).fill( text );
	await page.locator( '#counterhand-chat-send' ).click();
}

/* What the chat keeps and offers beyond one exchange, driven by the scripted model. */
test.describe( 'Chat upgrades', () => {
	let restoreChat: () => void;
	let restoreSettings: () => void;

	test.beforeAll( () => {
		restoreSettings = patchSettings( { products_read: true, products_write: true, reports_read: true } );
		restoreChat = patchOption( 'counterhand_chat', { provider: 'e2e-scripted', model: 'e2e-model', tool_groups: [ 'products', 'reports' ] } );
	} );

	test.afterAll( () => {
		wp( 'option', 'delete', 'counterhand_store_memory' );
		evaluate( `delete_metadata( 'user', 0, 'counterhand_saved_prompts', '', true );` );
		restoreChat();
		restoreSettings();
	} );

	test( 'a playbook starter shows its title and sends the playbook, with the person’s language', async ( { page } ) => {
		await page.goto( CHAT );
		await page.getByRole( 'button', { name: 'Sales review' } ).click();

		const log = page.locator( '#counterhand-chat-log' );
		await expect( log.locator( '.counterhand-msg--user' ) ).toHaveText( /Sales review/ );
		await expect( log ).toContainText( 'get_sales_report' );
		await expect( log ).toContainText( 'the locale code en_US' );
		await expect( log ).toContainText( 'over the last 7 days' );
	} );

	test( 'a playbook that needs a value it has no default for is not offered', async ( { page } ) => {
		await page.goto( CHAT );

		await expect( page.locator( '#counterhand-chat-starters' ) ).not.toContainText( 'Missing translations' );
	} );

	test( 'a reload keeps the conversation and its tokens, and clearing forgets both', async ( { page } ) => {
		await page.goto( CHAT );
		await ask( page, 'remember me' );
		await expect( page.locator( '#counterhand-chat-log' ) ).toContainText( 'E2E model says: remember me' );
		await expect( page.locator( '#counterhand-chat-status' ) ).toContainText( '10 tokens in, 5 out' );

		await page.reload();
		await expect( page.locator( '#counterhand-chat-log' ) ).toContainText( 'E2E model says: remember me' );
		await expect( page.locator( '#counterhand-chat-status' ) ).toContainText( '10 tokens in, 5 out' );

		await ask( page, 'and again' );
		await expect( page.locator( '#counterhand-chat-status' ) ).toContainText( '20 tokens in, 10 out' );

		await page.getByRole( 'button', { name: 'Clear conversation' } ).click();
		await page.reload();
		await expect( page.locator( '#counterhand-chat-log' ) ).not.toContainText( 'remember me' );
	} );

	test( 'a conversation kept for another user or another model is not shown', async ( { page } ) => {
		const planted = JSON.stringify( { history: [], entries: [ { kind: 'bubble', role: 'user', text: 'SOMEONE ELSE' } ], totals: { input: 1, output: 1 } } );
		await page.addInitScript( ( value ) => {
			window.sessionStorage.setItem( 'counterhand-chat:1:999:e2e-scripted', value );
			window.sessionStorage.setItem( 'counterhand-chat:1:1:anthropic', value );
		}, planted );

		await page.goto( CHAT );
		await expect( page.locator( '#counterhand-chat-log' ) ).not.toContainText( 'SOMEONE ELSE' );
	} );

	test( 'store notes reach the model, and only an administrator can change them', async ( { page, browser } ) => {
		await page.goto( CHAT );
		await page.locator( '.counterhand-store-memory summary' ).click();
		await page.locator( '#counterhand-store-memory' ).fill( 'Always mention E2E-NOTE-MARKER.' );
		await page.getByRole( 'button', { name: 'Save notes' } ).click();

		await ask( page, 'what do you know' );
		await expect( page.locator( '#counterhand-chat-log' ) ).toContainText( '[notes received]' );

		const manager = await browser.newPage( { storageState: shopManagerStatePath } );
		await manager.goto( CHAT );
		await manager.locator( '.counterhand-store-memory summary' ).click();
		await expect( manager.locator( '.counterhand-store-memory' ) ).toContainText( 'E2E-NOTE-MARKER' );
		await expect( manager.locator( '#counterhand-store-memory' ) ).toHaveCount( 0 );
		await manager.close();
	} );

	test( 'only an administrator chooses the model, its areas, and clears Activity', async ( { page, browser } ) => {
		const before = wp( 'option', 'get', 'counterhand_chat', '--format=json' );

		await page.goto( CHAT );
		await expect( page.locator( '.counterhand-chat__model a', { hasText: 'Change' } ) ).toBeVisible();

		const manager = await browser.newPage( { storageState: shopManagerStatePath } );
		await manager.goto( CHAT );
		await expect( manager.locator( '.counterhand-chat__model a', { hasText: 'Change' } ) ).toHaveCount( 0 );
		await expect( manager.locator( '.counterhand-chat-tools__form [type=submit]' ) ).toHaveCount( 0 );
		await expect( manager.locator( '.counterhand-chat-tools__fields' ) ).toHaveAttribute( 'disabled', /.*/ );
		await expect( manager.locator( '.counterhand-chat-tools__fields input[type=checkbox]' ).first() ).toBeDisabled();

		// Refused on capability, before any nonce is looked at.
		for ( const [ action, refusal ] of [ [ 'counterhand_save_chat', 'not allowed to change' ], [ 'counterhand_save_chat_tools', 'not allowed to change' ], [ 'counterhand_clear_log', 'not allowed to clear' ] ] ) {
			const response = await manager.request.post( '/wp-admin/admin-post.php', { form: { action, counterhand_chat_provider: 'openai', counterhand_chat_base_url: 'https://elsewhere.example/v1' } } );
			expect( await response.text(), action ).toContain( refusal );
		}

		await manager.goto( '/wp-admin/admin.php?page=counterhand-mcp-log' );
		await expect( manager.getByRole( 'button', { name: 'Clear activity' } ) ).toHaveCount( 0 );
		await manager.close();

		expect( wp( 'option', 'get', 'counterhand_chat', '--format=json' ) ).toBe( before );
	} );

	test( 'a saved question stays for that person and is sent as typed', async ( { page, browser } ) => {
		await page.goto( CHAT );
		await page.locator( '#counterhand-chat-input' ).fill( 'my usual question' );
		await page.getByRole( 'button', { name: 'Save question' } ).click();
		await expect( page.locator( '#counterhand-chat-saved' ) ).toContainText( 'my usual question' );

		await page.reload();
		await page.locator( '.counterhand-chat__saved-ask', { hasText: 'my usual question' } ).click();
		await expect( page.locator( '#counterhand-chat-log' ) ).toContainText( 'E2E model says: my usual question' );

		const manager = await browser.newPage( { storageState: shopManagerStatePath } );
		await manager.goto( CHAT );
		await expect( manager.locator( '#counterhand-chat-saved' ) ).not.toContainText( 'my usual question' );
		await manager.close();

		await page.getByRole( 'button', { name: 'Forget this question' } ).click();
		await expect( page.locator( '#counterhand-chat-saved' ) ).not.toContainText( 'my usual question' );
	} );

	test( 'a price change to approve shows the price now and after, and declining writes nothing', async ( { page } ) => {
		const product = Number( evaluate( `
			$product = new WC_Product_Simple();
			$product->set_name( 'E2E approval diff' );
			$product->set_regular_price( '10' );
			$product->set_status( 'publish' );
			echo $product->save();
		` ) );

		try {
			await page.goto( CHAT );
			await ask( page, `set product ${ product } to 15` );

			const diff = page.locator( '.counterhand-confirm__diff' );
			await expect( diff ).toBeVisible();
			const row = diff.locator( 'tr', { has: page.locator( 'th', { hasText: 'regular_price' } ) } );
			await expect( row.locator( 'td' ).nth( 0 ) ).toHaveText( '10' );
			await expect( row.locator( 'td' ).nth( 1 ) ).toHaveText( '15' );

			await page.locator( '.counterhand-confirm' ).getByRole( 'button', { name: 'Decline' } ).click();
			await expect( page.locator( '.counterhand-tool--declined' ) ).toBeVisible();
			expect( evaluate( `echo wc_get_product( ${ product } )->get_regular_price();` ) ).toBe( '10' );
		} finally {
			wp( 'post', 'delete', String( product ), '--force' );
		}
	} );
} );
