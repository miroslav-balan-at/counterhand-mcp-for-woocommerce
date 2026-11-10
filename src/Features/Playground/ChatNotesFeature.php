<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

use Counterhand\Features\Settings\AdminScreen;
use Counterhand\Features\Settings\SettingsAccess;
use Counterhand\Shared\FeatureInterface;

defined( 'ABSPATH' ) || exit;

/** The chat's store memory and each person's saved prompts: what is kept between conversations. */
final readonly class ChatNotesFeature implements FeatureInterface {

	public const MEMORY_ACTION = 'counterhand_store_memory';
	public const PROMPTS_NONCE = 'counterhand_saved_prompts';
	public const SAVE_PROMPT   = 'counterhand_chat_save_prompt';
	public const FORGET_PROMPT = 'counterhand_chat_forget_prompt';

	public function __construct(
		private StoreMemory $memory,
		private SavedPrompts $prompts,
	) {}

	public function register(): void {
		add_action( 'admin_post_' . self::MEMORY_ACTION, [ $this, 'handle_memory' ] );
		add_action( 'wp_ajax_' . self::SAVE_PROMPT, [ $this, 'handle_save_prompt' ] );
		add_action( 'wp_ajax_' . self::FORGET_PROMPT, [ $this, 'handle_forget_prompt' ] );
	}

	/** Administrators only: the notes steer every chat on the store, a shop manager's too. */
	public function handle_memory(): void {
		if ( ! SettingsAccess::can_save() ) {
			wp_die( esc_html__( 'You are not allowed to change the store notes.', 'counterhand-mcp-for-woocommerce' ), 403 );
		}

		check_admin_referer( self::MEMORY_ACTION );

		$this->memory->save( (string) wp_unslash( $_POST['counterhand_store_memory'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- StoreMemory::save() sanitizes.

		wp_safe_redirect( AdminScreen::Chat->url() );
		exit;
	}

	public function handle_save_prompt(): void {
		$this->guard();

		wp_send_json_success( [ 'prompts' => $this->prompts->add( get_current_user_id(), (string) wp_unslash( $_POST['prompt'] ?? '' ) ) ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- SavedPrompts::add() sanitizes; nonce checked in guard().
	}

	public function handle_forget_prompt(): void {
		$this->guard();

		wp_send_json_success( [ 'prompts' => $this->prompts->remove( get_current_user_id(), (string) wp_unslash( $_POST['prompt'] ?? '' ) ) ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- only compared against the stored list; nonce checked in guard().
	}

	private function guard(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Not allowed.', 'counterhand-mcp-for-woocommerce' ) ], 403 );
		}

		check_ajax_referer( self::PROMPTS_NONCE );
	}
}
