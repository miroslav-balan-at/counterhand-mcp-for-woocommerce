<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Something a token can be granted: a shipped ApiScope, or an IntegrationScope
 * for a plugin this code has never heard of.
 */
interface ScopeInterface {

	/** "<group>:<axis>", as persisted on the token row. */
	public function key(): string;

	public function is_write(): bool;

	public function group(): ScopeGroupInterface;

	public function label(): string;

	public function description(): string;
}
