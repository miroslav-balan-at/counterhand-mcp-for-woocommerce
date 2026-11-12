<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Rewrites;

defined( 'ABSPATH' ) || exit;

/** A rewrite job keeps the instructions a person gave for 30 days, so WordPress's export and erase tools reach it. */
final readonly class RewritePrivacy {

	private const SLUG = 'counterhand-rewrites';

	public function __construct( private RewriteQueueInterface $queue ) {}

	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'with_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'with_eraser' ] );
	}

	/**
	 * @param  array<string, array<string, mixed>> $exporters
	 * @return array<string, array<string, mixed>>
	 */
	public function with_exporter( array $exporters ): array {
		$exporters[ self::SLUG ] = [
			'exporter_friendly_name' => __( 'Counterhand rewrites', 'counterhand-mcp-for-woocommerce' ),
			'callback'               => [ $this, 'export' ],
		];

		return $exporters;
	}

	/**
	 * @param  array<string, array<string, mixed>> $erasers
	 * @return array<string, array<string, mixed>>
	 */
	public function with_eraser( array $erasers ): array {
		$erasers[ self::SLUG ] = [
			'eraser_friendly_name' => __( 'Counterhand rewrites', 'counterhand-mcp-for-woocommerce' ),
			'callback'             => [ $this, 'erase' ],
		];

		return $erasers;
	}

	/** @return array{data: list<array<string, mixed>>, done: bool} */
	public function export( string $email ): array {
		$user = get_user_by( 'email', $email );
		$data = [];

		foreach ( $user instanceof \WP_User ? $this->queue->made_by( $user->ID ) : [] as $item ) {
			$data[] = [
				'group_id'    => self::SLUG,
				'group_label' => __( 'Product texts rewritten by AI', 'counterhand-mcp-for-woocommerce' ),
				'item_id'     => 'counterhand-rewrite-' . $item->id,
				'data'        => [
					[
						'name'  => __( 'Product', 'counterhand-mcp-for-woocommerce' ),
						'value' => $item->product_name,
					],
					[
						'name'  => __( 'Instructions', 'counterhand-mcp-for-woocommerce' ),
						'value' => $item->instruction,
					],
					[
						'name'  => __( 'Made at', 'counterhand-mcp-for-woocommerce' ),
						'value' => $item->created_at->format( 'Y-m-d H:i:s' ),
					],
				],
			];
		}

		return [
			'data' => $data,
			'done' => true,
		];
	}

	/** @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool} */
	public function erase( string $email ): array {
		$user = get_user_by( 'email', $email );

		return [
			'items_removed'  => $user instanceof \WP_User && $this->queue->forget_user( $user->ID ) > 0,
			'items_retained' => false,
			'messages'       => [],
			'done'           => true,
		];
	}
}
