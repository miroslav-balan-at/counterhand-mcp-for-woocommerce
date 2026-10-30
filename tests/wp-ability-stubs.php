<?php

declare( strict_types=1 );

class WP_Error {

	public function __construct( private string $code = '', private string $message = '' ) {}

	public function get_error_code(): string {
		return $this->code;
	}

	public function get_error_message(): string {
		return $this->message;
	}
}

/**
 * Just enough of core's WP_Ability for the bridge's unit tests. What core really
 * does with these is covered by tests/e2e/specs/abilities.spec.ts.
 */
class WP_Ability {

	/** @var array<int, mixed> */
	public array $executed_with = [];

	/**
	 * @param array<string, mixed> $input_schema
	 * @param array<string, mixed> $meta
	 */
	public function __construct(
		private string $name,
		private array $input_schema = [],
		private array $meta = [],
		private mixed $result = [ 'ok' => true ],
		private mixed $permission = true,
	) {}

	public function get_name(): string {
		return $this->name;
	}

	public function get_description(): string {
		return 'Fixture ' . $this->name;
	}

	/** @return array<string, mixed> */
	public function get_input_schema(): array {
		return $this->input_schema;
	}

	/** @return array<string, mixed> */
	public function get_meta(): array {
		return $this->meta;
	}

	public function check_permissions( mixed $input = null ): mixed {
		if ( $this->permission instanceof \Throwable ) {
			throw $this->permission;
		}

		return $this->permission;
	}

	public function execute( mixed $input = null ): mixed {
		$this->executed_with[] = $input;

		return $this->result;
	}
}
