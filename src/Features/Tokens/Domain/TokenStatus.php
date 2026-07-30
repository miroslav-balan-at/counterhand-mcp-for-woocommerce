<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

enum TokenStatus: string {
	case Active  = 'active';
	case Revoked = 'revoked';
	case Expired = 'expired';
}
