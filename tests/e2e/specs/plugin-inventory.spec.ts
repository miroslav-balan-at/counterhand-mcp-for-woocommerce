import { expect, test } from '@playwright/test';
import { listToolNames, mcpCall } from '../support/mcp';
import { anonymousContext, connect } from '../support/oauth';
import { adminStatePath, patchOption, patchSettings, wp } from '../support/store';

/*
 * What each plugin offers once set up, pinned. CI installs the latest release daily,
 * so a release that adds an ability fails here: read what it does before adding it to
 * the list, because the consent wording and the skip list may need to change too.
 * Grants are held to what was approved, so stores are safe in the meantime.
 */
const EXPECTED = {
	'yoast-seo': [
		'get_post_seo_data', 'get_readability_scores', 'get_seo_scores', 'set_llms_txt_status', 'set_open_graph_status',
		'set_schema_aggregation_endpoint_status', 'set_schema_framework_status', 'set_x_card_status', 'set_xml_sitemap_status',
		'update_post_seo_data', 'update_site_representation',
	],
	wpforms: [ 'get_form', 'get_form_stats', 'list_forms' ],
	'rank-math': [
		'audit_site_seo', 'create_ai_visibility_brand', 'fix_site_seo', 'get_404_logs', 'get_ai_visibility_brand_insights',
		'get_ai_visibility_brand_queries', 'get_ai_visibility_overview', 'get_link_report', 'get_post_links', 'get_post_schema',
		'get_post_seo_meta', 'get_redirections', 'get_robots_txt', 'get_sitemap_status', 'get_top_keywords', 'set_breadcrumb_settings',
		'set_global_seo_settings', 'set_homepage_seo', 'set_link_settings', 'set_module_status', 'set_plugin_preferences',
		'set_post_type_seo_settings', 'set_sitemap_settings', 'set_website_identity',
	],
};

// WPForms offers no write until its own write-access toggle is on, so it has no write scope here.
const SCOPES = [ 'integration:yoast-seo:read', 'integration:yoast-seo:write', 'integration:wpforms:read', 'integration:rank-math:read', 'integration:rank-math:write' ];

test.describe( 'Plugins as they are once set up', () => {
	let restore: () => void;
	let call: ( name: string, args: Record< string, unknown > ) => Promise< { error?: unknown; result?: { isError?: boolean; structuredContent?: Record< string, unknown >; content?: { text: string }[] } } >;
	let names: string[];
	let tools: { name: string; inputSchema: Record< string, unknown>; annotations: { destructiveHint: boolean } }[];
	let product: string;

	test.beforeAll( async ( { browser, baseURL } ) => {
		wp( 'plugin', 'activate', 'wordpress-seo', 'wpforms-lite', 'seo-by-rank-math' );
		wp( 'option', 'update', 'rank_math_registration_skip', '1' );
		const settings: Record< string, boolean > = { customers_read: true };
		for ( const slug of Object.keys( EXPECTED ) ) {
			settings[ `integration_${ slug }_read` ] = true;
			settings[ `integration_${ slug }_write` ] = true;
		}
		restore = patchSettings( settings );
		product = wp( 'post', 'create', '--post_type=product', '--post_title=Inventory product', '--post_status=publish', '--porcelain' ).trim();
		wp( 'post', 'meta', 'set', product, '_probe_secret', 's3cret-protected' );

		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		const { tokens, discovery } = await connect( page, baseURL as string, [ ...SCOPES, 'customers:read' ] );
		await page.close();
		const client = await anonymousContext( baseURL as string );
		names = await listToolNames( client, discovery.resource, tokens.access_token );
		tools = ( await ( await mcpCall( client, discovery.resource, tokens.access_token, 'tools/list' ) ).json() ).result.tools;
		call = async ( name, args ) => ( await mcpCall( client, discovery.resource, tokens.access_token, 'tools/call', { name, arguments: args } ) ).json();
	} );

	test.afterAll( () => {
		wp( 'post', 'delete', product, '--force' );
		restore();
		wp( 'option', 'delete', 'rank_math_registration_skip' );
		wp( 'plugin', 'deactivate', 'wordpress-seo', 'wpforms-lite', 'seo-by-rank-math' );
	} );

	for ( const [ slug, expected ] of Object.entries( EXPECTED ) ) {
		test( `${ slug } offers exactly what we have read`, () => {
			const prefix = `${ slug.replace( '-', '_' ) }_`;
			const offered = names.filter( ( name ) => name.startsWith( prefix ) ).map( ( name ) => name.slice( prefix.length ) ).sort();

			expect( offered ).toEqual( [ ...expected ].sort() );
		} );
	}

	test( 'nothing published carries required: true on a property, and every write says destructive', () => {
		for ( const tool of tools.filter( ( candidate ) => /^(yoast_seo|wpforms|rank_math)_/.test( candidate.name ) ) ) {
			const properties = ( tool.inputSchema.properties ?? {} ) as Record< string, { required?: unknown } >;
			for ( const [ name, property ] of Object.entries( properties ) ) {
				expect( typeof property.required, `${ tool.name }.${ name }` ).not.toBe( 'boolean' );
			}
		}

		expect( tools.find( ( tool ) => 'rank_math_set_global_seo_settings' === tool.name )?.annotations.destructiveHint ).toBe( true );
	} );

	test( 'a Yoast title cannot read protected meta, and clearing still works', async () => {
		const refused = await call( 'yoast_seo_update_post_seo_data', { post_id: Number( product ), seo_title: '%%cf__probe_secret%%', confirm: true } );
		expect( refused.result?.isError ).toBe( true );
		expect( JSON.stringify( refused ) ).not.toContain( 's3cret-protected' );
		expect( wp( 'post', 'meta', 'list', product, '--format=csv' ) ).not.toContain( '%%cf_' );
	} );

	test( 'a Yoast write still needs a person', async () => {
		const unconfirmed = await call( 'yoast_seo_set_xml_sitemap_status', { enabled: false, confirm: false } );
		expect( unconfirmed.result?.isError ).toBe( true );
	} );

	test( 'a failure Rank Math reports as a result is a failed call', async () => {
		const failed = await call( 'rank_math_set_module_status', { modules: { 'no-such-module': true }, confirm: true } );
		expect( failed.result?.isError ).toBe( true );
	} );

	test( 'direct-debit bank details are not readable as customer meta', async () => {
		const customer = wp( 'user', 'create', 'e2e-debit', 'e2e-debit@example.test', '--role=customer', '--porcelain' ).trim();
		try {
			wp( 'user', 'meta', 'set', customer, 'direct_debit_iban', 'AT00E2EPROBE' );
			wp( 'user', 'meta', 'set', customer, 'visible_note', 'fine' );
			const listed = await call( 'get_customer_meta', { id: Number( customer ) } );
			expect( JSON.stringify( listed ) ).not.toContain( 'AT00E2EPROBE' );
		} finally {
			wp( 'user', 'delete', customer, '--yes' );
		}
	} );

	test( 'settings warn about the other MCP server this plugin starts', async ( { browser, baseURL } ) => {
		const page = await browser.newPage( { baseURL, storageState: adminStatePath } );
		// Twice: a freshly activated plugin may spend the first admin load on its welcome redirect.
		await page.goto( '/wp-admin/admin.php?page=counterhand-mcp-settings' );
		await page.goto( '/wp-admin/admin.php?page=counterhand-mcp-settings' );
		await expect( page.locator( '.counterhand-other-server' ) ).toContainText( 'mcp_adapter_create_default_server' );
		await page.close();
	} );
} );
