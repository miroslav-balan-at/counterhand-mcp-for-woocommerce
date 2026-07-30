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
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$ctrh_active = $chat_providers[ $chat_settings->provider_id() ] ?? null;
?>
<div class="ctrh-chat">
	<?php if ( null !== $save_result ) : ?>
		<p class="ctrh-chooser__result <?php echo $save_result->ok ? 'ctrh-ok' : 'ctrh-fail'; ?>" role="status">
			<?php echo esc_html( ( $save_result->ok ? '✓ ' : '✕ ' ) . $save_result->message ); ?>
		</p>
	<?php endif; ?>

	<?php if ( $is_ready && [] === $tools ) : ?>
		<div class="notice notice-warning inline"><p>
			<?php esc_html_e( 'No tools are enabled, so the assistant cannot read or change anything. Turn on at least one tool group on the Settings tab.', 'counterhand-mcp-for-woocommerce' ); ?>
		</p></div>
	<?php endif; ?>

	<div class="ctrh-chat__panel">
		<?php
		// The live-region attributes are on the conversation branch only: the
		// model chooser is a form, and announcing every field it contains as a
		// log entry is what breaks a screen reader on this screen.
		?>
		<div class="ctrh-chat__log <?php echo $is_ready ? '' : 'ctrh-chat__log--setup'; ?>"
			id="ctrh-chat-log" <?php echo $is_ready ? 'role="log" aria-live="polite"' : ''; ?>>

			<?php if ( ! $is_ready ) : ?>
				<?php require __DIR__ . '/partial-model-chooser.php'; ?>
			<?php else : ?>
				<div class="ctrh-chat__empty" id="ctrh-chat-empty">
					<p class="ctrh-chat__empty-title"><?php esc_html_e( 'Ask about your store', 'counterhand-mcp-for-woocommerce' ); ?></p>
					<p class="ctrh-chat__empty-hint"><?php esc_html_e( 'Plain language works — the assistant looks the answer up with the same tools an outside AI app would use.', 'counterhand-mcp-for-woocommerce' ); ?></p>
					<div class="ctrh-chat__suggestions">
						<?php
						$ctrh_suggestions = [
							__( 'How many orders are waiting to be processed?', 'counterhand-mcp-for-woocommerce' ),
							__( 'Show me my 5 best selling products this month', 'counterhand-mcp-for-woocommerce' ),
							__( 'Which products are out of stock?', 'counterhand-mcp-for-woocommerce' ),
							__( 'What were my sales last week?', 'counterhand-mcp-for-woocommerce' ),
						];
						foreach ( $ctrh_suggestions as $ctrh_suggestion ) :
							?>
							<button type="button" class="ctrh-chat__suggestion"><?php echo esc_html( $ctrh_suggestion ); ?></button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<form class="ctrh-chat__composer" id="ctrh-chat-form"
			data-nonce="<?php echo esc_attr( $send_nonce ); ?>"
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<label class="screen-reader-text" for="ctrh-chat-input"><?php esc_html_e( 'Message', 'counterhand-mcp-for-woocommerce' ); ?></label>
			<textarea id="ctrh-chat-input" class="ctrh-chat__input" rows="1"
				placeholder="
				<?php
				echo esc_attr(
					$is_ready
						? __( 'Ask about products, orders, customers or sales…', 'counterhand-mcp-for-woocommerce' )
						: __( 'Connect a model above, then ask your first question here…', 'counterhand-mcp-for-woocommerce' )
				);
				?>
				" <?php disabled( ! $is_ready ); ?>></textarea>
			<button type="submit" class="button button-primary ctrh-chat__send" id="ctrh-chat-send" <?php disabled( ! $is_ready ); ?>>
				<?php esc_html_e( 'Send', 'counterhand-mcp-for-woocommerce' ); ?>
			</button>
		</form>

		<p class="ctrh-chat__foot">
			<span class="ctrh-chat__foot-group">
				<span id="ctrh-chat-status" class="ctrh-chat__status" role="status"></span>
				<span class="ctrh-chat__hint">
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

			<span class="ctrh-chat__foot-group">
				<?php if ( $is_ready && null !== $ctrh_active ) : ?>
					<span class="ctrh-chat__model">
						<?php
						// The core provider carries no model name, so this reads
						// as just the provider label for it.
						echo esc_html( rtrim( $ctrh_active->label() . ' · ' . $chat_settings->model(), ' ·' ) );
						?>
						<a href="<?php echo esc_url( add_query_arg( 'ctrh_change_model', '1' ) ); ?>">
							<?php esc_html_e( 'Change', 'counterhand-mcp-for-woocommerce' ); ?>
						</a>
					</span>
				<?php endif; ?>
				<button type="button" class="button-link ctrh-chat__reset" id="ctrh-chat-reset" <?php disabled( ! $is_ready ); ?>>
					<?php esc_html_e( 'Clear conversation', 'counterhand-mcp-for-woocommerce' ); ?>
				</button>
			</span>
		</p>
	</div>

	<?php require __DIR__ . '/partial-chat-tools.php'; ?>

	<p class="description ctrh-chat__note">
		<?php esc_html_e( 'The assistant runs the same tools an external AI client would, limited by the tool groups you enabled. Your store data is sent to the model provider you configured.', 'counterhand-mcp-for-woocommerce' ); ?>
	</p>
</div>
