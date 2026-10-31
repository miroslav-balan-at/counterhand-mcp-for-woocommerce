<?php

declare( strict_types=1 );

namespace Counterhand\Features\Flamingo;

defined( 'ABSPATH' ) || exit;

/**
 * Marking spam is deliberately absent: Flamingo's spam() submits the message to
 * Akismet, and sending someone's personal data to a third party should not be a
 * side effect of tidying an inbox.
 */
enum InboundMessageOperation: string {
	case List  = 'list_inbound_messages';
	case Get   = 'get_inbound_message';
	case Trash = 'trash_inbound_message';
}
