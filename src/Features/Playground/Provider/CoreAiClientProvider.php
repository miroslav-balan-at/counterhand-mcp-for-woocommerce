<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Provider;

use Counterhand\Shared\Exception\ToolCallException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress 7.0's built-in AI Client.
 *
 * Preferred over the direct HTTP adapters whenever core provides it, because
 * core owns the credential: keys live in the Connectors API, shared by every
 * plugin on the site, so this plugin never stores or transmits one of its own.
 *
 * Messages are carried as the SDK's own `Message::toArray()` shape. The chat
 * ships history to the browser and back between turns, so the provider's
 * message format has to survive a JSON round trip — `toArray()`/`fromArray()`
 * is exactly that, and it keeps function-call ids intact for free.
 *
 * Registration is gated on {@see self::is_available()}, so a site whose core
 * exposes a different surface falls back to the direct adapters instead of
 * fataling.
 */
final readonly class CoreAiClientProvider implements ProviderInterface {

	public const ID = 'wp_core';

	/**
	 * Whether core can serve this provider.
	 *
	 * Checks the entry point and every SDK type used below, because core
	 * bundles the SDK as an external library and could move or version it
	 * independently of the wrapper function.
	 */
	public static function is_available(): bool {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return false;
		}

		foreach (
			[
				Message::class,
				MessagePart::class,
				MessageRoleEnum::class,
				FunctionDeclaration::class,
				FunctionResponse::class,
			] as $required
		) {
			if ( ! class_exists( $required ) ) {
				return false;
			}
		}

		return true;
	}

	public function id(): string {
		return self::ID;
	}

	public function label(): string {
		return __( 'WordPress AI (built in)', 'counterhand-mcp-for-woocommerce' );
	}

	/**
	 * Empty on purpose: which models exist depends on the AI provider plugins
	 * the site has installed, so offering a fixed list here would promise
	 * models this site may not have. Model choice is a preference, not a
	 * requirement — see complete().
	 */
	public function default_models(): array {
		return [];
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

	/** Core owns the credential, so the bring-your-own-key chooser skips this one. */
	public function is_user_configured(): bool {
		return false;
	}

	// test() makes no API call here, so it doubles as the per-render check.
	public function is_ready( ProviderConfig $config ): bool {
		try {
			$this->test( $config );
		} catch ( \Throwable ) {
			return false;
		}

		return true;
	}

	/** Empty: the key is entered on our own chooser, into core's setting. */
	public function key_url(): string {
		return '';
	}

	/**
	 * No API call and no cost — core compares the request against the models
	 * the site's installed providers actually expose. A site running
	 * WordPress 7.0 with no AI provider plugin reports false here, which is the
	 * case core warns about explicitly.
	 */
	public function test( ProviderConfig $config ): void {
		if ( ! $this->builder( $config )->with_text( 'ping' )->is_supported_for_text_generation() ) {
			throw new ToolCallException(
				esc_html__( 'No installed AI provider can serve this request. Connect a provider under Settings → Connectors, or install one of the AI Provider plugins.', 'counterhand-mcp-for-woocommerce' )
			);
		}
	}

	public function complete( array $messages, array $tools, ProviderConfig $config ): ProviderTurn {
		if ( [] === $messages ) {
			throw new ToolCallException( esc_html__( 'Nothing to send to the model.', 'counterhand-mcp-for-woocommerce' ) );
		}

		// The last message is the turn being asked; everything before it is context.
		$current = Message::fromArray( (array) array_pop( $messages ) );
		$builder = $this->builder( $config );

		if ( [] !== $messages ) {
			$builder = $builder->with_history(
				...array_map(
					static fn ( array $message ): Message => Message::fromArray( $message ),
					array_values( $messages )
				)
			);
		}

		$builder = $builder->with_message_parts( ...$current->getParts() );

		if ( [] !== $tools ) {
			$builder = $builder->using_function_declarations(
				...array_map(
					static fn ( array $tool ): FunctionDeclaration => FunctionDeclaration::fromArray( $tool ),
					$tools
				)
			);
		}

		$result = $builder->generate_text_result();

		if ( is_wp_error( $result ) ) {
			throw new ToolCallException( esc_html( $result->get_error_message() ) );
		}

		return $this->to_turn( $result );
	}

	/** @param GenerativeAiResult $result */
	private function to_turn( object $result ): ProviderTurn {
		$message    = $result->toMessage();
		$text       = '';
		$tool_calls = [];

		foreach ( $message->getParts() as $part ) {
			// Reasoning parts ride the thought channel; they are not the answer.
			if ( ! $part->getChannel()->isContent() ) {
				continue;
			}

			$part_text = $part->getText();
			if ( null !== $part_text ) {
				$text .= $part_text;
			}

			$call = $part->getFunctionCall();
			if ( null === $call ) {
				continue;
			}

			$arguments = $call->getArgs();

			$tool_calls[] = new ToolCall(
				id: (string) ( $call->getId() ?? '' ),
				name: (string) ( $call->getName() ?? '' ),
				input: is_array( $arguments ) ? $arguments : [],
			);
		}

		$usage = $result->getTokenUsage();

		return new ProviderTurn(
			text: trim( $text ),
			tool_calls: $tool_calls,
			wants_tools: [] !== $tool_calls,
			raw: $this->replayable( $message )->toArray(),
			usage: new TokenUsage(
				input: $usage->getPromptTokens(),
				output: $usage->getCompletionTokens(),
			),
		);
	}

	/**
	 * The assistant turn as it can legally be sent back.
	 *
	 * Extended thinking returns reasoning parts that a model will only accept
	 * on a later turn when they still carry their signature, and MessagePart
	 * serialises that signature only when it has one. Replaying an unsigned
	 * reasoning part is rejected outright — Anthropic answers
	 * "messages.N.content.0.thinking.signature: Field required" — so those
	 * parts are dropped here. Signed ones are kept, because a tool-calling turn
	 * that used extended thinking needs them.
	 *
	 * @param Message $message
	 */
	private function replayable( object $message ): Message {
		$parts = array_values(
			array_filter(
				$message->getParts(),
				static fn ( MessagePart $part ): bool =>
					$part->getChannel()->isContent() || null !== $part->getThoughtSignature()
			)
		);

		return new Message( $message->getRole(), $parts );
	}

	/**
	 * Returned as an array rather than a FunctionDeclaration so the shape stays
	 * JSON-safe like every other provider's; complete() rehydrates it.
	 */
	/**
	 * The AI Client hides which provider answers, so this has to hold for the
	 * strictest of them. OpenAI and Gemini both reject more than 128 tools
	 * outright, and Google's own advice is to keep 10–20 active.
	 */
	public function max_eager_tools(): int {
		return 60;
	}

	/** No searchable catalogue through the AI Client; the set is sent as-is. */
	public function with_tool_search( array $tools ): array {
		return $tools;
	}

	public function describe_tool( string $name, string $description, array $input_schema ): array {
		return ( new FunctionDeclaration( $name, $description, [] === $input_schema ? null : $input_schema ) )->toArray();
	}

	public function assistant_message( ProviderTurn $turn ): array {
		// Already the model's own message array, so ids survive the round trip.
		return $turn->raw;
	}

	public function tool_result_messages( array $results ): array {
		$parts = [];

		foreach ( $results as $result ) {
			$decoded = json_decode( $result->output, true );
			$payload = null === $decoded ? $result->output : $decoded;

			// FunctionResponse has no error flag, so failures are labelled in
			// the payload — otherwise the model cannot tell them apart.
			if ( $result->is_error ) {
				$payload = [ 'error' => $payload ];
			}

			$parts[] = new MessagePart( new FunctionResponse( $result->id, $result->name, $payload ) );
		}

		return [ ( new Message( MessageRoleEnum::user(), $parts ) )->toArray() ];
	}

	public function user_message( string $text ): array {
		return ( new Message( MessageRoleEnum::user(), [ new MessagePart( $text ) ] ) )->toArray();
	}

	/**
	 * A configured prompt builder. The model is passed as a preference, not a
	 * requirement: core picks the first configured model that matches, so a
	 * store whose provider does not offer the named model still works.
	 *
	 * @return object The WP_AI_Client_Prompt_Builder for this request.
	 */
	private function builder( ProviderConfig $config ): object {
		$builder = wp_ai_client_prompt();

		if ( '' !== $config->system_prompt ) {
			$builder = $builder->using_system_instruction( $config->system_prompt );
		}

		if ( '' !== $config->model ) {
			$builder = $builder->using_model_preference( $config->model );
		}

		return $builder->using_max_tokens( $config->max_tokens );
	}
}
