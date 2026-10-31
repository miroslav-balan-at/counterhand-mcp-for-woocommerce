<?php
/**
 * Plugin Name: Counterhand e2e fixtures
 * Description: Test-only. Mapped into the wp-env e2e store by .wp-env.e2e.json; never shipped.
 */

declare( strict_types=1 );

use Counterhand\Features\Playground\Provider\ProviderConfig;
use Counterhand\Features\Playground\Provider\ProviderInterface;
use Counterhand\Features\Playground\Provider\ProviderTurn;
use Counterhand\Features\Playground\Provider\ToolCall;

defined( 'ABSPATH' ) || exit;

const COUNTERHAND_E2E_REDIRECT_URI = 'http://127.0.0.1:47321/callback';

/*
 * The consent flow resolves the client by fetching its CIMD document. A real
 * client would publish one on its own HTTPS host; here the document is answered
 * in-process, because wp_safe_remote_get() rightly refuses every host the test
 * runner could serve it from. pre_http_request fires before that URL check.
 *
 * Any subdomain of client.example.test answers, so a spec can give its
 * connection a name no other spec shares.
 */
add_filter(
	'pre_http_request',
	static function ( $preempt, array $arguments, string $url ) {
		if ( 1 !== preg_match( '#^https://([a-z0-9-]+\\.)?client\\.example\\.test/oauth/client-metadata\\.json$#', $url ) ) {
			return $preempt;
		}

		return [
			'headers'  => [ 'content-type' => 'application/json' ],
			'body'     => wp_json_encode(
				[
					'client_id'     => $url,
					'client_name'   => 'E2E Test Client',
					'redirect_uris' => [ COUNTERHAND_E2E_REDIRECT_URI ],
					'grant_types'   => [ 'authorization_code', 'refresh_token' ],
				]
			),
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		];
	},
	10,
	3
);

/*
 * A model that answers from a script, so the chat can be driven without a key
 * or a network. Declared inside the callback because mu-plugins load before
 * the plugin that defines ProviderInterface.
 */
add_filter(
	'counterhand_chat_providers',
	static function ( array $providers ): array {
		$providers[] = new class() implements ProviderInterface {

			public function id(): string {
				return 'e2e-scripted';
			}

			public function label(): string {
				return 'E2E scripted model';
			}

			public function default_models(): array {
				return [ 'e2e-model' ];
			}

			public function needs_base_url(): bool {
				return false;
			}

			public function default_base_url(): string {
				return '';
			}

			public function needs_key(): bool {
				return false;
			}

			public function is_user_configured(): bool {
				return true;
			}

			public function is_ready( ProviderConfig $config ): bool {
				return true;
			}

			public function key_url(): string {
				return '';
			}

			public function test( ProviderConfig $config ): void {}

			/** Asks for a product list, or a confirmation-gated settings change, when told to. */
			public function complete( array $messages, array $tools, ProviderConfig $config ): ProviderTurn {
				$last = end( $messages );

				if ( 'tool' === ( $last['role'] ?? '' ) ) {
					return new ProviderTurn( 'E2E model read the tool result.', [], false );
				}

				$text = (string) ( $last['text'] ?? '' );
				if ( str_contains( $text, 'list products' ) ) {
					return new ProviderTurn( '', [ new ToolCall( 'e2e-call-1', 'list_products', [ 'per_page' => 1 ] ) ], true );
				}

				if ( str_contains( $text, 'change the store address' ) ) {
					return new ProviderTurn(
						'',
						[
							new ToolCall(
								'e2e-call-2',
								'update_setting',
								[
									'group_id' => 'general',
									'id'       => 'woocommerce_store_address',
									'value'    => 'E2E Street 1',
								]
							),
						],
						true
					);
				}

				return new ProviderTurn( 'E2E model says: ' . $text, [], false );
			}

			public function describe_tool( string $name, string $description, array $input_schema ): array {
				return [ 'name' => $name ];
			}

			public function max_eager_tools(): ?int {
				return null;
			}

			public function with_tool_search( array $tools ): array {
				return $tools;
			}

			public function assistant_message( ProviderTurn $turn ): array {
				return [
					'role'  => 'assistant',
					'text'  => $turn->text,
					'calls' => array_map( static fn ( ToolCall $call ): string => $call->id, $turn->tool_calls ),
				];
			}

			public function tool_result_messages( array $results ): array {
				return array_map(
					static fn ( $result ): array => [
						'role'     => 'tool',
						'id'       => $result->id,
						'is_error' => $result->is_error,
					],
					$results
				);
			}

			public function user_message( string $text ): array {
				return [
					'role' => 'user',
					'text' => $text,
				];
			}
		};

		return $providers;
	}
);

