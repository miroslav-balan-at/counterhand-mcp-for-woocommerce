<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Tokens\Domain;

use Counterhand\Features\Tokens\Domain\ApiScope;
use Counterhand\Features\Tokens\Domain\GrantedScopeSet;
use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\Tokens\Domain\IntegrationScope;
use Counterhand\Features\Tokens\Domain\ScopeGroups;
use Counterhand\Features\Tokens\Domain\ScopeSummary;
use Counterhand\Shared\Tool\ToolGroup;
use Counterhand\Shared\Tool\ToolSection;
use Counterhand\Tests\Unit\TestCase;

final class IntegrationScopeTest extends TestCase {

	public function test_a_scope_round_trips_through_its_own_string(): void {
		foreach ( [ 'integration:yoast-seo:read', 'integration:yoast-seo:write' ] as $value ) {
			$this->assertSame( $value, IntegrationScope::try_from_string( $value )?->key() );
		}
	}

	/** @dataProvider malformed */
	public function test_anything_malformed_is_dropped( string $value ): void {
		$this->assertNull( IntegrationScope::try_from_string( $value ) );
	}

	/** @return iterable<string, array{string}> */
	public static function malformed(): iterable {
		yield 'no prefix' => [ 'yoast-seo:read' ];
		yield 'no slug' => [ 'integration::read' ];
		yield 'unknown axis' => [ 'integration:yoast-seo:admin' ];
		yield 'no axis' => [ 'integration:yoast-seo' ];
		yield 'capitals' => [ 'integration:Yoast:read' ];
		yield 'nested' => [ 'integration:a:b:read' ];
	}

	public function test_a_grant_for_one_plugin_does_not_contain_another(): void {
		$set = GrantedScopeSet::from_csv( 'integration:yoast-seo:read,products:read,integration:broken' );

		$this->assertSame( 'integration:yoast-seo:read,products:read', $set->to_csv() );
		$this->assertTrue( $set->contains( IntegrationGroup::try_of( 'yoast-seo' )->read_scope() ) );
		$this->assertTrue( $set->contains( ApiScope::ProductsRead ) );
		$this->assertFalse( $set->contains( IntegrationGroup::try_of( 'yoast-seo' )->write_scope() ) );
		$this->assertFalse( $set->contains( IntegrationGroup::try_of( 'wpforms' )->read_scope() ) );
	}

	public function test_a_summary_shows_an_integration_after_the_shipped_groups_and_collapses_its_axes(): void {
		$summary = ScopeSummary::of( GrantedScopeSet::from_csv( 'integration:contact-form-7:write,integration:contact-form-7:read,products:read' ) );

		$this->assertSame( [ 'Products', 'Contact Form 7' ], $summary->labels() );
		$this->assertTrue( $summary->grants[1]->writable );
	}

	public function test_an_integration_ships_off_and_collapsed_and_is_always_logged(): void {
		$group = IntegrationGroup::try_of( 'wpforms' );

		$this->assertFalse( $group->enabled_by_default() );
		$this->assertFalse( $group->in_chat_by_default() );
		$this->assertTrue( $group->is_always_logged() );
		$this->assertTrue( $group->section()->is_advanced() );
	}

	public function test_a_client_naming_no_scopes_is_never_offered_an_integration(): void {
		\Brain\Monkey\Filters\expectApplied( ScopeGroups::INTEGRATIONS_FILTER )->never();

		foreach ( ApiScope::conservative_default() as $scope ) {
			$this->assertNotInstanceOf( IntegrationScope::class, $scope );
		}
	}

	public function test_the_section_lists_what_the_filter_publishes_and_drops_anything_else(): void {
		$yoast = IntegrationGroup::try_of( 'yoast-seo' );
		\Brain\Monkey\Filters\expectApplied( ScopeGroups::INTEGRATIONS_FILTER )->andReturn( [ $yoast, 'not a group' ] );

		$this->assertSame( [ $yoast ], ToolSection::Integrations->groups() );
	}

	public function test_a_stored_chat_selection_resolves_either_kind_of_group(): void {
		$this->assertSame( ToolGroup::Products, ScopeGroups::try_from( 'products' ) );
		$this->assertSame( 'integration:wpforms', ScopeGroups::try_from( 'integration:wpforms' )?->key() );
		$this->assertNull( ScopeGroups::try_from( 'gone' ) );
	}

	public function test_only_the_settings_keys_of_an_integration_qualify(): void {
		$this->assertTrue( IntegrationGroup::is_option_key( 'integration_yoast-seo_read' ) );
		$this->assertTrue( IntegrationGroup::is_option_key( 'integration_yoast-seo_write' ) );
		$this->assertFalse( IntegrationGroup::is_option_key( 'enabled' ) );
		$this->assertFalse( IntegrationGroup::is_option_key( 'integration__read' ) );
		$this->assertFalse( IntegrationGroup::is_option_key( 'integration_yoast-seo_admin' ) );
	}

	public function test_a_plugin_named_by_two_sources_is_one_row_and_keeps_its_write_switch(): void {
		\Brain\Monkey\Filters\expectApplied( ScopeGroups::INTEGRATIONS_FILTER )->andReturn(
			[ IntegrationGroup::try_of( 'yoast-seo', false ), IntegrationGroup::try_of( 'yoast-seo' ), IntegrationGroup::try_of( 'yoast-seo', false ) ]
		);

		$groups = ScopeGroups::integrations();

		$this->assertCount( 1, $groups );
		$this->assertTrue( $groups[0]->has_write() );
	}

	public function test_the_owning_feature_supplies_the_consent_wording(): void {
		$scope = IntegrationGroup::of( 'flamingo', copy: new \Counterhand\Features\Flamingo\FlamingoCopy() )->read_scope();

		$this->assertStringContainsString( 'email addresses', $scope->description() );
	}

	public function test_a_malformed_slug_in_code_is_a_programming_error(): void {
		$this->expectException( \InvalidArgumentException::class );

		IntegrationGroup::of( 'Not A Slug' );
	}

	public function test_groups_come_back_in_name_order_whichever_source_named_them_first(): void {
		\Brain\Monkey\Filters\expectApplied( ScopeGroups::INTEGRATIONS_FILTER )->andReturn( [ IntegrationGroup::of( 'yoast-seo' ), IntegrationGroup::of( 'flamingo' ) ] );

		$this->assertSame(
			[ 'integration:flamingo', 'integration:yoast-seo' ],
			array_map( static fn ( $group ): string => $group->key(), ScopeGroups::integrations() )
		);
	}

	public function test_a_plugin_that_is_off_is_not_named_to_an_agent(): void {
		$summary = ScopeSummary::of( GrantedScopeSet::from_csv( 'products:read,integration:wpforms:read' ) );

		$this->assertSame( [ 'Products' ], $summary->live_labels() );
		$this->assertCount( 2, $summary->grants, 'the person reviewing the grant still sees it' );
	}

	public function test_the_first_source_keeps_its_wording_unless_only_a_later_one_offers_writes(): void {
		$first = IntegrationGroup::of( 'acf' );
		\Brain\Monkey\Filters\expectApplied( ScopeGroups::INTEGRATIONS_FILTER )->andReturn( [ $first, IntegrationGroup::of( 'acf' ) ] );

		$this->assertSame( [ $first ], ScopeGroups::integrations() );
	}
}
