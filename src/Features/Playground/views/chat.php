<?php
/**
 * Chat tab: talk to the store in plain language.
 *
 * This is the inward-facing half of the plugin — AI used here, inside
 * WooCommerce, on a model this store owns. The Connect AI apps tab is the
 * outward-facing half: apps elsewhere using this store.
 *
 * The chat frame is always on screen. Until a model is connected the setup
 * lives inside it, where the conversation will appear — so the answer to
 * "where is the chat?" is never "somewhere after you finish this form".
 *
 * @var list<\Counterhand\Shared\Tool\ToolInterface>                               $tools
 * @var bool                                                                        $is_ready
 * @var string                                                                      $send_nonce
 * @var \Counterhand\Features\Playground\ChatSettings                              $chat_settings
 * @var array<string, \Counterhand\Features\Playground\Provider\ProviderInterface> $chat_providers
 * @var array<string, \Counterhand\Features\Playground\Provider\ProviderInterface> $chat_own_providers
 * @var string                                                                      $active_id
 * @var \Counterhand\Features\Playground\ConnectResult|null                        $save_result
 * @var \Counterhand\Features\Playground\CoreAiState|null                          $core_state
 * @var list<\Counterhand\Features\Playground\CoreConnector>                       $core_connectors
 * @var list<\Counterhand\Features\Playground\ChatStarter>                         $starters
 * @var list<string>                                                                $saved_prompts
 * @var string                                                                      $prompts_nonce
 * @var string                                                                      $store_memory
 * @var bool                                                                        $can_edit_memory
 * @var bool                                                                        $can_choose_model
 * @var string                                                                      $storage_key
 */

declare( strict_types=1 );

use Counterhand\Features\Settings\AdminScreen;

defined( 'ABSPATH' ) || exit;

