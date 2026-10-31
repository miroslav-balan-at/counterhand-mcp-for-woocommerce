<?php

declare( strict_types=1 );

namespace Counterhand\Features\Flamingo;

defined( 'ABSPATH' ) || exit;

/**
 * Where a message sits, as Flamingo's own admin screen names it.
 */
enum InboundMessageStatus: string {
	case Inbox = 'inbox';
	case Spam  = 'spam';
	case Trash = 'trash';

	public function post_status(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- $this is valid in enum methods (PHP 8.1+), sniff false positive.
			self::Inbox => 'publish',
			self::Spam  => 'flamingo-spam',
			self::Trash => 'trash',
		};
	}

	/** A status Flamingo has no name for reads as the inbox, its default. */
	public static function of_post_status( string $post_status ): self {
		foreach ( self::cases() as $status ) {
			if ( $status->post_status() === $post_status ) {
				return $status;
			}
		}

		return self::Inbox;
	}
}
