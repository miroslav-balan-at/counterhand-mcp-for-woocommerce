<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Infrastructure;

use Counterhand\Features\WooCommerceTools\Descriptors\StaticDescriptorCatalog;
use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestMethod;
use Counterhand\Features\WooCommerceTools\Infrastructure\RouteArgs;
use Counterhand\Features\WooCommerceTools\Infrastructure\SchemaFromArgs;
use Counterhand\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * The schema-drift canary.
 *
 * SchemaFromArgsTest proves the transformation rules against inputs built by
 * hand, which is the right way to test rules but says nothing about whether the
 * rules still fit what WooCommerce registers. These fixtures are the other
 * half: real `args` arrays captured from a live store, checked in with the
 * WooCommerce version they came from, and run through the same converter.
 *
 * The second test here is the one that earns the fixtures their place. Every
 * field name a descriptor publishes is asserted to be an argument WooCommerce
 * actually declares — the check that had to be run by hand against a live store
 * until now, and the one that catches a FieldProfile going stale after a
 * WooCommerce upgrade. Regenerate with `regenerate.php` on a major upgrade; a
 * failure here is the early warning.
 */
final class WcRouteArgsFixtureTest extends TestCase {

	private const DIR = __DIR__ . '/../../../../Fixtures/WcRouteArgs';

