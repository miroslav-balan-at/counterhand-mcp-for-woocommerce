<?php

declare( strict_types=1 );

namespace Counterhand\Features\Flamingo;

defined( 'ABSPATH' ) || exit;

interface InboxInterface {

	public function is_installed(): bool;

	public function page( InboundMessageStatus $status, int $per_page, int $page, string $search ): InboundMessagePage;

	/** Null for any id that is not an inbound message, whatever else it may be. */
	public function find( int $id ): ?InboundMessage;

	/** Without loading the message. Null on the same terms as find(). */
	public function status_of( int $id ): ?InboundMessageStatus;

	public function trash( int $id ): bool;

	public function user_can_list(): bool;

	public function user_can_read( int $id ): bool;

	public function user_can_trash( int $id ): bool;
}
