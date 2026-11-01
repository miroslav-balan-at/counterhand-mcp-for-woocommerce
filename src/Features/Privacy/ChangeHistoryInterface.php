<?php

declare( strict_types=1 );

namespace Counterhand\Features\Privacy;

defined( 'ABSPATH' ) || exit;

interface ChangeHistoryInterface {

	/** @return list<PersonalChange> */
	public function made_by( int $user_id ): array;

	/** How many records were removed. */
	public function forget( int $user_id ): int;
}
