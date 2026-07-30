<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Provider;

use Counterhand\Shared\Exception\ToolCallException;

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

	/** The base URL used when the admin does not supply one; '' when none applies. */
	public function default_base_url(): string;

	/** Whether an API key is required; local models and core's client need none. */
	public function needs_key(): bool;

	/**
	 * Whether the admin connects this provider here — their own key, model and
	 * endpoint — as opposed to WordPress core managing the credential for them.
	 */
	public function is_user_configured(): bool;

	/** Whether the chat could run with this config. Runs per render — no network. */
	public function is_ready( ProviderConfig $config ): bool;

	/**
	 * Where the admin creates a key for this provider.
	 *
	 * Surfaced as a link next to the key field so nobody has to go hunting for
	 * the right console page. Empty when the provider has no such page.
	 */
	public function key_url(): string;

	/**
	 * Proves the credentials and model actually work.
	 *
	 * Called when the admin saves, so a bad key is reported there and then
	 * rather than surfacing as a failed first message.
	 *
	 * @throws ToolCallException With an admin-readable reason when it doesn't.
	 */
	public function test( ProviderConfig $config ): void;

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

	/**
	 * The most tool definitions one request should carry, or null for no ceiling.
	 *
	 * Not a wire limit — it is where the provider's own model stops picking
	 * reliably. A provider that can search a deferred catalogue answers null,
	 * because for it the ceiling has moved from the request to the search.
	 */
	public function max_eager_tools(): ?int;

	/**
	 * Rewrites a full tool set into a searchable catalogue, or returns it as-is.
	 *
	 * Asked of the provider rather than decided by the loop: whether a catalogue
	 * can be searched, and how one is spelled, is provider knowledge.
	 *
	 * @param  list<array<string,mixed>> $tools
	 * @return list<array<string,mixed>>
	 */
	public function with_tool_search( array $tools ): array;

	/** Builds the assistant turn to append after a tool-calling response. */
	public function assistant_message( ProviderTurn $turn ): array;

	/**
	 * Builds the message(s) carrying tool results back to the model.
	 *
	 * @param list<ToolResult> $results
	 * @return list<array<string,mixed>>
	 */
	public function tool_result_messages( array $results ): array;

	/** Builds a plain user message. */
	public function user_message( string $text ): array;
}
