<?php

declare( strict_types=1 );

namespace AgentGateMcp\Shared\JsonRpc;

defined( 'ABSPATH' ) || exit;

final class JsonRpcEnvelopeException extends \RuntimeException {

	public function __construct(
		public readonly JsonRpcErrorCode $error_code,
		string $message,
	) {
		parent::__construct( $message );
	}
}
