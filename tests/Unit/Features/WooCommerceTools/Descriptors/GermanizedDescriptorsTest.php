<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\WooCommerceTools\Descriptors\GermanizedDescriptors;
use Counterhand\Features\WooCommerceTools\Descriptors\ProductDescriptors;
use Counterhand\Features\WooCommerceTools\Descriptors\StaticDescriptorCatalog;
use Counterhand\Features\WooCommerceTools\Descriptors\VariationDescriptors;
use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRequestGuards;
use Counterhand\Tests\Unit\TestCase;

final class GermanizedDescriptorsTest extends TestCase {

	/** @return array<string, OperationDescriptor> */
	private function operations(): array {
		$operations = [];

		foreach ( ( new GermanizedDescriptors() )->resources() as $resource ) {
			foreach ( $resource->operations as $operation ) {
				$operations[ $operation->name->value ] = $operation;
			}
		}

		return $operations;
	}

	/** /products also accepts these, and they are what products:write gates. */
	public function test_the_product_tools_stay_closed_and_name_no_catalog_field(): void {
		$update = $this->operations()['update_product_legal_details'];

		$this->assertFalse( $update->accepts_additional() );

		foreach ( [ 'regular_price', 'sale_price', 'price', 'status', 'name', 'stock_quantity', 'meta_data', 'tax_class', 'tax_status', 'direct_debit', 'differential_taxation', 'photovoltaic_system' ] as $field ) {
			$this->assertNotContains( $field, $update->fields->input, $field );
		}

		$this->assertContains( 'differential_taxation', $update->fields->output, 'still readable' );
	}

	public function test_the_legal_reads_see_what_is_stored_rather_than_the_stores_default(): void {
		foreach ( [ 'get_products_legal_details', 'get_product_legal_details' ] as $name ) {
			$this->assertSame( 'edit', $this->operations()[ $name ]->forced_params['context'], $name );
		}
	}

	/** Germanized clears min_age on any product write that omits it, whoever sends it. */
	public function test_every_tool_writing_through_the_product_routes_keeps_the_age_check(): void {
		$keep = [ RestRequestGuards::KEEP_UNSENT => [ 'min_age' ] ];

		foreach ( [ new ProductDescriptors(), new VariationDescriptors() ] as $provider ) {
			foreach ( $provider->resources() as $resource ) {
				foreach ( $resource->operations as $operation ) {
					if ( Operation::UpdateItem === $operation->operation ) {
						$this->assertSame( $keep[ RestRequestGuards::KEEP_UNSENT ], $operation->forced_params[ RestRequestGuards::KEEP_UNSENT ], $operation->name->value );
					}
				}

				if ( null !== $resource->meta ) {
					$this->assertSame( $keep, $resource->meta_write_params, $resource->id );
				}
			}
		}

		$this->assertSame( $keep[ RestRequestGuards::KEEP_UNSENT ], $this->operations()['update_product_legal_details']->forced_params[ RestRequestGuards::KEEP_UNSENT ] );
	}

	public function test_a_deposit_type_keeps_its_packaging(): void {
		$this->assertSame( [ 'is_packaging', 'packaging', 'packaging_number_of_contents' ], $this->operations()['update_deposit_type']->forced_params[ RestRequestGuards::KEEP_UNSENT ] );
	}

	/** Germanized decrypts bank details into every order and customer response, unless _fields says otherwise. */
	public function test_no_order_or_customer_tool_can_return_bank_details(): void {
		foreach ( ( new StaticDescriptorCatalog() )->resources() as $resource ) {
			if ( 1 !== preg_match( '#/(orders|customers)$#', $resource->collection->path_template() ) ) {
				continue;
			}

			foreach ( $resource->operations as $operation ) {
				$this->assertNotSame( [], $operation->fields->output, $operation->name->value . ' must list its fields' );
				$this->assertNotContains( 'direct_debit', $operation->fields->output, $operation->name->value );
			}
		}
	}

	public function test_no_read_names_bank_details(): void {
		foreach ( $this->operations() as $name => $operation ) {
			$this->assertNotContains( 'direct_debit', $operation->fields->output, $name );
		}
	}

	public function test_every_write_loses_unfiltered_html_and_every_delete_is_gated_and_forced(): void {
		foreach ( $this->operations() as $name => $operation ) {
			if ( Operation::DeleteItem === $operation->operation ) {
				$this->assertTrue( $operation->requires_confirmation, $name );
				$this->assertTrue( $operation->forced_params['force'], $name );
			}

			if ( Operation::CreateItem === $operation->operation || Operation::UpdateItem === $operation->operation ) {
				$this->assertArrayHasKey( RestRequestGuards::NO_UNFILTERED_HTML, $operation->forced_params, $name );
			}
		}
	}

	public function test_what_changes_money_or_legal_statements_needs_confirmation(): void {
		$operations = $this->operations();

		$this->assertTrue( $operations['update_deposit_type']->requires_confirmation );
		$this->assertTrue( $operations['update_product_legal_details']->requires_confirmation );
		$this->assertFalse( $operations['update_unit']->requires_confirmation );
	}

	public function test_it_offers_no_custom_field_tools(): void {
		foreach ( ( new GermanizedDescriptors() )->resources() as $resource ) {
			$this->assertNull( $resource->meta, $resource->id );
		}
	}
}
