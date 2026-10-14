import { expect, test } from '@playwright/test';
import { patchOption, patchSettings, wp } from '../support/store';

/*
 * Driven by the scripted model in mu-plugins/counterhand-e2e-fixtures.php, so
 * no key and no network are involved. The stored chat settings are restored
 * afterwards, real provider and key included.
 */
test.describe( 'Chat', () => {
	let restore: () => void;

	test.beforeAll( () => {
		restore = patchOption( 'counterhand_chat', { provider: 'e2e-scripted', model: 'e2e-model' } );
	} );

	test.afterAll( () => restore() );

	test( 'a question gets the model\'s answer', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=counterhand-mcp' );

		await page.locator( '#counterhand-chat-input' ).fill( 'hello store' );
		await page.locator( '#counterhand-chat-send' ).click();

		await expect( page.locator( '#counterhand-chat-log' ) ).toContainText( 'E2E model says: hello store' );
	} );

	test( 'a tool the model calls runs through the store and is shown as a card', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=counterhand-mcp' );

		await page.locator( '#counterhand-chat-input' ).fill( 'list products please' );
		await page.locator( '#counterhand-chat-send' ).click();

		const card = page.locator( '.counterhand-tool' ).filter( { has: page.locator( '.counterhand-tool__name', { hasText: 'list_products' } ) } );
		await expect( card ).toBeVisible();
		await expect( card ).not.toHaveClass( /counterhand-tool--error/ );
		await expect( page.locator( '#counterhand-chat-log' ) ).toContainText( 'E2E model read the tool result.' );

		await card.locator( 'summary' ).click();
		await expect( card.locator( '.counterhand-tool__code' ).last() ).toContainText( 'E2E Mug' );
	} );

	test( 'a confirmation-gated call parks for approval, and declining runs nothing', async ( { page } ) => {
		const restoreSettings = patchSettings( { settings_read: true, settings_write: true } );
		const restoreChat = patchOption( 'counterhand_chat', { tool_groups: [ 'products', 'settings' ] } );
		try {
			const before = wp( 'option', 'get', 'woocommerce_store_address' );
			await page.goto( '/wp-admin/admin.php?page=counterhand-mcp' );

			await page.locator( '#counterhand-chat-input' ).fill( 'please change the store address' );
			await page.locator( '#counterhand-chat-send' ).click();

			const approval = page.locator( '.counterhand-confirm' );
			await expect( approval ).toContainText( 'Approve this change?' );
			await expect( approval.locator( '.counterhand-tool__name' ) ).toHaveText( 'update_setting' );
			await approval.getByRole( 'button', { name: 'Decline' } ).click();

			await expect( page.locator( '.counterhand-tool--declined' ) ).toBeVisible();
			expect( wp( 'option', 'get', 'woocommerce_store_address' ), 'nothing was written' ).toBe( before );
		} finally {
			restoreChat();
			restoreSettings();
		}
	} );
} );
