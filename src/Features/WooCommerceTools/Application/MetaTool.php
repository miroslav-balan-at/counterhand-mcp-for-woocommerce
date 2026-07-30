<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Application;

use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Features\WooCommerceTools\Domain\MetaKeyPolicy;
use Counterhand\Features\WooCommerceTools\Domain\MetaOperation;
use Counterhand\Features\WooCommerceTools\Domain\MetaOwner;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestGatewayInterface;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestMethod;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Features\WooCommerceTools\Infrastructure\RoutePermissionProbe;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * A resource's custom fields, as three tools.
 *
 * Not a GeneratedTool, because there is no meta route to generate from — wc/v3
 * has no meta rest_base at all. Custom fields are a *field* of the item route,
 * so these tools read and write meta_data on /products/{id} and friends, and
 * the schema is written by hand because it describes one key and one value
 * rather than a WooCommerce resource.
 *
 * The design turns on one verified fact: WooCommerce's meta_data is an upsert.
 * Sending a single key leaves every other key untouched, and sending a key with
 * no value deletes it. That means none of these operations has to read the
 * resource first, which removes the lost-update race a read-modify-write would
 * have had — two agents editing different keys on the same product cannot now
 * clobber each other.
 *
 * Every key crossing this class is judged by MetaKeyPolicy, in both directions.
 * WooCommerce authorises the object; only the policy looks at the key, and the
 * key is where privilege escalation lives.
 */
