<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Application;

use Counterhand\Features\Tokens\Domain\ScopeGroupInterface;
use Counterhand\Features\Tokens\Domain\ScopeInterface;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ToolIntent;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestGatewayInterface;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestMethod;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestResult;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Features\WooCommerceTools\Infrastructure\RouteCatalog;
use Counterhand\Features\WooCommerceTools\Infrastructure\RoutePermissionProbe;
use Counterhand\Features\WooCommerceTools\Infrastructure\SchemaProvider;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Any REST resource operation, as an MCP tool.
 *
 * One class for the whole surface. That is the design, not a shortcut: a tool
 * per endpoint would mean a hand-written copy of WooCommerce's field list in
 * every one, and those copies go stale the first time WooCommerce ships a new
 * field. Here the descriptor says which resource and which operation, and every
 * answer an agent actually reads — the argument names, their types, enums,
 * defaults and descriptions — is asked of the running WooCommerce.
 */
final readonly class GeneratedTool implements ToolInterface {

	public function __construct(
		// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.resourceFound -- "resource" is what wc/v3 calls the thing a controller exposes; renaming it here would leave the code speaking a different language from the API it wraps.
		private ResourceDescriptor $resource,
		private OperationDescriptor $operation,
		private ScopeInterface $scope,
		private RestGatewayInterface $gateway,
		private RouteCatalog $catalog,
		private RoutePermissionProbe $probe,
		private SchemaProvider $schemas,
	) {}

	public function name(): string {
		return $this->operation->name->value;
	}

	public function description(): string {
		return $this->operation->describe( $this->resource->singular, $this->resource->plural );
	}

	public function input_schema(): array {
		$route = $this->route();

		return $this->with_closed_arguments(
			$this->with_body_argument(
				$this->with_defaults(
					$this->operation->with_declared_arguments(
						$this->schemas->schema(
							$this->name(),
							$route,
							$this->method(),
							$this->operation->fields,
							$route->parameters()
						)
					)
				)
			)
		);
	}

	public function required_scope(): ScopeInterface {
		return $this->scope;
	}

	public function group(): ScopeGroupInterface {
		return $this->resource->group;
	}

	public function requires_confirmation(): bool {
		return $this->operation->requires_confirmation;
	}

	public function is_read_only(): bool {
		return ToolIntent::Read === $this->operation->operation->intent();
	}

	/** A write the person must confirm is treated as destructive whatever its verb. */
	public function is_destructive(): bool {
		return $this->operation->requires_confirmation || $this->operation->operation->is_destructive();
	}

	public function is_idempotent(): bool {
		return $this->operation->operation->is_idempotent();
	}

	/**
	 * Two questions, both fail-closed: does this WooCommerce still serve the
	 * route, and would it let this user in.
	 *
	 * The first matters because the permission question is asked of the
	 * collection while several operations dispatch to the item route — a
	 * WooCommerce that dropped an endpoint would otherwise keep advertising a
	 * tool that can only 404.
	 */
	public function is_available(): bool {
		$intent = $this->operation->operation->intent();

		return $this->resource->is_plugin_loaded()
			&& $this->catalog->has( $this->route(), $this->method() )
			&& $this->probe->allows( $this->resource->probe_route( $intent ), $intent->probe_method() );
	}

	/** Every route here is on this store. */
	public function is_open_world(): bool {
		return false;
	}

	public function execute( array $arguments ): array {
		$this->guard( $arguments );

		$body = $this->body( $arguments );

		if ( null !== $this->operation->body_argument ) {
			unset( $arguments[ $this->operation->body_argument ] );
		}

		$result = $this->gateway->dispatch( $this->route(), $this->method(), $this->params( $arguments ), $body );

		return $this->shape( $result );
	}

	/**
	 * The value of the argument this operation sends as its whole JSON body.
	 *
	 * @param  array<string, mixed> $arguments
	 */
	private function body( array $arguments ): mixed {
		if ( null === $this->operation->body_argument ) {
			return null;
		}

		return $arguments[ $this->operation->body_argument ] ?? null;
	}

	/**
	 * The check WooCommerce cannot make for us, before dispatch.
	 *
	 * @param  array<string, mixed> $arguments
	 * @throws ToolCallException When the policy refuses.
	 */
	private function guard( array $arguments ): void {
		$verdict = $this->operation->policy?->verdict( $arguments );

		if ( null !== $verdict && ! $verdict->allowed ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
			throw new ToolCallException( $verdict->reason );
		}
	}

	/**
	 * @param  array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	private function params( array $arguments ): array {
		$params = [
			// First, so the agent can argue its way out of any of them.
			...$this->operation->default_params,
			...$arguments,
			// 'edit' context returns raw HTML for every prose field and roughly
			// doubles the payload, and nothing here renders an edit form.
			'context' => 'view',
			// Last, so a descriptor pinning a value cannot be argued out of it.
			...$this->operation->forced_params,
		];

		$fields = $this->operation->fields->output_fields();

		if ( null === $fields ) {
			return $params;
		}

		$params['_fields'] = $fields;

		return $params;
	}

	/**
	 * Restates the descriptor's default_params as schema defaults.
	 *
	 * Without this an agent would be told WooCommerce's default — "publish" for
	 * a product — while execute() quietly sent a different one. Both halves
	 * come from the same declaration, so the tool cannot describe itself
	 * inaccurately. A default naming a field the profile does not publish is
	 * dropped rather than invented into the schema.
	 *
	 * @param  array<string, mixed> $schema
	 * @return array<string, mixed>
	 */
	private function with_defaults( array $schema ): array {
		foreach ( $this->operation->default_params as $field => $value ) {
			if ( isset( $schema['properties'][ $field ] ) ) {
				$schema['properties'][ $field ]['default'] = $value;
			}
		}

		return $schema;
	}

	/**
	 * Publishes the argument carrying the whole JSON body.
	 *
	 * Declared here rather than in a FieldProfile because it is not one of
	 * WooCommerce's route fields: the route publishes no argument at all, and
	 * the controller reads the body directly.
	 *
	 * @param  array<string, mixed> $schema
	 * @return array<string, mixed>
	 */
	private function with_body_argument( array $schema ): array {
		$argument = $this->operation->body_argument;

		if ( null === $argument || null === $this->operation->body_schema ) {
			return $schema;
		}

		$schema['properties'][ $argument ] = $this->operation->body_schema;

		$schema['required'] = [ ...( $schema['required'] ?? [] ), $argument ];

		return $schema;
	}

	/**
	 * A risky write takes exactly what it declares, so nothing undeclared rides
	 * along on a call the person is being asked to approve.
	 *
	 * @param  array<string, mixed> $schema
	 * @return array<string, mixed>
	 */
	private function with_closed_arguments( array $schema ): array {
		if ( ! $this->operation->accepts_additional() ) {
			$schema['additionalProperties'] = false;
		}

		return $schema;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function shape( RestResult $result ): array {
		if ( ! $this->operation->operation->returns_collection() ) {
			return [ $this->resource->singular => $result->item() ];
		}

		$items = $result->items();

		return [
			$this->resource->plural => $items,
			'count'                 => count( $items ),
			...$result->pagination(),
		];
	}

	private function route(): RestRoute {
		return $this->resource->route_for( $this->operation->operation );
	}

	private function method(): RestMethod {
		return $this->operation->operation->method();
	}
}
