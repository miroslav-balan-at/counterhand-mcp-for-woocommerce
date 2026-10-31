<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * What a person reads about an integration, supplied by the feature that knows
 * the plugin. Methods rather than values: integrations are built at
 * plugins_loaded, before translations may load.
 */
interface IntegrationCopyInterface {

	public function name(): string;

	/** One line on the settings row. */
	public function description(): string;

	/** The consequence of granting reads, for the consent screen. */
	public function read_description(): string;

	/** The consequence of granting writes, for the consent screen. */
	public function write_description(): string;
}
