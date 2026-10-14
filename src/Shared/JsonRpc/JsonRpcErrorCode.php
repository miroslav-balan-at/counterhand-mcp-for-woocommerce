<?php

declare( strict_types=1 );

namespace Counterhand\Shared\JsonRpc;

defined( 'ABSPATH' ) || exit;

enum JsonRpcErrorCode: int {
	case ParseError     = -32700;
	case InvalidRequest = -32600;
	case MethodNotFound = -32601;
	case InvalidParams  = -32602;
	case InternalError  = -32603;

	/** MCP 2026-07-28: the mirrored HTTP headers disagree with the body, or are missing. */
	case HeaderMismatch = -32020;

	/** MCP 2026-07-28: the request names a protocol version this server does not serve. */
	case UnsupportedProtocolVersion = -32022;
}
