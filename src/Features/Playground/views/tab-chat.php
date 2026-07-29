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
 * @var list<\AgentGateMcp\Shared\Tool\ToolInterface>                               $tools
 * @var bool                                                                        $is_ready
 * @var string                                                                      $send_nonce
 * @var \AgentGateMcp\Features\Playground\ChatSettings                              $chat_settings
 * @var array<string, \AgentGateMcp\Features\Playground\Provider\ProviderInterface> $chat_providers
 * @var string                                                                      $active_id
 * @var \AgentGateMcp\Features\Playground\ConnectResult|null                        $save_result
 * @var \AgentGateMcp\Features\Playground\CoreAiState|null                          $core_state
 * @var list<\AgentGateMcp\Features\Playground\CoreConnector>                       $core_connectors
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$agmcp_active = $chat_providers[ $chat_settings->provider_id() ] ?? null;
?>
<div class="agmcp-chat">
	<?php if ( null !== $save_result ) : ?>
		<p class="agmcp-chooser__result <?php echo $save_result->ok ? 'agmcp-ok' : 'agmcp-fail'; ?>" role="status">
			<?php echo esc_html( ( $save_result->ok ? '✓ ' : '✕ ' ) . $save_result->message ); ?>
		</p>
	<?php endif; ?>

	<?php if ( $is_ready && [] === $tools ) : ?>
		<div class="notice notice-warning inline"><p>
			<?php esc_html_e( 'No tools are enabled, so the assistant cannot read or change anything. Turn on at least one tool group on the Settings tab.', 'agentgate-mcp-for-woocommerce' ); ?>
		</p></div>
	<?php endif; ?>

	<div class="agmcp-chat__panel">
		<div class="agmcp-chat__log <?php echo $is_ready ? '' : 'agmcp-chat__log--setup'; ?>"
			id="agmcp-chat-log" role="log" aria-live="polite">

			<?php if ( ! $is_ready ) : ?>
				<?php require __DIR__ . '/partial-model-chooser.php'; ?>
			<?php else : ?>
				<div class="agmcp-chat__empty" id="agmcp-chat-empty">
					<p class="agmcp-chat__empty-title"><?php esc_html_e( 'Ask about your store', 'agentgate-mcp-for-woocommerce' ); ?></p>
					<p class="agmcp-chat__empty-hint"><?php esc_html_e( 'Plain language works — the assistant looks the answer up with the same tools an outside AI app would use.', 'agentgate-mcp-for-woocommerce' ); ?></p>
					<div class="agmcp-chat__suggestions">
						<?php
						$agmcp_suggestions = [
							__( 'How many orders are waiting to be processed?', 'agentgate-mcp-for-woocommerce' ),
							__( 'Show me my 5 best selling products this month', 'agentgate-mcp-for-woocommerce' ),
							__( 'Which products are out of stock?', 'agentgate-mcp-for-woocommerce' ),
							__( 'What were my sales last week?', 'agentgate-mcp-for-woocommerce' ),
						];
						foreach ( $agmcp_suggestions as $agmcp_suggestion ) :
							?>
							<button type="button" class="agmcp-chat__suggestion"><?php echo esc_html( $agmcp_suggestion ); ?></button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<form class="agmcp-chat__composer" id="agmcp-chat-form"
			data-nonce="<?php echo esc_attr( $send_nonce ); ?>"
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<label class="screen-reader-text" for="agmcp-chat-input"><?php esc_html_e( 'Message', 'agentgate-mcp-for-woocommerce' ); ?></label>
			<textarea id="agmcp-chat-input" class="agmcp-chat__input" rows="1"
				placeholder="
				<?php
				echo esc_attr(
					$is_ready
						? __( 'Ask about products, orders, customers or sales…', 'agentgate-mcp-for-woocommerce' )
						: __( 'Connect a model above, then ask your first question here…', 'agentgate-mcp-for-woocommerce' )
				);
				?>
				" <?php disabled( ! $is_ready ); ?>></textarea>
			<button type="submit" class="button button-primary agmcp-chat__send" id="agmcp-chat-send" <?php disabled( ! $is_ready ); ?>>
				<?php esc_html_e( 'Send', 'agentgate-mcp-for-woocommerce' ); ?>
			</button>
		</form>

		<p class="agmcp-chat__foot">
			<span class="agmcp-chat__foot-group">
				<span id="agmcp-chat-status" class="agmcp-chat__status" role="status"></span>
				<span class="agmcp-chat__hint">
					<?php
					printf(
						/* translators: 1: Enter key name, 2: Shift+Enter key combination */
						esc_html__( '%1$s to send · %2$s for a new line', 'agentgate-mcp-for-woocommerce' ),
						'<kbd>' . esc_html__( 'Enter', 'agentgate-mcp-for-woocommerce' ) . '</kbd>',
						'<kbd>' . esc_html__( 'Shift+Enter', 'agentgate-mcp-for-woocommerce' ) . '</kbd>'
					);
					?>
				</span>
			</span>

			<span class="agmcp-chat__foot-group">
				<?php if ( $is_ready && null !== $agmcp_active ) : ?>
					<span class="agmcp-chat__model">
						<?php
						// The core provider carries no model name, so this reads
						// as just the provider label for it.
						echo esc_html( rtrim( $agmcp_active->label() . ' · ' . $chat_settings->model(), ' ·' ) );
						?>
						<a href="<?php echo esc_url( add_query_arg( 'agmcp_change_model', '1' ) ); ?>">
							<?php esc_html_e( 'Change', 'agentgate-mcp-for-woocommerce' ); ?>
						</a>
					</span>
				<?php endif; ?>
				<button type="button" class="button-link agmcp-chat__reset" id="agmcp-chat-reset" <?php disabled( ! $is_ready ); ?>>
					<?php esc_html_e( 'Clear conversation', 'agentgate-mcp-for-woocommerce' ); ?>
				</button>
			</span>
		</p>
	</div>

	<?php require __DIR__ . '/partial-chat-tools.php'; ?>

	<p class="description agmcp-chat__note">
		<?php esc_html_e( 'The assistant runs the same tools an external AI client would, limited by the tool groups you enabled. Your store data is sent to the model provider you configured.', 'agentgate-mcp-for-woocommerce' ); ?>
	</p>
</div>
