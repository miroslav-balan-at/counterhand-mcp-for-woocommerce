<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Playground\Provider;

use AgentGateMcp\Shared\Exception\ToolCallException;

defined( 'ABSPATH' ) || exit;

/**
 * One turn of an LLM conversation, normalised across providers.
 *
 * Each adapter translates our provider-neutral message/tool shapes into its
 * own wire format and back, so the agentic loop never sees vendor specifics.
 */
interface ProviderInterface {

	public function id(): string;

	public function label(): string;

	/** Models we offer in the picker; the user may still type their own. */
	public function default_models(): array;

	/** Whether this provider needs a base URL (OpenAI-compatible endpoints do). */
	public function needs_base_url(): bool;

	/**
	 * Runs one completion.
	 *
	 * @param list<array<string,mixed>> $messages Conversation in this provider's own format.
	 * @param list<array<string,mixed>> $tools    Tool definitions in provider format.
	 * @throws ToolCallException On transport or API error, with an admin-readable message.
	 */
	public function complete( array $messages, array $tools, ProviderConfig $config ): ProviderTurn;

	/** Translates one MCP tool into this provider's tool-definition shape. */
	public function describe_tool( string $name, string $description, array $input_schema ): array;

	/** Builds the assistant turn to append after a tool-calling response. */
	public function assistant_message( ProviderTurn $turn ): array;

	/**
	 * Builds the message(s) carrying tool results back to the model.
	 *
	 * @param list<array{id: string, name: string, output: string, is_error: bool}> $results
	 * @return list<array<string,mixed>>
	 */
	public function tool_result_messages( array $results ): array;

	/** Builds a plain user message. */
	public function user_message( string $text ): array;
}
