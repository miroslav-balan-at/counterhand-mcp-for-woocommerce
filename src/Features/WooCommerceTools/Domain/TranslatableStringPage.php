<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

final readonly class TranslatableStringPage {

	/** @param list<TranslatableString> $strings */
	public function __construct(
		public array $strings,
		public int $total,
	) {}
}
