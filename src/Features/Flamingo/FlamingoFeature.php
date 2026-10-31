<?php

declare( strict_types=1 );

namespace Counterhand\Features\Flamingo;

use Counterhand\Features\McpServer\ToolRegistrarInterface;
use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\Tokens\Domain\ScopeGroups;
use Counterhand\Shared\FeatureInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Flamingo gets its own integration rather than sharing Contact Form 7's: forms
 * are configuration, while these messages are other people's personal data.
 */
final readonly class FlamingoFeature implements FeatureInterface {

	public function __construct(
		private ToolRegistrarInterface $tool_registry,
		private InboxInterface $inbox,
	) {}

	public function register(): void {
		if ( ! $this->inbox->is_installed() ) {
			return;
		}

		$group = $this->group();

		foreach ( InboundMessageOperation::cases() as $operation ) {
			$this->tool_registry->add( new InboundMessageTool( $operation, $this->inbox, $group ) );
		}

		add_filter( ScopeGroups::INTEGRATIONS_FILTER, [ $this, 'with_group' ] );
	}

	/**
	 * @param  mixed $groups What earlier filters returned.
	 * @return array<mixed>
	 */
	public function with_group( mixed $groups ): array {
		return [ ...( is_array( $groups ) ? $groups : [] ), $this->group() ];
	}

	private function group(): IntegrationGroup {
		return IntegrationGroup::of( 'flamingo', copy: new FlamingoCopy() );
	}
}
