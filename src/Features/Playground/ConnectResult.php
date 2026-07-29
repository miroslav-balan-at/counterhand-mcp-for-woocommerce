<?php

declare( strict_types=1 );

namespace AgentGateMcp\Features\Playground;

defined( 'ABSPATH' ) || exit;

/** Outcome of a model-connect attempt, carried across the post-redirect-get hop. */
final readonly class ConnectResult {

	public function __construct(
		public bool $ok,
		public string $message,
	) {}

	/** @return array{ok: bool, message: string} */
	public function to_array(): array {
		return [
			'ok'      => $this->ok,
			'message' => $this->message,
		];
	}

	public static function from_array( mixed $data ): ?self {
		if ( ! is_array( $data ) || ! isset( $data['message'] ) ) {
			return null;
		}

		return new self( (bool) ( $data['ok'] ?? false ), (string) $data['message'] );
	}
}
