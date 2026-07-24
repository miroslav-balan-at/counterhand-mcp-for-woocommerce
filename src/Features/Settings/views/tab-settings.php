<?php
/**
 * Settings tab: master switch, per-group read/write toggles, rate limit.
 *
 * @var \AgentGateMcp\Features\Settings\PluginSettings $settings
 * @var list<\AgentGateMcp\Shared\Tool\ToolGroup>       $tool_groups
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
