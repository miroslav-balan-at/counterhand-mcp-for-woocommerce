<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Infrastructure;

use Counterhand\Features\WooCommerceTools\Descriptors\AcfDescriptors;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRequestGuards;
use Counterhand\Tests\Unit\TestCase;

final class RestRequestGuardsTest extends TestCase {

	public function test_password_fields_are_dropped_and_the_rest_kept(): void {
		$fields = ( new RestRequestGuards() )->without_passwords(
			[
				[
					'name' => 'care_notes',
					'type' => 'text',
				],
				[
					'name' => 'api_secret',
					'type' => 'password',
				],
			]
		);

		$this->assertSame( [ 'care_notes' ], array_column( $fields, 'name' ) );
	}

	/** A group writes its sub-fields one by one, so the top-level filter never sees them. */
	public function test_writing_a_password_field_is_skipped_and_anything_else_is_not(): void {
		$guards = new RestRequestGuards();

		$this->assertTrue( $guards->keep_stored_password( null, 'changed', 5, [ 'type' => 'password' ] ) );
		$this->assertNull( $guards->keep_stored_password( null, 'changed', 5, [ 'type' => 'text' ] ) );
	}

	public function test_every_acf_operation_forces_the_marker(): void {
		foreach ( ( new AcfDescriptors() )->resources() as $resource ) {
			foreach ( $resource->operations as $operation ) {
				$this->assertArrayHasKey( RestRequestGuards::WITHHOLD_PASSWORDS, $operation->forced_params, $operation->name->value );
			}
		}
	}

	public function test_only_unfiltered_html_is_denied(): void {
		$guards = new RestRequestGuards();

		$this->assertSame( [ 'do_not_allow' ], $guards->deny_unfiltered_html( [ 'unfiltered_html' ], 'unfiltered_html' ) );
		$this->assertSame( [ 'edit_posts' ], $guards->deny_unfiltered_html( [ 'edit_posts' ], 'edit_posts' ) );
	}

	public function test_every_write_to_contact_forms_and_custom_fields_loses_unfiltered_html(): void {
		$providers = [ new AcfDescriptors(), new \Counterhand\Features\WooCommerceTools\Descriptors\ContactForm7Descriptors() ];

		foreach ( $providers as $provider ) {
			foreach ( $provider->resources() as $resource ) {
				foreach ( $resource->operations as $operation ) {
					if ( \Counterhand\Features\WooCommerceTools\Domain\Operation::UpdateItem === $operation->operation || \Counterhand\Features\WooCommerceTools\Domain\Operation::CreateItem === $operation->operation ) {
						$this->assertArrayHasKey( RestRequestGuards::NO_UNFILTERED_HTML, $operation->forced_params, $operation->name->value );
					}
				}
			}
		}
	}
}
