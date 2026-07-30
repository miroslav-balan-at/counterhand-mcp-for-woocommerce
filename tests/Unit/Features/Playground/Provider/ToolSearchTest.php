<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Playground\Provider;

use Counterhand\Features\Playground\Provider\AnthropicProvider;
use Counterhand\Features\Playground\Provider\CoreAiClientProvider;
use Counterhand\Features\Playground\Provider\OpenAiCompatibleProvider;
use Counterhand\Tests\Unit\TestCase;

/**
 * How a large tool surface reaches each provider.
 *
 * Selection accuracy falls off past roughly 30–50 eagerly-loaded tools, and the
 * providers differ in what can be done about it: Anthropic defers the tail into
 * a searchable catalogue, the others have no such affordance and must be given
 * a smaller surface instead. Getting this wrong is either a refused chat or a
 * model quietly picking the wrong tool.
 */
final class ToolSearchTest extends TestCase {

	/** @return list<array<string, mixed>> */
	private function tools( int $count ): array {
		$tools = [];

		for ( $index = 0; $index < $count; $index++ ) {
			$tools[] = [
				'name'         => 'tool_' . $index,
				'description'  => 'Tool number ' . $index,
				'input_schema' => [ 'type' => 'object' ],
			];
		}

		return $tools;
	}

	public function test_anthropic_declines_to_name_a_ceiling(): void {
		self::assertNull(
			( new AnthropicProvider() )->max_eager_tools(),
			'Deferred loading moves the ceiling from the request to the search.'
		);
	}

	public function test_a_small_tool_set_is_left_alone(): void {
		$tools = $this->tools( 4 );

		self::assertSame( $tools, ( new AnthropicProvider() )->with_tool_search( $tools ) );
	}

	public function test_a_large_tool_set_gains_a_search_tool_first(): void {
		$catalogue = ( new AnthropicProvider() )->with_tool_search( $this->tools( 40 ) );

		self::assertSame( 'tool_search_tool_bm25_20251119', $catalogue[0]['type'] );
		self::assertCount( 41, $catalogue, 'Every tool is still sent; only loading is deferred.' );
	}

	/** The API rejects a request in which every tool is deferred. */
	public function test_the_first_few_tools_stay_eager(): void {
		$catalogue = ( new AnthropicProvider() )->with_tool_search( $this->tools( 40 ) );
		$eager     = array_filter(
			array_slice( $catalogue, 1 ),
			static fn ( array $tool ): bool => ! ( $tool['defer_loading'] ?? false )
		);

		self::assertNotSame( [], $eager );
		self::assertCount( 5, $eager );
	}

	public function test_the_tail_is_deferred(): void {
		$catalogue = ( new AnthropicProvider() )->with_tool_search( $this->tools( 40 ) );

		self::assertTrue( end( $catalogue )['defer_loading'] );
	}

	/** The search tool itself must never be deferred. */
	public function test_the_search_tool_is_never_deferred(): void {
		$catalogue = ( new AnthropicProvider() )->with_tool_search( $this->tools( 40 ) );

		self::assertArrayNotHasKey( 'defer_loading', $catalogue[0] );
	}

	public function test_providers_without_deferred_loading_keep_a_real_ceiling(): void {
		self::assertSame( 60, OpenAiCompatibleProvider::openai()->max_eager_tools() );
		self::assertSame( 60, ( new CoreAiClientProvider() )->max_eager_tools() );
	}

	public function test_providers_without_deferred_loading_send_the_set_unchanged(): void {
		$tools = $this->tools( 80 );

		self::assertSame( $tools, OpenAiCompatibleProvider::openai()->with_tool_search( $tools ) );
		self::assertSame( $tools, ( new CoreAiClientProvider() )->with_tool_search( $tools ) );
	}
}