$counterhand_active = $chat_providers[ $chat_settings->provider_id() ] ?? null;
?>
<div class="counterhand-chat">
	<?php if ( null !== $save_result ) : ?>
		<p class="counterhand-chooser__result <?php echo $save_result->ok ? 'counterhand-ok' : 'counterhand-fail'; ?>" role="status">
			<?php echo esc_html( ( $save_result->ok ? '✓ ' : '✕ ' ) . $save_result->message ); ?>
		</p>
	<?php endif; ?>

	<?php if ( $is_ready && [] === $tools ) : ?>
		<div class="notice notice-warning inline"><p>
			<?php esc_html_e( 'No area is switched on, so the assistant cannot read or change anything. Switch on at least one area in Access.', 'counterhand-mcp-for-woocommerce' ); ?>
		</p></div>
	<?php endif; ?>

	<div class="counterhand-chat__panel">
		<?php
		// The live-region attributes are on the conversation branch only: the
		// model chooser is a form, and announcing every field it contains as a
		// log entry is what breaks a screen reader on this screen.
		?>
		<div class="counterhand-chat__log <?php echo $is_ready ? '' : 'counterhand-chat__log--setup'; ?>"
			id="counterhand-chat-log" <?php echo $is_ready ? 'role="log" aria-live="polite"' : ''; ?>>

			<?php if ( ! $is_ready && ! $can_choose_model ) : ?>
				<p class="counterhand-chat__empty-hint">
					<?php
					printf(
						/* translators: %s: the name of the plugin's Chat screen. */
						esc_html__( 'No AI model is chosen yet. An administrator chooses one on %s.', 'counterhand-mcp-for-woocommerce' ),
						esc_html( AdminScreen::Chat->menu_title() )
					);
					?>
				</p>
			<?php elseif ( ! $is_ready ) : ?>
				<?php require __DIR__ . '/partial-model-chooser.php'; ?>
			<?php else : ?>
				<div class="counterhand-chat__empty" id="counterhand-chat-empty">
					<p class="counterhand-chat__empty-title"><?php esc_html_e( 'Ask about your store', 'counterhand-mcp-for-woocommerce' ); ?></p>
					<p class="counterhand-chat__empty-hint"><?php esc_html_e( 'Plain language works — the assistant looks the answer up with the same tools an outside AI app would use.', 'counterhand-mcp-for-woocommerce' ); ?></p>
					<div class="counterhand-chat__suggestions" id="counterhand-chat-starters">
						<?php foreach ( $starters as $counterhand_starter ) : ?>
							<button type="button" class="counterhand-chat__suggestion" data-message="<?php echo esc_attr( $counterhand_starter->message ); ?>"><?php echo esc_html( $counterhand_starter->title ); ?></button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $is_ready ) : ?>
			<ul class="counterhand-chat__saved" id="counterhand-chat-saved" aria-label="<?php esc_attr_e( 'Saved questions', 'counterhand-mcp-for-woocommerce' ); ?>">
				<?php foreach ( $saved_prompts as $counterhand_prompt ) : ?>
					<li>
						<button type="button" class="counterhand-chat__saved-ask"><?php echo esc_html( $counterhand_prompt ); ?></button>
						<button type="button" class="counterhand-chat__saved-forget" aria-label="<?php esc_attr_e( 'Forget this question', 'counterhand-mcp-for-woocommerce' ); ?>">×</button>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<form class="counterhand-chat__composer" id="counterhand-chat-form"
			data-nonce="<?php echo esc_attr( $send_nonce ); ?>"
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-prompts-nonce="<?php echo esc_attr( $prompts_nonce ); ?>"
			data-storage-key="<?php echo esc_attr( $storage_key ); ?>">
			<label class="screen-reader-text" for="counterhand-chat-input"><?php esc_html_e( 'Message', 'counterhand-mcp-for-woocommerce' ); ?></label>
			<textarea id="counterhand-chat-input" class="counterhand-chat__input" rows="1"
				placeholder="
				<?php
				echo esc_attr(
					$is_ready
						? __( 'Ask about products, orders, customers or sales…', 'counterhand-mcp-for-woocommerce' )
						: __( 'Choose an AI model above, then ask your first question here…', 'counterhand-mcp-for-woocommerce' )
				);
				?>
				" <?php disabled( ! $is_ready ); ?>></textarea>
			<button type="button" class="button counterhand-chat__save-prompt" id="counterhand-chat-save-prompt" <?php disabled( ! $is_ready ); ?>>
				<?php esc_html_e( 'Save question', 'counterhand-mcp-for-woocommerce' ); ?>
			</button>
			<button type="submit" class="button button-primary counterhand-chat__send" id="counterhand-chat-send" <?php disabled( ! $is_ready ); ?>>
				<?php esc_html_e( 'Send', 'counterhand-mcp-for-woocommerce' ); ?>
			</button>
		</form>

		<p class="counterhand-chat__foot">
			<span class="counterhand-chat__foot-group">
				<span id="counterhand-chat-status" class="counterhand-chat__status" role="status"></span>
				<span class="counterhand-chat__hint">
					<?php
					printf(
						/* translators: 1: Enter key name, 2: Shift+Enter key combination */
						esc_html__( '%1$s to send · %2$s for a new line', 'counterhand-mcp-for-woocommerce' ),
						'<kbd>' . esc_html__( 'Enter', 'counterhand-mcp-for-woocommerce' ) . '</kbd>',
						'<kbd>' . esc_html__( 'Shift+Enter', 'counterhand-mcp-for-woocommerce' ) . '</kbd>'
					);
					?>
				</span>
			</span>

			<span class="counterhand-chat__foot-group">
				<?php if ( $is_ready && null !== $counterhand_active ) : ?>
					<span class="counterhand-chat__model">
						<?php
						// The core provider carries no model name, so this reads
						// as just the provider label for it.
						echo esc_html( rtrim( $counterhand_active->label() . ' · ' . $chat_settings->model(), ' ·' ) );
						?>
						<?php if ( $can_choose_model ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'counterhand_change_model', '1' ) ); ?>">
								<?php esc_html_e( 'Change', 'counterhand-mcp-for-woocommerce' ); ?>
							</a>
						<?php endif; ?>
					</span>
				<?php endif; ?>
				<button type="button" class="button-link counterhand-chat__reset" id="counterhand-chat-reset" <?php disabled( ! $is_ready ); ?>>
					<?php esc_html_e( 'Clear conversation', 'counterhand-mcp-for-woocommerce' ); ?>
				</button>
			</span>
		</p>
	</div>

	<?php require __DIR__ . '/partial-chat-tools.php'; ?>
	<?php require __DIR__ . '/partial-store-memory.php'; ?>

	<p class="description counterhand-chat__note">
		<?php esc_html_e( 'The assistant uses the same tools an AI app would, limited to the areas switched on in Access. Store data the assistant reads, which can include customers\' names, addresses and orders, is sent to the model provider you chose. Check whether you need a data processing agreement with them.', 'counterhand-mcp-for-woocommerce' ); ?>
	</p>
</div>
