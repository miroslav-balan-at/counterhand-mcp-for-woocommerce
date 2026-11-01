<?php

declare( strict_types=1 );

namespace Counterhand\Features\Undo;

use Counterhand\Features\Tokens\Authentication\AuthenticatedAgent;

defined( 'ABSPATH' ) || exit;

interface ChangeRepositoryInterface {

	/** Null unless this connection and this user made it, so one cannot probe another's. */
	public function find( string $key, AuthenticatedAgent $agent ): ?RecordedChange;

	/** @return list<RecordedChange> Newest first, this connection's and this user's only. */
	public function recent( AuthenticatedAgent $agent, int $limit ): array;

	/** False if it was undone already, which is what stops two undos racing. */
	public function claim( string $key ): bool;

	public function release( string $key ): void;
}
