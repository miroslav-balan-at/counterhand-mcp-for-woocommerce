<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

defined( 'ABSPATH' ) || exit;

/** A tool where only some calls need a person: a price on a product, not its description. */
interface ConditionalConfirmationInterface {

	/** @return list<string> Arguments whose presence, unless false, makes a call need confirmation. */
	public function fields_needing_confirmation(): array;
}
