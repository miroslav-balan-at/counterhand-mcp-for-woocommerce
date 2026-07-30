<?php

declare( strict_types=1 );

namespace Counterhand\Shared\Exception;

defined( 'ABSPATH' ) || exit;

/**
 * A tool execution failure with a human/agent-actionable message.
 * Rendered as an MCP tool result with isError=true, never a protocol error.
 */
final class ToolCallException extends \RuntimeException {
}
