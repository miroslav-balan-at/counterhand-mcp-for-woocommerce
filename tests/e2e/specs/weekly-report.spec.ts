import { writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test, type Page } from '@playwright/test';
import { patchOption, patchSettings, stateDirectory, wp } from '../support/store';

const DASHBOARD = '/wp-admin/admin.php?page=counterhand-mcp-dashboard';
const HOSTILE = '<img src=x onerror=alert(1)>E2E Report Soap';

type Mail = { to: string | string[]; subject: string; message: string };

const evaluate = ( code: string ) => {
	const script = join( stateDirectory, 'e2e-report-eval.php' );
	writeFileSync( script, `<?php ${ code }` );

	return wp( 'eval-file', script );
};

const mails = (): Mail[] => JSON.parse( wp( 'option', 'get', 'counterhand_e2e_mails', '--format=json' ) || '[]' );

const forget = ( login: string ) => evaluate( `delete_user_meta( get_user_by( 'login', '${ login }' )->ID, 'counterhand_weekly_report' );` );

const card = ( page: Page ) => page.locator( 'section[aria-labelledby="counterhand-dashboard-weekly-report"]' );

/* The weekly email as it would be sent: the figures WooCommerce states, for the people who asked. */
test.describe( 'Weekly report', () => {
	let restoreSettings: () => void;
	let restoreChat: () => void;
	let product: number;
	let order: number;
	let expectedTotal: string;

	test.beforeAll( () => {
		restoreSettings = patchSettings( { reports_read: true } );
		restoreChat = patchOption( 'counterhand_chat', { provider: 'e2e-scripted', model: 'e2e-model' } );
		wp( 'option', 'update', 'counterhand_e2e_capture_mail', '1' );

		[ product, order ] = evaluate( `
			$product = new WC_Product_Simple();
			$product->set_name( ${ JSON.stringify( HOSTILE ) } );
			$product->set_regular_price( '12.50' );
			$product->set_status( 'publish' );
			$product->save();

			$order = wc_create_order();
			$order->add_product( $product, 3 );
			$order->calculate_totals();
			$order->set_status( 'completed' );
			$order->set_date_created( ( new DateTimeImmutable( 'monday this week', wp_timezone() ) )->modify( '-5 days' )->setTime( 12, 0 )->getTimestamp() );
			$order->save();
			echo $product->get_id(), ',', $order->get_id();
		` ).split( ',' ).map( Number );

		// The figure the email must print is the one WooCommerce's own report gives for that week.
		expectedTotal = evaluate( `
			wp_set_current_user( get_user_by( 'login', 'e2e-admin' )->ID );
			$monday = ( new DateTimeImmutable( 'monday this week', wp_timezone() ) )->modify( '-7 days' );
			$request = new WP_REST_Request( 'GET', '/wc/v3/reports/sales' );
			$request->set_query_params( [ 'date_min' => $monday->format( 'Y-m-d' ), 'date_max' => $monday->modify( '+6 days' )->format( 'Y-m-d' ) ] );
			echo wp_strip_all_tags( html_entity_decode( wc_price( (float) rest_do_request( $request )->get_data()[0]['total_sales'] ) ) );
		` );
	} );

	test.beforeEach( () => {
		wp( 'option', 'update', 'counterhand_e2e_mails', '[]', '--format=json' );
	} );

	test.afterAll( () => {
		wp( 'post', 'delete', String( product ), '--force' );
		evaluate( `wc_get_order( ${ order } )->delete( true );` );
		forget( 'e2e-admin' );
		wp( 'option', 'delete', 'counterhand_e2e_capture_mail' );
		wp( 'option', 'delete', 'counterhand_e2e_mails' );
		restoreChat();
		restoreSettings();
	} );

	test( 'a person signs up, and the report they get prints the store’s own figures', async ( { page } ) => {
		await page.goto( DASHBOARD );
		await card( page ).getByRole( 'button', { name: 'Email me every Monday' } ).click();
		await expect( card( page ) ).toContainText( 'every Monday morning' );
		await expect( card( page ).locator( '.counterhand-stat' ) ).toHaveText( 'On' );

		await card( page ).getByRole( 'button', { name: 'Send last week’s report now' } ).click();
		await expect( card( page ) ).toContainText( 'on its way' );

		const sent = mails();
		expect( sent ).toHaveLength( 1 );
		expect( sent[ 0 ].to ).toContain( 'e2e-admin@example.test' );
		expect( sent[ 0 ].subject ).toContain( 'Weekly report for' );

		const html = sent[ 0 ].message;
		const text = html.replace( /<[^>]+>/g, '' ).replace( /&nbsp;|&#160;/g, ' ' );
		expect( html ).not.toContain( '<img src=x' );
		expect( html ).toContain( '&lt;img src=x onerror=alert(1)&gt;E2E Report Soap: 3 sold' );
		expect( html ).toContain( 'E2E summary: sales are up. Bold' );
		expect( html ).toContain( 'Written by AI from the figures below.' );
		expect( html.replace( /&nbsp;|&#160;/g, ' ' ).replace( /<[^>]+>/g, '' ) ).toContain( expectedTotal.replace( / /g, ' ' ) );
		expect( text ).toContain( 'orders processing' );
	} );

	test( 'the Monday run mails only the people who asked, and sets the next one', async ( { page } ) => {
		wp( 'user', 'meta', 'update', 'e2e-admin', 'counterhand_weekly_report', '1' );
		forget( 'e2e-shop-manager' );
		await page.goto( DASHBOARD );

		wp( 'cron', 'event', 'run', 'counterhand_weekly_report' );

		const recipients = mails().flatMap( ( mail ) => mail.to );
		expect( recipients ).toEqual( [ 'e2e-admin@example.test' ] );
		expect( wp( 'cron', 'event', 'list', '--hook=counterhand_weekly_report', '--format=count' ) ).toBe( '1' );
	} );

	test( 'without a model the figures still go out, with no AI text', async ( { page } ) => {
		const restore = patchOption( 'counterhand_chat', { provider: '', model: '' } );

		try {
			await page.goto( DASHBOARD );
			await card( page ).getByRole( 'button', { name: 'Send last week’s report now' } ).click();

			const html = mails()[ 0 ].message;
			expect( html ).toContain( 'E2E Report Soap: 3 sold' );
			expect( html ).not.toContain( 'Written by AI' );
		} finally {
			restore();
		}
	} );

	test( 'a store with reports switched off sends nothing', async ( { page } ) => {
		const restore = patchSettings( { reports_read: false } );

		try {
			await page.goto( DASHBOARD );
			await expect( card( page ).locator( '.counterhand-chip--warn' ) ).toContainText( 'Reports are switched off' );
			await expect( card( page ).getByRole( 'button', { name: 'Send last week’s report now' } ) ).toHaveCount( 0 );

			wp( 'user', 'meta', 'update', 'e2e-admin', 'counterhand_weekly_report', '1' );
			wp( 'cron', 'event', 'run', 'counterhand_weekly_report' );
			expect( mails() ).toHaveLength( 0 );
		} finally {
			restore();
		}
	} );
} );
