<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\WooCommerceTools\Application;

use AgentGateMcp\Features\WooCommerceTools\Domain\DescriptorProvider;
use AgentGateMcp\Features\WooCommerceTools\Domain\MetaKeyPolicy;
use AgentGateMcp\Features\WooCommerceTools\Domain\MetaOperation;
use AgentGateMcp\Features\WooCommerceTools\Domain\OperationDescriptor;
use AgentGateMcp\Features\WooCommerceTools\Domain\ResourceDescriptor;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RestGatewayInterface;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RouteCatalog;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\RoutePermissionProbe;
use AgentGateMcp\Features\WooCommerceTools\Infrastructure\SchemaProvider;
use AgentGateMcp\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the tool objects a descriptor set describes.
 *
 * Pure construction: no WooCommerce call happens here, which is what lets the
 * whole surface be registered at plugins_loaded. Forcing rest_get_server() that
 * early would fire rest_api_init before init, and WooCommerce registers its
 * controllers for post types that do not exist yet at that point — the catalog
 * would come back half empty and stay that way for the request.
 */
final readonly class ToolFactory {

	public function __construct(
		private RestGatewayInterface $gateway,
		private RouteCatalog $catalog,
		private RoutePermissionProbe $probe,
		private SchemaProvider $schemas,
		private MetaKeyPolicy $meta_policy,
	) {}

	/** @return list<ToolInterface> */
	public function tools( DescriptorProvider $descriptors ): array {
		$tools = [];

		foreach ( $descriptors->resources() as $resource ) {
			foreach ( $resource->operations as $operation ) {
				$tools[] = $this->tool( $resource, $operation );
			}

			$tools = [ ...$tools, ...$this->meta_tools( $resource ) ];
		}

		return $tools;
	}

	/**
	 * The custom-field tools for a resource that declares it has any.
	 *
	 * Built here rather than declared as operations because they are not route
	 * operations: wc/v3 has no meta endpoint, so these read and write a field of
	 * the item route and need a policy no OperationDescriptor could carry.
	 *
	 * @return list<ToolInterface>
	 */
	// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.resourceFound -- "resource" is what wc/v3 calls the thing a controller exposes; renaming it here would leave the code speaking a different language from the API it wraps.
	private function meta_tools( ResourceDescriptor $resource ): array {
		if ( null === $resource->meta || null === $resource->item ) {
			return [];
		}

		$tools = [];

		foreach ( MetaOperation::cases() as $operation ) {
			$scope = $operation->intent()->scope_of( $resource->group );

			// A group with no write axis cannot offer meta writes either. Reads
			// still stand, so this skips rather than throws.
			if ( null === $scope ) {
				continue;
			}

			$tools[] = new MetaTool(
				$resource,
				$operation,
				$resource->meta,
				$scope,
				$this->meta_policy,
				$this->gateway,
				$this->probe
			);
		}

		return $tools;
	}

	/**
	 * @throws \LogicException When the descriptor asks for something the group cannot grant.
	 */
	// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.resourceFound -- "resource" is what wc/v3 calls the thing a controller exposes; renaming it here would leave the code speaking a different language from the API it wraps.
	private function tool( ResourceDescriptor $resource, OperationDescriptor $operation ): ToolInterface {
		$intent = $operation->operation->intent();
		$scope  = $intent->scope_of( $resource->group );

		// A write on a group WooCommerce only exposes read-only. There is no
		// scope to gate it with, so there is no safe way to offer it — and no
		// point discovering that at dispatch time on someone's store.
		if ( null === $scope ) {
			throw new \LogicException(
				sprintf(
					'Tool "%s" is a %s but group "%s" has no %s scope.',
					$operation->name->value,
					$operation->operation->value,
					$resource->group->value,
					$intent->value
				)
			);
		}

		return new GeneratedTool(
			$resource,
			$operation,
			$scope,
			$this->gateway,
			$this->catalog,
			$this->probe,
			$this->schemas
		);
	}
}
