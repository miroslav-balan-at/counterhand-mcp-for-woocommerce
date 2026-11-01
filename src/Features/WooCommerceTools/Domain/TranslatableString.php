<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

final readonly class TranslatableString {

	public function __construct(
		public int $id,
		public string $context,
		public string $name,
		public string $value,
		public string $language,
		public ?string $translation,
	) {}

	/** @return array<string, mixed> */
	public function to_array(): array {
		return [
			'id'          => $this->id,
			'context'     => $this->context,
			'name'        => $this->name,
			'value'       => $this->value,
			'language'    => $this->language,
			'translation' => $this->translation,
		];
	}
}
