<?php

declare( strict_types=1 );

namespace Counterhand\Shared\Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Any authentication failure. One generic message for every cause —
 * never reveals whether the token id, secret, status or owner failed.
 */
final class AuthenticationFailedException extends \RuntimeException {

	public function __construct() {
		parent::__construct( 'Invalid or missing API token.' );
	}
}
