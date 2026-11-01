<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\WooCommerceTools\Descriptors\WpmlDescriptors;
use Counterhand\Features\WooCommerceTools\Domain\ExistingTranslationPolicy;
use Counterhand\Features\WooCommerceTools\Domain\NewTranslationPolicy;
use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRequestGuards;
use Counterhand\Tests\Unit\TestCase;

final class WpmlDescriptorsTest extends TestCase {

	/** @return array<string, OperationDescriptor> */
	private function operations(): array {
		$operations = [];

		foreach ( ( new WpmlDescriptors() )->resources() as $resource ) {
			foreach ( $resource->operations as $operation ) {
				$operations[ $operation->name->value ] = $operation;
			}
		}

		return $operations;
	}

	/** These routes also accept what products:write gates. */
	public function test_no_tool_reaches_a_price_status_or_stock(): void {
		foreach ( $this->operations() as $name => $operation ) {
			$this->assertFalse( $operation->accepts_additional(), $name );

			foreach ( [ 'regular_price', 'sale_price', 'status', 'stock_quantity', 'meta_data', 'sku' ] as $field ) {
				if ( Operation::GetItems === $operation->operation && 'status' === $field ) {
					continue;
				}

				$this->assertNotContains( $field, $operation->fields->input, $name . ' ' . $field );
			}
		}
	}

	public function test_every_write_is_held_to_a_policy_and_cannot_store_script(): void {
		foreach ( $this->operations() as $name => $operation ) {
			if ( Operation::CreateItem === $operation->operation ) {
				$this->assertInstanceOf( NewTranslationPolicy::class, $operation->policy, $name );
			}

			if ( Operation::UpdateItem === $operation->operation ) {
				$this->assertInstanceOf( ExistingTranslationPolicy::class, $operation->policy, $name );
			}

			if ( in_array( $operation->operation, [ Operation::CreateItem, Operation::UpdateItem ], true ) ) {
				$this->assertArrayHasKey( RestRequestGuards::NO_UNFILTERED_HTML, $operation->forced_params, $name );
			}
		}
	}

	public function test_a_new_product_translation_is_a_draft_and_only_publishing_shows_it(): void {
		$operations = $this->operations();

		$this->assertSame( 'draft', $operations['create_product_translation']->forced_params['status'] );
		$this->assertSame( 'publish', $operations['publish_product_translation']->forced_params['status'] );
		$this->assertTrue( $operations['publish_product_translation']->requires_confirmation );
	}

	/** Germanized clears min_age on any product write that omits it. */
	public function test_product_writes_keep_the_age_check(): void {
		foreach ( [ 'create_product_translation', 'update_product_translation', 'publish_product_translation', 'update_product_variation_translation' ] as $name ) {
			$this->assertArrayHasKey( RestRequestGuards::KEEP_UNSENT, $this->operations()[ $name ]->forced_params, $name );
		}
	}

	/** WPML alone adds nothing to these routes, and Multilingual without WPML ignores lang. */
	public function test_both_plugins_are_required(): void {
		foreach ( ( new WpmlDescriptors() )->resources() as $resource ) {
			$this->assertCount( 2, $resource->requires_functions, $resource->id );
		}
	}
}
