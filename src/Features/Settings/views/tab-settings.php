<?php
/**
 * Settings tab: master switch, per-group read/write toggles, limits, log.
 *
 * The chat model is deliberately not here — it lives on the Chat tab, with the
 * thing that needs it. This tab is only about what the store exposes and how
 * much of it.
 *
 * One idea per card, following WooCommerce's extension settings guidance.
 *
 * @var \AgentGateMcp\Features\Settings\PluginSettings $settings
 * @var list<\AgentGateMcp\Shared\Tool\ToolSection>    $tool_sections
 */

declare( strict_types=1 );

use AgentGateMcp\Features\Settings\PluginSettings;

defined( 'ABSPATH' ) || exit;

$agmcp_values = $settings->all();
$agmcp_option = PluginSettings::OPTION;
?>
<form method="post" action="options.php" class="agmcp-settings-form">
	<?php settings_fields( 'agmcp_settings_group' ); ?>

	<div class="agmcp-card">
		<div class="agmcp-card__head">
			<span class="agmcp-card__heading">
				<span class="agmcp-card__title"><?php esc_html_e( 'Connector', 'agentgate-mcp-for-woocommerce' ); ?></span>
				<span class="agmcp-card__desc"><?php esc_html_e( 'When the connector is off, the /mcp endpoint does not exist — no tools and no keys are exposed to any assistant.', 'agentgate-mcp-for-woocommerce' ); ?></span>
			</span>
		</div>
		<div class="agmcp-card__body">
			<label class="agmcp-master-switch">
				<input type="checkbox" name="<?php echo esc_attr( $agmcp_option ); ?>[enabled]" value="1" <?php checked( (bool) $agmcp_values['enabled'] ); ?>>
				<strong><?php esc_html_e( 'Enable the MCP connector', 'agentgate-mcp-for-woocommerce' ); ?></strong>
			</label>
		</div>
	</div>

	<div class="agmcp-card">
		<div class="agmcp-card__head">
			<span class="agmcp-card__heading">
				<span class="agmcp-card__title"><?php esc_html_e( 'Tool groups', 'agentgate-mcp-for-woocommerce' ); ?></span>
				<span class="agmcp-card__desc"><?php esc_html_e( 'Disabled groups are withheld from every AI client and enforced when a tool runs — not just hidden from the list. A connection additionally needs the matching scope.', 'agentgate-mcp-for-woocommerce' ); ?></span>
			</span>
		</div>
		<div class="agmcp-card__body">
			<table class="widefat agmcp-groups-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Group', 'agentgate-mcp-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Read', 'agentgate-mcp-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Write', 'agentgate-mcp-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<?php foreach ( $tool_sections as $agmcp_section ) : ?>
					<tbody>
						<tr class="agmcp-groups-table__section">
							<th colspan="3" scope="colgroup">
								<span class="agmcp-groups-table__section-title"><?php echo esc_html( $agmcp_section->label() ); ?></span>
								<span class="agmcp-muted"><?php echo esc_html( $agmcp_section->description() ); ?></span>
							</th>
						</tr>
						<?php foreach ( $agmcp_section->groups() as $agmcp_group ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $agmcp_group->label() ); ?></strong>
									<span class="agmcp-muted"><?php echo esc_html( $agmcp_group->description() ); ?></span>
								</td>
								<td>
									<input type="checkbox"
										name="<?php echo esc_attr( $agmcp_option ); ?>[<?php echo esc_attr( $agmcp_group->read_option_key() ); ?>]"
										value="1" <?php checked( (bool) ( $agmcp_values[ $agmcp_group->read_option_key() ] ?? false ) ); ?>>
								</td>
								<td>
									<?php if ( ! $agmcp_group->has_write() ) : ?>
										<span class="agmcp-muted">—</span>
									<?php else : ?>
										<input type="checkbox"
											name="<?php echo esc_attr( $agmcp_option ); ?>[<?php echo esc_attr( $agmcp_group->write_option_key() ); ?>]"
											value="1" <?php checked( (bool) ( $agmcp_values[ $agmcp_group->write_option_key() ] ?? false ) ); ?>>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				<?php endforeach; ?>
			</table>
		</div>
	</div>

	<div class="agmcp-card">
		<div class="agmcp-card__head">
			<span class="agmcp-card__heading">
				<span class="agmcp-card__title"><?php esc_html_e( 'Limits', 'agentgate-mcp-for-woocommerce' ); ?></span>
				<span class="agmcp-card__desc"><?php esc_html_e( 'Caps how fast a single connection can call the store.', 'agentgate-mcp-for-woocommerce' ); ?></span>
			</span>
		</div>
		<div class="agmcp-card__body">
			<div class="agmcp-field">
				<label class="agmcp-label" for="agmcp-rate-limit"><?php esc_html_e( 'Requests per minute per connection', 'agentgate-mcp-for-woocommerce' ); ?></label>
				<input type="number" id="agmcp-rate-limit" min="1" max="1000"
					name="<?php echo esc_attr( $agmcp_option ); ?>[rate_limit_per_minute]"
					value="<?php echo esc_attr( (string) $agmcp_values['rate_limit_per_minute'] ); ?>">
			</div>
		</div>
	</div>

	<div class="agmcp-card">
		<div class="agmcp-card__head">
			<span class="agmcp-card__heading">
				<span class="agmcp-card__title"><?php esc_html_e( 'Action log', 'agentgate-mcp-for-woocommerce' ); ?></span>
				<span class="agmcp-card__desc"><?php esc_html_e( 'Records what each connection did. Emails and phone numbers are masked before anything is stored.', 'agentgate-mcp-for-woocommerce' ); ?></span>
			</span>
		</div>
		<div class="agmcp-card__body">
			<div class="agmcp-field">
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $agmcp_option ); ?>[action_log_enabled]" value="1"
						<?php checked( (bool) ( $agmcp_values['action_log_enabled'] ?? false ) ); ?>>
					<?php esc_html_e( 'Record every tool call', 'agentgate-mcp-for-woocommerce' ); ?>
				</label>
			</div>

			<div class="agmcp-field">
				<label class="agmcp-label" for="agmcp-retention"><?php esc_html_e( 'Keep log entries for (days)', 'agentgate-mcp-for-woocommerce' ); ?></label>
				<input type="number" id="agmcp-retention" min="1" max="365"
					name="<?php echo esc_attr( $agmcp_option ); ?>[log_retention_days]"
					value="<?php echo esc_attr( (string) ( $agmcp_values['log_retention_days'] ?? 30 ) ); ?>">
			</div>
		</div>
	</div>

	<?php submit_button(); ?>
</form>
