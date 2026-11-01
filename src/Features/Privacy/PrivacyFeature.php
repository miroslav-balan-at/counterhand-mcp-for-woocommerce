<?php

declare( strict_types=1 );

namespace Counterhand\Features\Privacy;

use Counterhand\Shared\FeatureInterface;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress's privacy tools: suggested policy text, and export and erasure on request.
 *
 * The undo history is the one store of personal data tied to a user here, as what they
 * changed and when. The action log masks people's details and names only the connection,
 * so no email address leads to a row in it.
 */
final readonly class PrivacyFeature implements FeatureInterface {

	private const SLUG = 'counterhand-mcp-for-woocommerce';

	public function __construct( private ChangeHistoryInterface $history ) {}

	public function register(): void {
		add_action( 'admin_init', [ $this, 'suggest_policy_text' ] );
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'with_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'with_eraser' ] );
	}

	public function suggest_policy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		wp_add_privacy_policy_content(
			__( 'Counterhand MCP for WooCommerce', 'counterhand-mcp-for-woocommerce' ),
			wp_kses_post(
				wpautop(
					__( 'AI assistants you connect to this store can read and change the data you grant them, which can include customers\' names, addresses, orders and messages. That data goes to the assistant\'s provider under your agreement with them. If you use the built-in chat, store data you ask about is sent to the AI provider you configured. A log of assistant actions, with names, addresses, email addresses and phone numbers masked, is kept if you switch it on, and always for maintenance, settings, payment and refund actions. If you grant undo history, the previous values of changed catalog, content, shipping and tax records are kept for 30 days. Check whether you need a data processing agreement with your AI providers, and describe this use in your own words.', 'counterhand-mcp-for-woocommerce' )
				)
			)
		);
	}

	/**
	 * @param  array<string, array<string, mixed>> $exporters
	 * @return array<string, array<string, mixed>>
	 */
	public function with_exporter( array $exporters ): array {
		$exporters[ self::SLUG ] = [
			'exporter_friendly_name' => __( 'Counterhand undo history', 'counterhand-mcp-for-woocommerce' ),
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
			'eraser_friendly_name' => __( 'Counterhand undo history', 'counterhand-mcp-for-woocommerce' ),
			'callback'             => [ $this, 'erase' ],
		];

		return $erasers;
	}

	/** @return array{data: list<array<string, mixed>>, done: bool} */
	public function export( string $email ): array {
		$user = get_user_by( 'email', $email );
		$data = [];

		foreach ( $user instanceof \WP_User ? $this->history->made_by( $user->ID ) : [] as $change ) {
			$data[] = [
				'group_id'    => self::SLUG,
				'group_label' => __( 'Changes made through an AI connection', 'counterhand-mcp-for-woocommerce' ),
				'item_id'     => 'counterhand-change-' . $change->key,
				'data'        => [
					[
						'name'  => __( 'Tool', 'counterhand-mcp-for-woocommerce' ),
						'value' => $change->tool_name,
					],
					[
						'name'  => __( 'Made at', 'counterhand-mcp-for-woocommerce' ),
						'value' => $change->created_at,
					],
					[
						'name'  => __( 'Fields', 'counterhand-mcp-for-woocommerce' ),
						'value' => implode( ', ', $change->fields ),
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
		$user    = get_user_by( 'email', $email );
		$removed = $user instanceof \WP_User && $this->history->forget( $user->ID ) > 0;

		return [
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => [],
			'done'           => true,
		];
	}
}
