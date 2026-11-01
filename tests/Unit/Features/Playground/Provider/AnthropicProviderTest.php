<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Playground\Provider;

use Brain\Monkey\Functions;
use Counterhand\Features\Playground\Provider\AnthropicProvider;
use Counterhand\Features\Playground\Provider\ProviderConfig;
use Counterhand\Features\Playground\Provider\ProviderTurn;
use Counterhand\Tests\Unit\TestCase;

final class AnthropicProviderTest extends TestCase {

	/** @var array<string, mixed> */
	private array $sent = [];

	/** @param array<string, mixed> $answer */
	private function complete( array $answer ): ProviderTurn {
		$body = (string) json_encode( $answer );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $body );
		Functions\when( 'wp_remote_post' )->alias(
			function ( string $url, array $arguments ): array {
				$this->sent = json_decode( $arguments['body'], true );
				return [];
			}
		);

		return ( new AnthropicProvider() )->complete(
			[],
			[],
			new ProviderConfig( api_key: 'key', model: 'claude-opus-5-5', base_url: '', system_prompt: 'Be brief.' )
		);
	}

	public function test_a_request_asks_for_caching_and_usage_counts_what_the_cache_served(): void {
		$turn = $this->complete(
			[
				'content'     => [
					[
						'type' => 'text',
						'text' => 'Hello',
					],
				],
				'stop_reason' => 'end_turn',
				'usage'       => [
					'input_tokens'                => 10,
					'cache_read_input_tokens'     => 100,
					'cache_creation_input_tokens' => 1000,
					'output_tokens'               => 5,
				],
			]
		);

		self::assertSame( [ 'type' => 'ephemeral' ], $this->sent['cache_control'] );
		self::assertSame( 1110, $turn->usage->input );
		self::assertFalse( $turn->paused );
	}

	public function test_a_paused_turn_says_so(): void {
		self::assertTrue(
			$this->complete(
				[
					'content'     => [],
					'stop_reason' => 'pause_turn',
				]
			)->paused
		);
	}

	public function test_a_refusal_is_said_rather_than_left_blank(): void {
		$turn = $this->complete(
			[
				'content'     => [],
				'stop_reason' => 'refusal',
			]
		);

		self::assertSame( 'The model declined to answer this.', $turn->text );
	}
}
