<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\McpServer;

use AgentGateMcp\Features\Settings\PluginSettings;
use AgentGateMcp\Features\Tokens\Authentication\AuthenticatedAgent;
use AgentGateMcp\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Holds every wired tool and filters visibility per request.
 *
 * The SAME filtered set is used for tools/list and tools/call: a tool that
 * is toggled off or out of scope does not exist for the caller (fail-closed
 * at both ends, enforced at call time — not just hidden from the list).
 */
final class ToolRegistry {

	/** @var array<string, ToolInterface> */
	private array $tools = [];

	public function __construct( private readonly PluginSettings $settings ) {}

	public function add( ToolInterface $tool ): void {
		$this->tools[ $tool->name() ] = $tool;
	}

	/** @return list<ToolInterface> */
	public function visible_for( AuthenticatedAgent $agent ): array {
		return array_values(
			array_filter(
				$this->tools,
				fn ( ToolInterface $tool ): bool => $this->is_group_enabled( $tool ) && $agent->scopes()->contains( $tool->required_scope() )
			)
		);
	}

	public function resolve_for( AuthenticatedAgent $agent, string $tool_name ): ?ToolInterface {
		foreach ( $this->visible_for( $agent ) as $tool ) {
			if ( $tool->name() === $tool_name ) {
				return $tool;
			}
		}

		return null;
	}

	private function is_group_enabled( ToolInterface $tool ): bool {
		return $tool->required_scope()->is_write()
			? $this->settings->is_group_write_enabled( $tool->group() )
			: $this->settings->is_group_read_enabled( $tool->group() );
	}
}
