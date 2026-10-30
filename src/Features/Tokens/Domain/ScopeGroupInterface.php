<?php

declare( strict_types=1 );

namespace Counterhand\Features\Tokens\Domain;

use Counterhand\Shared\Tool\ToolSection;

defined( 'ABSPATH' ) || exit;

/**
 * A family of scopes switched on or off as a unit: a shipped ToolGroup, or an
 * IntegrationGroup for a plugin discovered at runtime.
 */
interface ScopeGroupInterface {

	/** What the group's scopes are prefixed with. */
	public function key(): string;

	public function label(): string;

	public function description(): string;

	public function noun(): string;

	public function section(): ToolSection;

	public function read_scope(): ScopeInterface;

	public function write_scope(): ?ScopeInterface;

	public function has_write(): bool;

	public function read_option_key(): string;

	public function write_option_key(): string;

	public function enabled_by_default(): bool;

	public function is_always_logged(): bool;

	public function in_chat_by_default(): bool;
}
