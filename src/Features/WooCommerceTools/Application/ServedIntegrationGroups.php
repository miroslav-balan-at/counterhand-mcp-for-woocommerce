<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Application;

use Counterhand\Features\Tokens\Domain\ScopeGroupInterface;
use Counterhand\Features\WooCommerceTools\Domain\DescriptorProvider;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestMethod;
use Counterhand\Features\WooCommerceTools\Infrastructure\RouteCatalog;
use Counterhand\Shared\Tool\ToolSection;

defined( 'ABSPATH' ) || exit;

/**
 * Which REST integrations this store serves right now, read off the route table
 * so no plugin needs detecting by name. Asked many times per screen, so answered
 * once; never before init, when the route table cannot be built.
 */
final class ServedIntegrationGroups {

	/** @var ?list<ScopeGroupInterface> */
	private ?array $groups = null;

	public function __construct(
		private readonly DescriptorProvider $descriptors,
		private readonly RouteCatalog $catalog,
	) {}

	/** @return list<ScopeGroupInterface> */
	public function all(): array {
		if ( ! did_action( 'init' ) ) {
			return [];
		}

		return $this->groups ??= $this->served();
	}

	/** @return list<ScopeGroupInterface> */
	private function served(): array {
		$groups = [];

		foreach ( $this->descriptors->resources() as $resource ) {
			if ( ToolSection::Integrations === $resource->group->section() && $this->catalog->has( $resource->collection, RestMethod::Get ) ) {
				$groups[] = $resource->group;
			}
		}

		return $groups;
	}
}
