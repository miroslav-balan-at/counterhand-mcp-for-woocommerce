<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Playground\Provider;

use Counterhand\Features\Playground\Provider\AnthropicProvider;
use Counterhand\Features\Playground\Provider\CoreAiClientProvider;
use Counterhand\Features\Playground\Provider\OpenAiCompatibleProvider;
use Counterhand\Features\Playground\Provider\ToolResult;
use Counterhand\Tests\Unit\TestCase;

/**
 * Pins the wire shapes the HTTP adapters build from the provider-neutral VOs,
 * so the shared HttpProvider base cannot drift them apart unnoticed.
 */
final class ProviderMessagesTest extends TestCase {

	public function test_user_message_shape_is_shared(): void {
		$expected = [
			'role'    => 'user',
			'content' => 'hello',
		];

		self::assertSame( $expected, ( new AnthropicProvider() )->user_message( 'hello' ) );
		self::assertSame( $expected, OpenAiCompatibleProvider::openai()->user_message( 'hello' ) );
	}

	public function test_anthropic_batches_all_results_into_one_user_message(): void {
		$messages = ( new AnthropicProvider() )->tool_result_messages(
			[
				new ToolResult( 'toolu_1', 'list_products', '{"ok":true}', false ),
				new ToolResult( 'toolu_2', 'get_order', '{"error":"missing"}', true ),
			]
		);

		self::assertSame(
			[
				[
					'role'    => 'user',
					'content' => [
						[
							'type'        => 'tool_result',
							'tool_use_id' => 'toolu_1',
							'content'     => '{"ok":true}',
							'is_error'    => false,
						],
						[
							'type'        => 'tool_result',
							'tool_use_id' => 'toolu_2',
							'content'     => '{"error":"missing"}',
							'is_error'    => true,
						],
					],
				],
			],
			$messages
		);
	}

	public function test_openai_sends_one_tool_message_per_result(): void {
		$messages = OpenAiCompatibleProvider::openai()->tool_result_messages(
			[
				new ToolResult( 'call_1', 'list_products', '{"ok":true}', false ),
				new ToolResult( 'call_2', 'get_order', '{"error":"missing"}', true ),
			]
		);

		self::assertSame(
			[
				[
					'role'         => 'tool',
					'tool_call_id' => 'call_1',
					'content'      => '{"ok":true}',
				],
				[
					'role'         => 'tool',
					'tool_call_id' => 'call_2',
					'content'      => '{"error":"missing"}',
				],
			],
			$messages
		);
	}

	public function test_only_the_core_client_is_core_managed(): void {
		self::assertFalse( ( new CoreAiClientProvider() )->is_user_configured() );
		self::assertTrue( ( new AnthropicProvider() )->is_user_configured() );
		self::assertTrue( OpenAiCompatibleProvider::ollama()->is_user_configured() );
	}
}
