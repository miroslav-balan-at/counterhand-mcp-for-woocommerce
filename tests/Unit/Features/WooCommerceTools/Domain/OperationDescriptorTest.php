<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Domain;

use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ToolName;
use Counterhand\Tests\Unit\TestCase;

/**
 * The description is the entire brief a model gets before deciding whether to
 * call a tool, so how the generic sentence and the resource's own advice are
 * joined is worth pinning down.
 */
final class OperationDescriptorTest extends TestCase {

	private function descriptor( string $hint = '', ?string $override = null ): OperationDescriptor {
		return new OperationDescriptor(
			ToolName::from( 'get_coupons' ),
			Operation::GetItems,
			FieldProfile::everything(),
			$hint,
			$override
		);
	}

	public function test_without_a_hint_the_operations_own_sentence_stands(): void {
		$this->assertSame(
			Operation::GetItems->describe( 'coupon', 'coupons' ),
			$this->descriptor()->describe( 'coupon', 'coupons' )
		);
	}

	public function test_a_hint_is_appended_to_the_generic_sentence(): void {
		$description = $this->descriptor( 'Coupon codes are stored lower-case.' )->describe( 'coupon', 'coupons' );

		$this->assertStringStartsWith( Operation::GetItems->describe( 'coupon', 'coupons' ), $description );
		$this->assertStringEndsWith( 'Coupon codes are stored lower-case.', $description );
	}

	/** Where the curated wording of a hand-written tool lands when it is migrated. */
	public function test_an_override_replaces_the_generic_sentence_entirely(): void {
		$this->assertSame(
			'List the discount codes this store offers.',
			$this->descriptor( 'ignored', 'List the discount codes this store offers.' )->describe( 'coupon', 'coupons' )
		);
	}

	public function test_the_descriptor_does_not_restate_the_route_or_the_method(): void {
		$properties = array_map(
			static fn ( \ReflectionProperty $property ): string => $property->getName(),
			( new \ReflectionClass( OperationDescriptor::class ) )->getProperties()
		);

		// Both are derived from Operation and the resource, so a descriptor
		// cannot declare a delete that dispatches a GET.
		$this->assertNotContains( 'route', $properties );
		$this->assertNotContains( 'method', $properties );
	}

	/** What keeps a plugin update from being overruled by the copy written here. */
	public function test_the_live_route_wins_over_a_declared_argument(): void {
		$operation = new OperationDescriptor(
			ToolName::from( 'a_tool' ),
			Operation::CreateItem,
			new FieldProfile( [], [] ),
			declared_arguments: [
				'title' => [ 'type' => 'string' ],
				'form'  => [ 'type' => 'string' ],
			]
		);

		$schema = $operation->with_declared_arguments(
			[
				'type'       => 'object',
				'properties' => [ 'title' => [ 'type' => 'integer' ] ],
			]
		);

		$this->assertSame( 'integer', $schema['properties']['title']['type'] );
		$this->assertSame( 'string', $schema['properties']['form']['type'] );
	}

	public function test_a_route_publishing_nothing_gets_the_declared_arguments(): void {
		$operation = new OperationDescriptor(
			ToolName::from( 'a_tool' ),
			Operation::CreateItem,
			new FieldProfile( [], [] ),
			declared_arguments: [ 'form' => [ 'type' => 'string' ] ]
		);

		$schema = $operation->with_declared_arguments(
			[
				'type'       => 'object',
				'properties' => [],
			]
		);

		$this->assertSame( [ 'form' => [ 'type' => 'string' ] ], $schema['properties'] );
	}

	/** Gated writes close, except a declared copy, which must let later fields through. */
	public function test_a_gated_operation_closes_unless_its_arguments_are_declared(): void {
		$gated = static fn ( array $declared ): OperationDescriptor => new OperationDescriptor(
			ToolName::from( 'a_tool' ),
			Operation::UpdateItem,
			new FieldProfile( [], [] ),
			requires_confirmation: true,
			declared_arguments: $declared
		);

		$this->assertFalse( $gated( [] )->accepts_additional() );
		$this->assertTrue( $gated( [ 'title' => [ 'type' => 'string' ] ] )->accepts_additional() );
	}
}
