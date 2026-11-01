<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

defined( 'ABSPATH' ) || exit;

/** Which record a call touches and which of its fields, which is all an undo needs to know. */
final readonly class ChangePlan {

	/**
	 * @param array<string, mixed> $path   Route parameters naming the record.
	 * @param list<string>         $fields The fields the call sends.
	 */
	public function __construct(
		public ChangeKind $kind,
		public array $path,
		public array $fields,
	) {}

	/** @param list<string> $fields */
	public function with_fields( array $fields ): self {
		return new self( $this->kind, $this->path, $fields );
	}

	public function naming( int $id ): self {
		return new self(
			$this->kind,
			[
				...$this->path,
				'id' => $id,
			],
			$this->fields
		);
	}
}