final readonly class MetaTool implements ToolInterface {

	public function __construct(
		private ResourceDescriptor $descriptor,
		private MetaOperation $operation,
		private MetaOwner $owner,
		private ApiScope $scope,
		private MetaKeyPolicy $policy,
		private RestGatewayInterface $gateway,
		private RoutePermissionProbe $probe,
	) {}

	public function name(): string {
		return $this->operation->tool_name( $this->descriptor->singular_slug() );
	}

	public function description(): string {
		return match ( $this->operation ) {
			MetaOperation::Get    => sprintf(
				'Read the custom fields stored on one %s. These are the extra values plugins and integrations attach to it; anything WooCommerce itself keeps private is not returned.',
				$this->descriptor->singular
			),
			MetaOperation::Set    => sprintf(
				'Set one custom field on a %s, creating it if it is not there and replacing it if it is. Other custom fields are left alone. %s',
				$this->descriptor->singular,
				$this->owner->carries_identity()
					? 'SAFETY: these are stored against a WordPress user account, so confirm the key with the user before writing one you did not read back first.'
					: 'Keys that WooCommerce keeps for its own bookkeeping cannot be written, because changing one behind the API leaves the record inconsistent.'
			),
			MetaOperation::Delete => sprintf(
				'Remove one custom field from a %s. This cannot be undone, and a field another plugin relies on will simply be gone — read it with %s first.',
				$this->descriptor->singular,
				MetaOperation::Get->tool_name( $this->descriptor->singular_slug() )
			),
		};
	}

	public function input_schema(): array {
		$id = $this->descriptor->item?->parameters()[0] ?? 'id';

		$properties = [
			$id => [
				'type'        => 'integer',
				'description' => sprintf( 'Numeric id of the %s.', $this->descriptor->singular ),
			],
		];

		if ( MetaOperation::Get !== $this->operation ) {
			$properties['key'] = [
				'type'        => 'string',
				'description' => 'The custom field name.',
			];
		}

		if ( MetaOperation::Set === $this->operation ) {
			$properties['value'] = [
				'description' => 'The value to store: a string, number, boolean or list.',
			];
		}

		return [
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => array_keys( $properties ),
			'additionalProperties' => false,
		];
	}

	public function required_scope(): ApiScope {
		return $this->scope;
	}

	public function group(): ToolGroup {
		return $this->descriptor->group;
	}

	/** Gated exactly like the resource it hangs off, since it is that route. */
	public function is_available(): bool {
		$intent = $this->operation->intent();

		return $this->probe->allows( $this->descriptor->probe_route( $intent ), $intent->probe_method() );
	}

	public function execute( array $arguments ): array {
		return match ( $this->operation ) {
			MetaOperation::Get    => $this->read( $arguments ),
			MetaOperation::Set    => $this->write( $arguments, $this->value_of( $arguments ) ),
			MetaOperation::Delete => $this->write( $arguments, null ),
		};
	}

	/**
	 * @param  array<string, mixed> $arguments
	 * @return array<string, mixed>
	 * @throws ToolCallException When the resource cannot be read.
	 */
	private function read( array $arguments ): array {
		$result = $this->gateway->dispatch(
			$this->item_route(),
			RestMethod::Get,
			[
				...$this->path_params( $arguments ),
				'_fields' => 'meta_data',
			]
		);

		$fields = [];

		foreach ( $this->entries( $result->item()['meta_data'] ?? [] ) as $entry ) {
			if ( ! isset( $entry['key'] ) ) {
				continue;
			}

			$key = (string) $entry['key'];

			// Withheld rather than reported as withheld: naming the keys would
			// hand back the very list the policy exists to keep quiet.
			if ( ! $this->policy->may_read( $key, $this->owner )->allowed ) {
				continue;
			}

			$fields[] = [
				'key'   => $key,
				'value' => $entry['value'] ?? null,
			];
		}

		return [
			'meta'  => $fields,
			'count' => count( $fields ),
		];
	}

	/**
	 * meta_data as plain arrays, whatever WooCommerce handed back.
	 *
	 * WooCommerce answers with WC_Meta_Data objects rather than arrays, and
	 * WP_REST_Server::response_to_data() does not flatten them — so an is_array()
	 * check on an entry silently drops every field, which is exactly what it did
	 * until a live store showed the read coming back empty. Going through
	 * JSON is what respects their JsonSerializable, where a cast would expose
	 * the class's protected internals instead.
	 *
	 * @param  mixed $meta
	 * @return list<array<string, mixed>>
	 */
	private function entries( mixed $meta ): array {
		if ( ! is_array( $meta ) ) {
			return [];
		}

		$decoded = json_decode( (string) wp_json_encode( $meta ), true );

		return is_array( $decoded ) ? array_values( array_filter( $decoded, 'is_array' ) ) : [];
	}

	/**
	 * @param  array<string, mixed> $arguments
	 * @return array<string, mixed>
	 * @throws ToolCallException When the policy denies the key, or WooCommerce rejects the write.
	 */
	private function write( array $arguments, mixed $value ): array {
		$key     = $this->key_of( $arguments );
		$verdict = $this->policy->may_write( $key, $value, $this->owner );

		if ( ! $verdict->allowed ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
			throw new ToolCallException( $verdict->reason );
		}

		// WooCommerce deletes a field when the entry carries no value at all,
		// which is what its REST documentation prescribes — not a null value.
		$entry = null === $value ? [ 'key' => $key ] : [
			'key'   => $key,
			'value' => $value,
		];

		$this->gateway->dispatch(
			$this->item_route(),
			RestMethod::Put,
			[
				...$this->path_params( $arguments ),
				'meta_data' => [ $entry ],
			]
		);

		return [
			'key'     => $key,
			'deleted' => null === $value,
		];
	}

	/**
	 * @param  array<string, mixed> $arguments
	 * @throws ToolCallException When the key is missing or empty.
	 */
	private function key_of( array $arguments ): string {
		$key = trim( (string) ( $arguments['key'] ?? '' ) );

		if ( '' === $key ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
			throw new ToolCallException( 'A custom field name is required.' );
		}

		return $key;
	}

	/**
	 * Null is not a way to delete through the set tool: deleting is its own
	 * tool, so that an agent cannot remove a field while believing it wrote one.
	 *
	 * @param  array<string, mixed> $arguments
	 * @throws ToolCallException When no value was supplied.
	 */
	private function value_of( array $arguments ): mixed {
		if ( ! array_key_exists( 'value', $arguments ) || null === $arguments['value'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
			throw new ToolCallException(
				sprintf(
					'A value is required. To remove the field, call %s.',
					MetaOperation::Delete->tool_name( $this->descriptor->singular_slug() )
				)
			);
		}

		return $arguments['value'];
	}

	/**
	 * @param  array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	private function path_params( array $arguments ): array {
		$names = $this->item_route()->parameters();

		return array_intersect_key( $arguments, array_flip( $names ) );
	}

	/** @throws \LogicException When a meta tool was built for a resource with no item route. */
	private function item_route(): RestRoute {
		if ( null === $this->descriptor->item ) {
			throw new \LogicException(
				sprintf( 'Resource "%s" has no item route to carry meta.', $this->descriptor->id )
			);
		}

		return $this->descriptor->item;
	}
}
