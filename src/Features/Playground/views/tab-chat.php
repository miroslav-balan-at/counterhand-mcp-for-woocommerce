<?php
/**
 * Chat tab: talk to the store in plain language.
 *
 * @var list<\AgentGateMcp\Shared\Tool\ToolInterface> $tools
 * @var bool                                          $is_ready
 * @var string                                        $send_nonce
 * @var string                                        $settings_url
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<div class="agmcp-chat">
	<?php if ( ! $is_ready ) : ?>
		<div class="notice notice-info inline agmcp-chat__setup">
			<p>
				<strong><?php esc_html_e( 'Connect a model to start chatting.', 'agentgate-mcp-for-woocommerce' ); ?></strong><br>
				<?php esc_html_e( 'The chat needs an AI model to decide which store tools to use. Choose a provider and paste an API key — or point it at a local model such as Ollama, which needs no key.', 'agentgate-mcp-for-woocommerce' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $settings_url ); ?>">
					<?php esc_html_e( 'Choose a model', 'agentgate-mcp-for-woocommerce' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( [] === $tools ) : ?>
		<div class="notice notice-warning inline"><p>
			<?php esc_html_e( 'No tools are enabled, so the assistant cannot read or change anything. Turn on at least one tool group on the Settings tab.', 'agentgate-mcp-for-woocommerce' ); ?>
		</p></div>
	<?php endif; ?>

	<div class="agmcp-chat__panel">
		<div class="agmcp-chat__log" id="agmcp-chat-log" role="log" aria-live="polite">
			<div class="agmcp-chat__empty" id="agmcp-chat-empty">
				<p class="agmcp-chat__empty-title"><?php esc_html_e( 'Ask about your store', 'agentgate-mcp-for-woocommerce' ); ?></p>
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
		</div>

		<form class="agmcp-chat__composer" id="agmcp-chat-form"
			data-nonce="<?php echo esc_attr( $send_nonce ); ?>"
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<textarea id="agmcp-chat-input" class="agmcp-chat__input" rows="1"
				placeholder="<?php esc_attr_e( 'Ask about products, orders, customers or sales…', 'agentgate-mcp-for-woocommerce' ); ?>"
				<?php disabled( ! $is_ready ); ?>></textarea>
			<button type="submit" class="button button-primary agmcp-chat__send" id="agmcp-chat-send" <?php disabled( ! $is_ready ); ?>>
				<?php esc_html_e( 'Send', 'agentgate-mcp-for-woocommerce' ); ?>
			</button>
		</form>

		<p class="agmcp-chat__foot">
			<span id="agmcp-chat-status" class="agmcp-chat__status" role="status"></span>
			<button type="button" class="button-link agmcp-chat__reset" id="agmcp-chat-reset"><?php esc_html_e( 'Clear conversation', 'agentgate-mcp-for-woocommerce' ); ?></button>
		</p>
	</div>

	<p class="description agmcp-chat__note">
		<?php esc_html_e( 'The assistant runs the same tools an external AI client would, limited by the tool groups you enabled. Your store data is sent to the model provider you configured.', 'agentgate-mcp-for-woocommerce' ); ?>
	</p>
</div>