// Yoast registers most abilities only in production mode, and this store is not.
add_filter( 'yoast_seo_development_mode', '__return_true' );

// ACF's abilities are off by default, and a spec needs to see them refused when they are on.
add_filter( 'acf/settings/enable_acf_ai', static fn ( mixed $enabled ): mixed => get_option( 'counterhand_e2e_acf_ai' ) ? true : $enabled );

/*
 * Abilities for the bridge to find, one per shape a real plugin can declare.
 * Fixtures rather than Yoast or WPForms, whose admin notices and scripts would
 * trip the screen checks in admin-ui.spec.ts.
 */
add_action(
	'wp_abilities_api_categories_init',
	static function (): void {
		wp_register_ability_category(
			'counterhand-e2e',
			[
				'label'       => 'E2E',
				'description' => 'Test-only abilities.',
			]
		);
	}
);

add_action(
	'wp_abilities_api_init',
	static function (): void {
		$read_only = [
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		];
		$define    = static function ( string $name, array $meta, callable $callback, array $extra = [] ): void {
			wp_register_ability(
				$name,
				[
					'label'               => $name,
					'description'         => 'E2E fixture: ' . $name,
					'category'            => 'counterhand-e2e',
					'execute_callback'    => $callback,
					'permission_callback' => static fn (): bool => current_user_can( 'manage_options' ),
					'meta'                => $meta,
					...$extra,
				]
			);
		};

		$define(
			'e2e-fixture/list-notes',
			[
				'annotations' => $read_only,
				'mcp'         => [ 'public' => true ],
			],
			static fn (): array => [ 'first', 'second' ]
		);
		$define(
			'e2e-fixture/shout',
			[
				'annotations' => $read_only,
				'public'      => true,
			],
			static fn ( string $input ): string => strtoupper( $input ),
			[ 'input_schema' => [ 'type' => 'string' ] ]
		);
		$define(
			'e2e-fixture/rewrite-note',
			[
				// Null, like Yoast's update: unknown has to read as destructive.
				'annotations' => [
					'readonly'    => false,
					'destructive' => null,
					'idempotent'  => false,
				],
				'mcp'         => [ 'public' => true ],
			],
			static function ( array $input ): array {
				update_option( 'counterhand_e2e_note', $input['note'] );

				return [ 'saved' => $input['note'] ];
			},
			[
				'input_schema' => [
					'type'                 => 'object',
					'properties'           => [ 'note' => [ 'type' => 'string' ] ],
					'required'             => [ 'note' ],
					'additionalProperties' => false,
				],
			]
		);
		// Stands in for what a plugin update adds, so a spec can check it stays out of old grants.
		if ( get_option( 'counterhand_e2e_late_ability' ) ) {
			$define(
				'e2e-fixture/arrived-later',
				[
					'annotations' => $read_only,
					'mcp'         => [ 'public' => true ],
				],
				static fn (): array => [ 'late' => true ]
			);
		}

		$define( 'e2e-fixture/not-exposed', [ 'annotations' => $read_only ], static fn (): array => [ 'secret' => true ] );
		$define(
			'e2e-other/ping',
			[
				'annotations' => $read_only,
				'mcp'         => [ 'public' => true ],
			],
			static fn (): array => [ 'pong' => true ]
		);
	}
);
