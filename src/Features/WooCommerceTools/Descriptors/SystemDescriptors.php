<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\WooCommerceTools\Domain\DescriptorProvider;
use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\SystemToolPolicy;
use Counterhand\Features\WooCommerceTools\Domain\ToolName;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;
use Counterhand\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

/**
 * The environment report and WooCommerce's maintenance tools.
 *
 * The most dangerous group in the plugin, and the one where WooCommerce's own
 * permission check helps least: everything here is gated by manage_woocommerce,
 * which every token owner already holds. Four things stand between an agent and
 * a destroyed store, and only the first is WooCommerce's:
 *
 * 1. The group ships disabled, sits in ToolSection::Advanced, and its write
 *    axis is a separate scope a store owner has to grant deliberately.
 * 2. run_system_status_tool publishes a required `confirm` argument.
 * 3. SystemToolPolicy refuses the tool ids that cannot be undone.
 * 4. Calls are written to the action log whatever the logging setting says.
 *
 * The environment report is worth having despite the company it keeps: it is
 * how an agent answers "why is this failing" without a human pasting a wall of
 * version numbers.
 */
final readonly class SystemDescriptors implements DescriptorProvider {

	/**
	 * The report's top-level sections, minus the ones an agent cannot use.
	 *
	 * Pruning stops at the top level, which is worth being clear about rather
	 * than implying otherwise: `_fields` selects whole sections, so `environment`
	 * arrives complete, absolute log paths and all. Anyone holding system:read
	 * could read the same report in wp-admin, so this is about payload size and
	 * usefulness rather than concealment — and the group ships disabled behind
	 * the Advanced heading precisely because the report is not for everyone.
	 */
	private const STATUS_FIELDS = [
		'environment',
		'database',
		'active_plugins',
		'inactive_plugins',
		'dropins_mu_plugins',
		'theme',
		'settings',
		'security',
		'pages',
	];

	private const TOOL_FIELDS = [ 'id', 'name', 'action', 'description', 'success', 'message' ];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			$this->status(),
			$this->tools(),
		];
	}

	/** A singleton: one report, no id. Collection and item are the same path. */
	private function status(): ResourceDescriptor {
		$route = RestRoute::wc( '/system_status' );

		return new ResourceDescriptor(
			'system_status',
			ToolGroup::System,
			$route,
			$route,
			'system_status',
			'system_statuses',
			[
				new OperationDescriptor(
					ToolName::from( 'get_system_status' ),
					Operation::GetItem,
					new FieldProfile( [], self::STATUS_FIELDS, false ),
					'',
					'The store\'s environment report: WordPress and WooCommerce versions, PHP and database details, active plugins and theme. This is what WooCommerce support asks for, and the first thing to read when something behaves unexpectedly. Takes no arguments and changes nothing.'
				),
			]
		);
	}

	private function tools(): ResourceDescriptor {
		return new ResourceDescriptor(
			'system_status_tools',
			ToolGroup::System,
			RestRoute::wc( '/system_status/tools' ),
			RestRoute::wc( '/system_status/tools/{id}' ),
			'system_tool',
			'system_tools',
			[
				new OperationDescriptor(
					ToolName::from( 'get_system_status_tools' ),
					Operation::GetItems,
					new FieldProfile( [], self::TOOL_FIELDS, false ),
					'',
					'The maintenance tools this store offers, including any its plugins added. Listing them changes nothing. Several of them cannot be undone and are refused if you try to run them; the listing does not mark which, so read each description before proposing one.'
				),
				new OperationDescriptor(
					ToolName::from( 'get_system_status_tool' ),
					Operation::GetItem,
					new FieldProfile( [], self::TOOL_FIELDS, false ),
					'Describes one maintenance tool without running it.'
				),
				new OperationDescriptor(
					ToolName::from( 'run_system_status_tool' ),
					Operation::UpdateItem,
					new FieldProfile( [], self::TOOL_FIELDS, false ),
					'',
					'SAFETY: this runs a WooCommerce maintenance routine against the live store, immediately. Read the tool\'s own description with get_system_status_tool, tell the user in plain words what it will do, and only then call this with confirm set to true. Routines that cannot be undone — resetting user roles, deleting tax rates, dropping order tables, running the database migration — are refused outright whatever you pass.',
					[],
					[],
					true,
					new SystemToolPolicy()
				),
			],
			null,
			// WooCommerce serves no POST on /system_status/tools, and the item
			// route's check is wc_rest_check_manager_permissions() — a bare
			// capability test that reads no id — so asking it is safe.
			RestRoute::wc( '/system_status/tools/{id}' )
		);
	}
}
