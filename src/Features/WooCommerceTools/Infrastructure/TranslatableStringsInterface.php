<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Infrastructure;

use Counterhand\Features\WooCommerceTools\Domain\TranslatableString;
use Counterhand\Features\WooCommerceTools\Domain\TranslatableStringPage;

defined( 'ABSPATH' ) || exit;

/** WPML String Translation, limited to what a token may see. */
interface TranslatableStringsInterface {

	public function is_installed(): bool;

	public function user_can_manage(): bool;

	public function page( string $language, string $search, bool $only_missing, int $per_page, int $page ): TranslatableStringPage;

	/** Null for an id that does not exist or is out of reach, so the two cannot be told apart. */
	public function find( int $id, string $language ): ?TranslatableString;

	public function is_active_language( string $language ): bool;

	public function save( int $id, string $language, string $value ): bool;
}