	/**
	 * Which shipped tool each fixture speaks for.
	 *
	 * Spelled out rather than derived, so that a descriptor renamed without
	 * thinking fails here instead of quietly dropping out of the check.
	 */
	private const COVERS = [
		'coupons-collection-get'   => 'get_coupons',
		'coupons-collection-post'  => 'create_coupon',
		'coupons-item-put'         => 'update_coupon',
		'coupons-item-delete'      => 'delete_coupon',
		'products-collection-get'  => 'list_products',
		'products-collection-post' => 'create_product',
		'orders-collection-post'   => null,
		'order-notes-collection-get' => 'get_order_notes',
		'customers-collection-post'  => null,
		'system-status-get'          => 'get_system_status',
		'settings-group-get'         => 'get_settings',
	];

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'apply_filters' )->returnArg( 2 );
	}

	/** @return array<string, mixed> */
	private function fixture( string $slug ): array {
		$path = self::DIR . '/' . $slug . '.php';

		$this->assertFileExists( $path, 'Fixture missing — run regenerate.php against a store.' );

		return (array) require $path;
	}

	/** @return iterable<string, array{string}> */
	public static function fixtures(): iterable {
		foreach ( glob( self::DIR . '/*.php' ) ?: [] as $path ) {
			$slug = basename( $path, '.php' );

			if ( 'regenerate' === $slug ) {
				continue;
			}

			yield $slug => [ $slug ];
		}
	}

	/** A fixture set that silently emptied itself would make every test below vacuous. */
	public function test_the_fixtures_are_present(): void {
		$this->assertGreaterThanOrEqual( 10, iterator_count( self::fixtures() ) );
	}

	/**
	 * Every fixture records the WooCommerce version it came from, which is what
	 * makes a diff here interpretable rather than just noise.
	 *
	 * @dataProvider fixtures
	 */
	public function test_a_fixture_records_where_it_came_from( string $slug ): void {
		$this->assertMatchesRegularExpression(
			'/Captured from WooCommerce \d+\.\d+/',
			(string) file_get_contents( self::DIR . '/' . $slug . '.php' )
		);
	}

	/**
	 * The transport envelope and WooCommerce's own callbacks must not reach an
	 * agent: _fields and context are handled by the gateway, and a callback name
	 * in a published schema is meaningless to a model and confusing to a client.
	 *
	 * @dataProvider fixtures
	 */
	public function test_no_envelope_or_callback_survives_conversion( string $slug ): void {
		$schema = $this->convert( $slug );

		// Cast: system-status-get has no arguments, so properties is stdClass.
		foreach ( [ 'context', '_fields', '_embed', '_envelope', '_method' ] as $envelope ) {
			$this->assertArrayNotHasKey( $envelope, (array) $schema['properties'], $slug );
		}

		$json = (string) wp_json_encode( $schema );

		foreach ( [ 'validate_callback', 'sanitize_callback', 'arg_options', '__closure__' ] as $leak ) {
			$this->assertStringNotContainsString( $leak, $json, $slug . ' leaks ' . $leak );
		}
	}

	/**
	 * Only the seven JSON Schema types, at any depth.
	 *
	 * WooCommerce 10.9.4 expresses a mixed value as a type *union* — meta_data's
	 * value is ["null","object","string","number","boolean","integer","array"] —
	 * which is legal and passes through intact. Older versions wrote
	 * 'type' => 'mixed', which is not, and which strict MCP clients reject
	 * outright. Both have to come out clean.
	 *
	 * @dataProvider fixtures
	 */
	public function test_every_published_type_is_a_real_json_schema_type( string $slug ): void {
		$offenders = [];
		$this->collect_types( $this->convert( $slug ), '', $offenders );

		$this->assertSame( [], $offenders, $slug . ' publishes non-JSON-Schema types.' );
	}

	/**
	 * The staleness canary.
	 *
	 * A `FieldProfile` names fields but never describes them, so the only way it
	 * can be wrong is by naming something WooCommerce no longer declares. That
	 * costs nothing at runtime — the name is simply dropped — which is exactly
	 * why it needs a test: a profile can rot completely without any symptom.
	 *
	 * @dataProvider covered_tools
	 */
	public function test_a_descriptors_field_names_are_arguments_woocommerce_declares( string $slug, string $tool ): void {
		$declared = array_keys( $this->fixture( $slug ) );
		$profile  = $this->profile_for( $tool );

		$this->assertNotNull( $profile, sprintf( 'Tool "%s" is no longer in the catalogue.', $tool ) );

		// Path placeholders are bound into the URL, so a route need not declare
		// them as arguments.
		$unknown = array_diff( $profile->input, $declared, [ 'id', 'group_id', 'order_id', 'attribute_id', 'product_id', 'zone_id', 'instance_id', 'slug', 'location', 'currency' ] );

		$this->assertSame(
			[],
			array_values( $unknown ),
			sprintf( '%s publishes fields WooCommerce does not declare on this route.', $tool )
		);
	}

	/** @return iterable<string, array{string, string}> */
	public static function covered_tools(): iterable {
		foreach ( self::COVERS as $slug => $tool ) {
			if ( null === $tool ) {
				continue;
			}

			yield $slug . ' => ' . $tool => [ $slug, $tool ];
		}
	}

	/**
	 * Walks a schema as a schema, descending only where a nested one can live.
	 *
	 * The distinction matters more than it looks: WooCommerce publishes a
	 * product field *named* `type`, so a walker that treats every `type` key as
	 * the JSON Schema keyword reports the product types as illegal. Keys under
	 * `properties` are names; keys beside it are keywords.
	 *
	 * @param array<string, mixed> $schema
	 * @param list<string>         $offenders
	 */
	private function collect_types( array $schema, string $path, array &$offenders ): void {
		$standard = [ 'string', 'number', 'integer', 'boolean', 'array', 'object', 'null' ];

		foreach ( (array) ( $schema['type'] ?? [] ) as $type ) {
			if ( ! in_array( $type, $standard, true ) ) {
				$offenders[] = $path . '.type=' . var_export( $type, true );
			}
		}

		foreach ( (array) ( $schema['properties'] ?? [] ) as $name => $property ) {
			if ( is_array( $property ) ) {
				$this->collect_types( $property, $path . '.' . $name, $offenders );
			}
		}

		foreach ( [ 'items', 'additionalProperties' ] as $keyword ) {
			if ( isset( $schema[ $keyword ] ) && is_array( $schema[ $keyword ] ) ) {
				$this->collect_types( $schema[ $keyword ], $path . '.' . $keyword, $offenders );
			}
		}
	}

	/** @return array<string, mixed> */
	private function convert( string $slug ): array {
		return ( new SchemaFromArgs() )->build(
			new RouteArgs( '/fixture/' . $slug, RestMethod::Get, $this->fixture( $slug ) ),
			FieldProfile::everything()
		);
	}

	private function profile_for( string $tool ): ?FieldProfile {
		foreach ( ( new StaticDescriptorCatalog() )->resources() as $resource ) {
			foreach ( $resource->operations as $operation ) {
				if ( $tool === $operation->name->value ) {
					return $operation->fields;
				}
			}
		}

		return null;
	}
}
