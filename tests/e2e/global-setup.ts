import { chromium, type FullConfig } from '@playwright/test';
import { accounts, adminStatePath, installStore, shopManagerStatePath, type Account } from './support/store';

async function logIn( baseURL: string, account: Account, statePath: string ): Promise< void > {
	const browser = await chromium.launch();
	const context = await browser.newContext( { baseURL } );
	const page = await context.newPage();

	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( account.username );
	await page.locator( '#user_pass' ).fill( account.password );
	await page.locator( '#wp-submit' ).click();
	await page.waitForURL( '**/wp-admin/**' );

	await context.storageState( { path: statePath } );
	await browser.close();
}

export default async function globalSetup( config: FullConfig ): Promise< void > {
	// Every run starts from the same store state, and fresh passwords.
	installStore();

	const baseURL = String( config.projects[ 0 ].use.baseURL );
	const { admin, shopManager } = accounts();

	await logIn( baseURL, admin, adminStatePath );
	await logIn( baseURL, shopManager, shopManagerStatePath );
}
