<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\McpApps;

use Counterhand\Features\McpApps\AppViewCatalog;
use Counterhand\Tests\Unit\TestCase;

/**
 * Tool names are public contracts, and a view for a renamed one would quietly vanish.
 */
final class AppViewCatalogTest extends TestCase {

	public function test_each_view_belongs_to_a_tool_and_ships_its_script(): void {
		$tools = [
			'get_sales_report' => 'sales-report',
			'get_order'        => 'order',
			'update_product'   => 'change-preview',
		];

		$catalog = new AppViewCatalog( $this->createStub( \Counterhand\Features\McpServer\ToolDispatcherInterface::class ) );

		foreach ( $tools as $tool => $view ) {
			self::assertSame( 'ui://counterhand/' . $view, $catalog->uri_for_tool( $tool ) );
			self::assertFileExists( dirname( __DIR__, 4 ) . '/assets/mcp-apps/' . $view . '.js' );
		}

		self::assertNull( $catalog->uri_for_tool( 'get_top_sellers' ) );
	}

	public function test_view_scripts_write_text_only(): void {
		foreach ( (array) glob( dirname( __DIR__, 4 ) . '/assets/mcp-apps/*.js' ) as $script ) {
			self::assertDoesNotMatchRegularExpression( '/innerHTML|outerHTML|insertAdjacentHTML|document\.write|eval\(/', (string) file_get_contents( (string) $script ), (string) $script );
		}
	}
}
