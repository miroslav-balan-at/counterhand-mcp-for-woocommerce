<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Application;

use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestGatewayInterface;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestMethod;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestResult;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Features\WooCommerceTools\Infrastructure\RouteCatalog;
use Counterhand\Features\WooCommerceTools\Infrastructure\RoutePermissionProbe;
use Counterhand\Features\WooCommerceTools\Infrastructure\SchemaProvider;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Any wc/v3 resource operation, as an MCP tool.
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
		private ApiScope $scope,
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

		return $this->with_confirmation(
			$this->with_defaults(
				$this->schemas->schema(
					$this->name(),
					$route,
					$this->method(),
					$this->operation->fields,
					$route->parameters()
				)
			)
		);
	}

	public function required_scope(): ApiScope {
		return $this->scope;
	}

	public function group(): ToolGroup {
		return $this->resource->group;
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

		return $this->catalog->has( $this->route(), $this->method() )
			&& $this->probe->allows( $this->resource->probe_route( $intent ), $intent->probe_method() );
	}

	public function execute( array $arguments ): array {
		$this->guard( $arguments );

		$result = $this->gateway->dispatch( $this->route(), $this->method(), $this->params( $arguments ) );

		return $this->shape( $result );
	}

	/**
	 * The two checks WooCommerce cannot make for us, both before dispatch.
	 *
	 * Confirmation first, because refusing an unconfirmed call is cheaper than
	 * explaining why the thing it asked for is off limits, and an agent that has
	 * not confirmed has not yet involved the person who would decide.
	 *
	 * @param  array<string, mixed> $arguments
	 * @throws ToolCallException When unconfirmed, or when the policy refuses.
	 */
	private function guard( array $arguments ): void {
		if ( $this->operation->requires_confirmation && true !== ( $arguments['confirm'] ?? false ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
			throw new ToolCallException(
				'This changes the store in a way that is not easily undone. Tell the user exactly what will happen, and call it again with confirm set to true only once they have agreed.'
			);
		}

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
		// Ours, not WooCommerce's — sending it on would be an unknown parameter.
		unset( $arguments['confirm'] );

		$params = [
			// First, so the agent can argue its way out of any of them.
			...$this->operation->default_params,
			...$arguments,
			// Last, so a descriptor pinning a value cannot be argued out of it.
			...$this->operation->forced_params,
			// 'edit' context returns raw HTML for every prose field and roughly
			// doubles the payload, and nothing here renders an edit form.
			'context' => 'view',
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
	 * Publishes the `confirm` argument, required, for operations that demand it.
	 *
	 * In the schema rather than only in the prose because a model reads the
	 * schema far more reliably than it reads a warning, and because a required
	 * argument means the first attempt fails loudly rather than succeeding
	 * quietly.
	 *
	 * @param  array<string, mixed> $schema
	 * @return array<string, mixed>
	 */
	private function with_confirmation( array $schema ): array {
		if ( ! $this->operation->requires_confirmation ) {
			return $schema;
		}

		// An argument-less route publishes properties as stdClass; adding a key
		// to that would throw, and `confirm` makes it non-empty anyway.
		$properties            = (array) ( $schema['properties'] ?? [] );
		$properties['confirm'] = [
			'type'        => 'boolean',
			'description' => 'Must be true. Set it only after telling the user what this will change and getting their agreement.',
		];

		$schema['properties'] = $properties;

		$schema['required']             = [ ...( $schema['required'] ?? [] ), 'confirm' ];
		$schema['additionalProperties'] = false;

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
