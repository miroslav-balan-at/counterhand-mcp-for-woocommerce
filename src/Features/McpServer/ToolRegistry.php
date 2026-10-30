<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

use Counterhand\Features\Settings\PluginSettings;
use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Features\Tokens\Domain\ScopeInterface;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Holds every wired tool and filters visibility per request.
 *
 * The SAME filtered set is used for tools/list and tools/call: a tool that
 * is toggled off or out of scope does not exist for the caller (fail-closed
 * at both ends, enforced at call time — not just hidden from the list).
 *
 * Three gates, all of which must agree, and each answering a different
 * question: the store's settings say whether this deployment offers the tool
 * at all, the token's scopes say whether this client was granted it, and the
 * tool itself says whether WordPress would let the token owner use it.
 */
final class ToolRegistry {

	/** @var array<string, ToolInterface> */
	private array $tools = [];

	/** @var array<string, array<string, ToolInterface>> Memo key => visible tools. */
	private array $visible = [];

	/** @var list<callable(): iterable<ToolInterface>> */
	private array $late_sources = [];

	public function __construct( private readonly PluginSettings $settings ) {}

	/**
	 * @throws \LogicException When two tools claim the same name.
	 */
	public function add( ToolInterface $tool ): void {
		$name = $tool->name();

		// Silently overwriting would leave the shadowed tool's scope and group
		// still advertised in the settings screen while a different class
		// answered the calls. Names are the only identity MCP has.
		if ( isset( $this->tools[ $name ] ) ) {
			throw new \LogicException(
				sprintf(
					'Two tools are registered as "%s": %s and %s.',
					esc_html( $name ),
					esc_html( $this->tools[ $name ]::class ),
					esc_html( $tool::class )
				)
			);
		}

		$this->tools[ $name ] = $tool;
		$this->visible        = [];
	}

	/**
	 * Tools that cannot be known at boot and are not worth knowing on every page.
	 *
	 * Asked once, the first time anything reads the surface. A name already taken
	 * is skipped rather than thrown on, unlike add(): these names come from other
	 * plugins, and a clash must not take the whole store's endpoint down with it.
	 *
	 * @param callable(): iterable<ToolInterface> $source
	 */
	public function add_late_source( callable $source ): void {
		$this->late_sources[] = $source;
	}

	/** @return list<ToolInterface> */
	public function visible_for( AuthenticatedAgent $agent ): array {
		return array_values( $this->visible_map_for( $agent ) );
	}

	public function resolve_for( AuthenticatedAgent $agent, string $tool_name ): ?ToolInterface {
		return $this->visible_map_for( $agent )[ $tool_name ] ?? null;
	}

	/**
	 * The whole registered surface per group, ungated on purpose: this is the
	 * denominator an admin screen needs to say what a setting is withholding.
	 *
	 * @return array<string, int>
	 */
	public function tool_counts_by_group(): array {
		$this->resolve_late_sources();

		$counts = [];

		foreach ( $this->tools as $tool ) {
			$slug            = $tool->group()->key();
			$counts[ $slug ] = ( $counts[ $slug ] ?? 0 ) + 1;
		}

		return $counts;
	}

	/**
	 * The one place the gates are evaluated, so tools/list and tools/call cannot
	 * drift apart, and so a tools/call costs no capability probes of its own.
	 *
	 * @return array<string, ToolInterface>
	 */
	private function visible_map_for( AuthenticatedAgent $agent ): array {
		$this->resolve_late_sources();

		return $this->visible[ $this->memo_key( $agent ) ] ??= array_filter(
			$this->tools,
			fn ( ToolInterface $tool ): bool =>
				$this->is_group_enabled( $tool )
				&& $agent->scopes()->contains( $tool->required_scope() )
				&& $tool->is_available()
		);
	}

	private function resolve_late_sources(): void {
		$sources            = $this->late_sources;
		$this->late_sources = [];

		foreach ( $sources as $source ) {
			foreach ( $source() as $tool ) {
				if ( ! isset( $this->tools[ $tool->name() ] ) ) {
					$this->add( $tool );
				}
			}
		}
	}

	/**
	 * What the answer depends on. Availability is a function of the WordPress
	 * user, which follows the token owner, so two tokens of the same owner with
	 * the same scopes genuinely see the same surface.
	 */
	private function memo_key( AuthenticatedAgent $agent ): string {
		$scopes = array_map( static fn ( ScopeInterface $scope ): string => $scope->key(), $agent->scopes()->all() );
		sort( $scopes );

		return $agent->token->owner_user_id . '|' . implode( ',', $scopes );
	}

	private function is_group_enabled( ToolInterface $tool ): bool {
		return $tool->required_scope()->is_write()
			? $this->settings->is_group_write_enabled( $tool->group() )
			: $this->settings->is_group_read_enabled( $tool->group() );
	}
}
