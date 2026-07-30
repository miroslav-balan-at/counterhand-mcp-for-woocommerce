<?php

declare( strict_types=1 );

namespace Counterhand\Features\Settings;

defined( 'ABSPATH' ) || exit;

/** Outcome of the Connect tab's readiness check. */
final readonly class ReadinessReport {

	public function __construct(
		public ReadinessStatus $status,
		public string $message,
		public string $detail = '',
	) {}

	/** @return array{status: string, message: string, detail: string} */
	public function to_array(): array {
		return [
			'status'  => $this->status->value,
			'message' => $this->message,
			'detail'  => $this->detail,
		];
	}
}
