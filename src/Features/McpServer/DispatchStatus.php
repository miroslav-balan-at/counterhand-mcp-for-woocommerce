<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

defined( 'ABSPATH' ) || exit;

/**
 * How one in-process tool call ended.
 *
 * Rejected and Failed are distinct because the protocol layer treats them
 * differently: a rejected call never reached a tool and maps to a JSON-RPC
 * error, a failed call is a real tool call and maps to an isError result the
 * agent can read and self-correct from.
 */
enum DispatchStatus: string {

	case Succeeded = 'succeeded';

	/** Refused before any tool ran: unknown name, or arguments the schema rejects. */
	case Rejected = 'rejected';

	/** A tool ran and failed — or refused — with a message written for the agent. */
	case Failed = 'failed';
}
