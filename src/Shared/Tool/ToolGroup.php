<?php

declare( strict_types=1 );

namespace AgentGateMcp\Shared\Tool;

defined( 'ABSPATH' ) || exit;

enum ToolGroup: string {
	case Products  = 'products';
	case Orders    = 'orders';
	case Customers = 'customers';
	case Reports   = 'reports';
}
