<?php

declare( strict_types=1 );

namespace Counterhand\Features\Flamingo;

defined( 'ABSPATH' ) || exit;

/**
 * Flamingo's messages, through its own class so its behaviour stays its own.
 *
 * Capabilities come off the post type: Flamingo maps its own names to whatever
 * the site configured, and a literal here would be a second copy of that.
 */
final readonly class FlamingoInbox implements InboxInterface {

	private const POST_TYPE = 'flamingo_inbound';

	private const MINIMUM_VERSION = '2.6.3';

	private const SEARCHED_META = [ '_subject', '_from', '_from_email', '_from_name' ];

	public function is_installed(): bool {
		// Before 2.6.3 its post type had generic capabilities, so a site's tightening of
		// who may read messages was not on the post type this reads from.
		return class_exists( \Flamingo_Inbound_Message::class )
			&& defined( 'FLAMINGO_VERSION' )
			&& version_compare( (string) constant( 'FLAMINGO_VERSION' ), self::MINIMUM_VERSION, '>=' );
	}

	public function page( InboundMessageStatus $status, int $per_page, int $page, string $search ): InboundMessagePage {
		$messages = \Flamingo_Inbound_Message::find(
			[
				'posts_per_page' => $per_page,
				'offset'         => ( $page - 1 ) * $per_page,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'post_status'    => $status->post_status(),
				...$this->search_arguments( $search ),
			]
		);

		return new InboundMessagePage(
			array_map( [ $this, 'value_of' ], $messages ),
			\Flamingo_Inbound_Message::count()
		);
	}

	public function find( int $id ): ?InboundMessage {
		return self::POST_TYPE === get_post_type( $id )
			? $this->value_of( new \Flamingo_Inbound_Message( $id ) )
			: null;
	}

	public function status_of( int $id ): ?InboundMessageStatus {
		return self::POST_TYPE === get_post_type( $id )
			? InboundMessageStatus::of_post_status( (string) get_post_status( $id ) )
			: null;
	}

	public function trash( int $id ): bool {
		return true === ( new \Flamingo_Inbound_Message( $id ) )->trash();
	}

	public function user_can_list(): bool {
		$capabilities = $this->capabilities();

		return null !== $capabilities && current_user_can( $capabilities->edit_posts );
	}

	public function user_can_read( int $id ): bool {
		$capabilities = $this->capabilities();

		return null !== $capabilities && current_user_can( $capabilities->read_post, $id );
	}

	public function user_can_trash( int $id ): bool {
		$capabilities = $this->capabilities();

		return null !== $capabilities && current_user_can( $capabilities->delete_post, $id );
	}

	/**
	 * Not WordPress's own search: Flamingo stores the IP and user agent in
	 * post_content, so a search over it would answer which messages came from an
	 * address this feature otherwise withholds.
	 *
	 * @return array<string, mixed>
	 */
	private function search_arguments( string $search ): array {
		if ( '' === $search ) {
			return [];
		}

		$clauses = array_map(
			static fn ( string $key ): array => [
				'key'     => $key,
				'value'   => $search,
				'compare' => 'LIKE',
			],
			self::SEARCHED_META
		);

		return [
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- an explicit search over a small plugin table.
			'meta_query' => [
				'relation' => 'OR',
				...$clauses,
			],
		];
	}

	private function capabilities(): ?object {
		return get_post_type_object( self::POST_TYPE )?->cap;
	}

	private function value_of( \Flamingo_Inbound_Message $message ): InboundMessage {
		$id = (int) $message->id();

		return new InboundMessage(
			$id,
			(string) $message->subject,
			(string) $message->from_name,
			(string) $message->from_email,
			(string) $message->channel,
			(string) get_post_field( 'post_date_gmt', $id ),
			InboundMessageStatus::of_post_status( (string) get_post_status( $id ) ),
			// An absent meta value reads as '', and casting that would invent an entry.
			is_array( $message->fields ) ? $message->fields : [],
			is_array( $message->consent ) ? $message->consent : [],
		);
	}
}
