<?php
/**
 * Settings tab: master switch, per-group read/write toggles, rate limit.
 *
 * @var \AgentGateMcp\Features\Settings\PluginSettings $settings
 * @var list<\AgentGateMcp\Shared\Tool\ToolGroup>       $tool_groups
 * @var \AgentGateMcp\Features\Playground\ChatSettings  $chat_settings
 * @var array<string, \AgentGateMcp\Features\Playground\Provider\ProviderInterface> $chat_providers
 */

declare( strict_types=1 );

use AgentGateMcp\Features\Settings\PluginSettings;
use AgentGateMcp\Shared\Tool\ToolGroup;

defined( 'ABSPATH' ) || exit;

$agmcp_values = $settings->all();
$agmcp_option = PluginSettings::OPTION;
?>
<form method="post" action="options.php" class="agmcp-settings-form">
	<?php settings_fields( 'agmcp_settings_group' ); ?>

	<h2><?php esc_html_e( 'Connector', 'agentgate-mcp-for-woocommerce' ); ?></h2>
	<p class="description"><?php esc_html_e( 'When the connector is off, the /mcp endpoint does not exist — no tools and no keys are exposed to any assistant.', 'agentgate-mcp-for-woocommerce' ); ?></p>

	<label class="agmcp-master-switch">
		<input type="checkbox" name="<?php echo esc_attr( $agmcp_option ); ?>[enabled]" value="1" <?php checked( (bool) $agmcp_values['enabled'] ); ?>>
		<strong><?php esc_html_e( 'Enable the MCP connector', 'agentgate-mcp-for-woocommerce' ); ?></strong>
	</label>

	<h2><?php esc_html_e( 'Tool groups', 'agentgate-mcp-for-woocommerce' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Disabled groups are withheld from every AI client and enforced when a tool runs — not just hidden from the list. A token additionally needs the matching scope.', 'agentgate-mcp-for-woocommerce' ); ?></p>

	<table class="widefat agmcp-groups-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Group', 'agentgate-mcp-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Read', 'agentgate-mcp-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Write', 'agentgate-mcp-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $tool_groups as $agmcp_group ) : ?>
				<tr>
					<td><strong><?php echo esc_html( ucfirst( $agmcp_group->value ) ); ?></strong></td>
					<td>
						<input type="checkbox"
							name="<?php echo esc_attr( $agmcp_option ); ?>[<?php echo esc_attr( $agmcp_group->value ); ?>_read]"
							value="1" <?php checked( (bool) ( $agmcp_values[ $agmcp_group->value . '_read' ] ?? false ) ); ?>>
					</td>
					<td>
						<?php if ( ToolGroup::Reports === $agmcp_group || ToolGroup::Customers === $agmcp_group ) : ?>
							<span class="agmcp-muted">—</span>
						<?php else : ?>
							<input type="checkbox"
								name="<?php echo esc_attr( $agmcp_option ); ?>[<?php echo esc_attr( $agmcp_group->value ); ?>_write]"
								value="1" <?php checked( (bool) ( $agmcp_values[ $agmcp_group->value . '_write' ] ?? false ) ); ?>>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Limits', 'agentgate-mcp-for-woocommerce' ); ?></h2>
	<p>
		<label for="agmcp-rate-limit"><strong><?php esc_html_e( 'Requests per minute per token', 'agentgate-mcp-for-woocommerce' ); ?></strong></label><br>
		<input type="number" id="agmcp-rate-limit" min="1" max="1000"
			name="<?php echo esc_attr( $agmcp_option ); ?>[rate_limit_per_minute]"
			value="<?php echo esc_attr( (string) $agmcp_values['rate_limit_per_minute'] ); ?>">
	</p>

	<h2><?php esc_html_e( 'Action log', 'agentgate-mcp-for-woocommerce' ); ?></h2>
	<label>
		<input type="checkbox" name="<?php echo esc_attr( $agmcp_option ); ?>[action_log_enabled]" value="1"
			<?php checked( (bool) ( $agmcp_values['action_log_enabled'] ?? false ) ); ?>>
		<?php esc_html_e( 'Record every tool call (PII is masked before storage)', 'agentgate-mcp-for-woocommerce' ); ?>
	</label>
	<p>
		<label for="agmcp-retention"><strong><?php esc_html_e( 'Keep log entries for (days)', 'agentgate-mcp-for-woocommerce' ); ?></strong></label><br>
		<input type="number" id="agmcp-retention" min="1" max="365"
			name="<?php echo esc_attr( $agmcp_option ); ?>[log_retention_days]"
			value="<?php echo esc_attr( (string) ( $agmcp_values['log_retention_days'] ?? 30 ) ); ?>">
	</p>

	<?php submit_button(); ?>
</form>

<hr>

<h2><?php esc_html_e( 'Chat model', 'agentgate-mcp-for-woocommerce' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Powers the Chat tab only. An AI model is what decides which store tools to use, so the chat needs one — this is separate from the apps you connect on the “Connect apps” tab, which bring their own AI.', 'agentgate-mcp-for-woocommerce' ); ?>
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="agmcp-chat-settings">
	<input type="hidden" name="action" value="agmcp_save_chat">
	<?php wp_nonce_field( 'agmcp_save_chat' ); ?>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="agmcp-chat-provider"><?php esc_html_e( 'Provider', 'agentgate-mcp-for-woocommerce' ); ?></label></th>
			<td>
				<select id="agmcp-chat-provider" name="agmcp_chat_provider">
					<?php foreach ( $chat_providers as $agmcp_provider ) : ?>
						<option value="<?php echo esc_attr( $agmcp_provider->id() ); ?>"
							<?php selected( $chat_settings->provider_id(), $agmcp_provider->id() ); ?>>
							<?php echo esc_html( $agmcp_provider->label() ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="agmcp-chat-model"><?php esc_html_e( 'Model', 'agentgate-mcp-for-woocommerce' ); ?></label></th>
			<td>
				<input type="text" id="agmcp-chat-model" name="agmcp_chat_model" class="regular-text"
					list="agmcp-chat-model-list"
					value="<?php echo esc_attr( $chat_settings->model() ); ?>"
					placeholder="claude-opus-5">
				<datalist id="agmcp-chat-model-list">
					<?php
					foreach ( $chat_providers as $agmcp_provider ) {
						foreach ( $agmcp_provider->default_models() as $agmcp_model_id => $agmcp_model_label ) {
							printf(
								'<option value="%s">%s</option>',
								esc_attr( $agmcp_model_id ),
								esc_attr( $agmcp_model_label )
							);
						}
					}
					?>
				</datalist>
				<p class="description"><?php esc_html_e( 'Pick from the list or type any model your provider offers.', 'agentgate-mcp-for-woocommerce' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="agmcp-chat-key"><?php esc_html_e( 'API key', 'agentgate-mcp-for-woocommerce' ); ?></label></th>
			<td>
				<?php
				$agmcp_key_placeholder = '' !== $chat_settings->masked_key()
					? sprintf(
						/* translators: %s: masked API key */
						__( 'Saved (%s) — leave blank to keep', 'agentgate-mcp-for-woocommerce' ),
						$chat_settings->masked_key()
					)
					: __( 'Paste your provider API key', 'agentgate-mcp-for-woocommerce' );
				?>
				<input type="password" id="agmcp-chat-key" name="agmcp_chat_key" class="regular-text"
					autocomplete="off"
					placeholder="<?php echo esc_attr( $agmcp_key_placeholder ); ?>">
				<p class="description">
					<?php esc_html_e( 'Stored in your database and sent only to the provider you chose. Local models such as Ollama need no key.', 'agentgate-mcp-for-woocommerce' ); ?>
					<?php if ( '' !== $chat_settings->masked_key() ) : ?>
						<label class="agmcp-forget-key">
							<input type="checkbox" name="agmcp_chat_forget" value="1">
							<?php esc_html_e( 'Remove the saved key', 'agentgate-mcp-for-woocommerce' ); ?>
						</label>
					<?php endif; ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="agmcp-chat-base-url"><?php esc_html_e( 'Base URL', 'agentgate-mcp-for-woocommerce' ); ?></label></th>
			<td>
				<input type="url" id="agmcp-chat-base-url" name="agmcp_chat_base_url" class="regular-text"
					value="<?php echo esc_attr( $chat_settings->base_url() ); ?>"
					placeholder="http://localhost:11434/v1">
				<p class="description"><?php esc_html_e( 'Only for OpenAI-compatible endpoints (Ollama, OpenRouter, Azure, LM Studio). Leave blank otherwise.', 'agentgate-mcp-for-woocommerce' ); ?></p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save chat model', 'agentgate-mcp-for-woocommerce' ) ); ?>
</form>
