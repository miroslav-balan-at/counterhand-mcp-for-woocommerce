<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

use Counterhand\Features\Settings\PluginSettings;
use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;
use Counterhand\Features\Tokens\Domain\GrantedScopeSet;
use Counterhand\Features\Tokens\Domain\ScopeInterface;
use Counterhand\Shared\Tool\ToolInterface;
use Counterhand\Shared\Tool\ToolSection;

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
final class ToolRegistry implements ToolRegistrarInterface, IntegrationToolCatalogInterface {

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
	 * Asked once, the first time anything needs a tool it does not already hold. A
	 * name already taken is skipped and a source that throws is logged, unlike add():
	 * these come from other plugins, which must not take the store's endpoint down.
	 *
	 * @param callable(): iterable<ToolInterface> $source
	 */
	public function add_late_source( callable $source ): void {
		$this->late_sources[] = $source;
	}

	public function integration_tools_by_scope( GrantedScopeSet $scopes ): array {
		$this->resolve_late_sources();

		$names = [];

		foreach ( $this->tools as $tool ) {
			if (
				ToolSection::Integrations === $tool->group()->section()
				&& $scopes->contains( $tool->required_scope() )
				&& $this->is_group_enabled( $tool )
				&& $tool->is_available()
			) {
				$names[ $tool->required_scope()->key() ][] = $tool->name();
			}
		}

		return $names;
	}

	/** @return list<ToolInterface> */
	public function visible_for( AuthenticatedAgent $agent ): array {
		return array_values( $this->visible_map_for( $agent ) );
	}

	/**
	 * Gates only the named tool, so calling a WooCommerce tool runs no other
	 * plugin's discovery or permission callback.
	 */
	public function resolve_for( AuthenticatedAgent $agent, string $tool_name ): ?ToolInterface {
		if ( ! isset( $this->tools[ $tool_name ] ) ) {
			$this->resolve_late_sources();
		}

		$tool = $this->tools[ $tool_name ] ?? null;
		if ( null === $tool ) {
			return null;
		}

		$known = $this->visible[ $this->memo_key( $agent ) ] ?? null;
		if ( null !== $known ) {
			return $known[ $tool_name ] ?? null;
		}

		return $this->passes_gates( $tool, $agent ) ? $tool : null;
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
			fn ( ToolInterface $tool ): bool => $this->passes_gates( $tool, $agent )
		);
	}

	private function passes_gates( ToolInterface $tool, AuthenticatedAgent $agent ): bool {
		return $this->is_group_enabled( $tool )
			&& $agent->scopes()->contains( $tool->required_scope() )
			&& $this->was_approved( $tool, $agent )
			&& $tool->is_available();
	}

	private function resolve_late_sources(): void {
		// Before init a source would answer empty and be used up for the request.
		if ( [] === $this->late_sources || ! did_action( 'init' ) ) {
			return;
		}

		$sources            = $this->late_sources;
		$this->late_sources = [];

		foreach ( $sources as $source ) {
			$this->add_from( $source );
		}
	}

	/**
	 * A plugin update can add tools under a scope someone already granted. Only those
	 * on the screen when they approved count, which is what the server's instructions
	 * promise: a connection only ever holds what it was approved with.
	 */
	private function was_approved( ToolInterface $tool, AuthenticatedAgent $agent ): bool {
		return ToolSection::Integrations !== $tool->group()->section() || $agent->token->has_approved_tool( $tool->name() );
	}

	/** @param callable(): iterable<ToolInterface> $source */
	private function add_from( callable $source ): void {
		try {
			foreach ( $source() as $tool ) {
				if ( ! isset( $this->tools[ $tool->name() ] ) ) {
					$this->add( $tool );
				}
			}
		} catch ( \Throwable $throwable ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error( 'A late tool source failed: ' . $throwable->getMessage(), [ 'source' => 'counterhand-mcp' ] );
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

		return $agent->token->owner_user_id . '|' . implode( ',', $scopes ) . '|' . implode( ',', $agent->token->approved_tools ?? [ '*' ] );
	}

	private function is_group_enabled( ToolInterface $tool ): bool {
		return $tool->required_scope()->is_write()
			? $this->settings->is_group_write_enabled( $tool->group() )
			: $this->settings->is_group_read_enabled( $tool->group() );
	}
}
