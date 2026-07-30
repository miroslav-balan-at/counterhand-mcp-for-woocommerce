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
 * @var \Counterhand\Features\Settings\PluginSettings $settings
 * @var list<\Counterhand\Shared\Tool\ToolSection>    $tool_sections
 */

declare( strict_types=1 );

use Counterhand\Features\Settings\PluginSettings;

defined( 'ABSPATH' ) || exit;

$ctrh_values = $settings->all();
$ctrh_option = PluginSettings::OPTION;
?>
<form method="post" action="options.php" class="ctrh-settings-form">
	<?php settings_fields( 'ctrh_settings_group' ); ?>

	<div class="ctrh-card">
		<div class="ctrh-card__head">
			<span class="ctrh-card__heading">
				<span class="ctrh-card__title"><?php esc_html_e( 'Connector', 'counterhand-mcp-for-woocommerce' ); ?></span>
				<span class="ctrh-card__desc"><?php esc_html_e( 'When the connector is off, the /mcp endpoint does not exist — no tools and no keys are exposed to any assistant.', 'counterhand-mcp-for-woocommerce' ); ?></span>
			</span>
		</div>
		<div class="ctrh-card__body">
			<label class="ctrh-master-switch">
				<input type="checkbox" name="<?php echo esc_attr( $ctrh_option ); ?>[enabled]" value="1" <?php checked( (bool) $ctrh_values['enabled'] ); ?>>
				<strong><?php esc_html_e( 'Enable the MCP connector', 'counterhand-mcp-for-woocommerce' ); ?></strong>
			</label>
		</div>
	</div>

	<div class="ctrh-card">
		<div class="ctrh-card__head">
			<span class="ctrh-card__heading">
				<span class="ctrh-card__title"><?php esc_html_e( 'Tool groups', 'counterhand-mcp-for-woocommerce' ); ?></span>
				<span class="ctrh-card__desc"><?php esc_html_e( 'Disabled groups are withheld from every AI client and enforced when a tool runs — not just hidden from the list. A connection additionally needs the matching scope.', 'counterhand-mcp-for-woocommerce' ); ?></span>
			</span>
		</div>
		<div class="ctrh-card__body">
			<table class="widefat ctrh-groups-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Group', 'counterhand-mcp-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Read', 'counterhand-mcp-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Write', 'counterhand-mcp-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<?php foreach ( $tool_sections as $ctrh_section ) : ?>
					<tbody>
						<tr class="ctrh-groups-table__section">
							<th colspan="3" scope="colgroup">
								<span class="ctrh-groups-table__section-title"><?php echo esc_html( $ctrh_section->label() ); ?></span>
								<span class="ctrh-muted"><?php echo esc_html( $ctrh_section->description() ); ?></span>
							</th>
						</tr>
						<?php foreach ( $ctrh_section->groups() as $ctrh_group ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $ctrh_group->label() ); ?></strong>
									<span class="ctrh-muted"><?php echo esc_html( $ctrh_group->description() ); ?></span>
								</td>
								<td>
									<input type="checkbox"
										name="<?php echo esc_attr( $ctrh_option ); ?>[<?php echo esc_attr( $ctrh_group->read_option_key() ); ?>]"
										value="1" <?php checked( (bool) ( $ctrh_values[ $ctrh_group->read_option_key() ] ?? false ) ); ?>>
								</td>
								<td>
									<?php if ( ! $ctrh_group->has_write() ) : ?>
										<span class="ctrh-muted">—</span>
									<?php else : ?>
										<input type="checkbox"
											name="<?php echo esc_attr( $ctrh_option ); ?>[<?php echo esc_attr( $ctrh_group->write_option_key() ); ?>]"
											value="1" <?php checked( (bool) ( $ctrh_values[ $ctrh_group->write_option_key() ] ?? false ) ); ?>>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				<?php endforeach; ?>
			</table>
		</div>
	</div>

	<div class="ctrh-card">
		<div class="ctrh-card__head">
			<span class="ctrh-card__heading">
				<span class="ctrh-card__title"><?php esc_html_e( 'Limits', 'counterhand-mcp-for-woocommerce' ); ?></span>
				<span class="ctrh-card__desc"><?php esc_html_e( 'Caps how fast a single connection can call the store.', 'counterhand-mcp-for-woocommerce' ); ?></span>
			</span>
		</div>
		<div class="ctrh-card__body">
			<div class="ctrh-field">
				<label class="ctrh-label" for="ctrh-rate-limit"><?php esc_html_e( 'Requests per minute per connection', 'counterhand-mcp-for-woocommerce' ); ?></label>
				<input type="number" id="ctrh-rate-limit" min="1" max="1000"
					name="<?php echo esc_attr( $ctrh_option ); ?>[rate_limit_per_minute]"
					value="<?php echo esc_attr( (string) $ctrh_values['rate_limit_per_minute'] ); ?>">
			</div>
		</div>
	</div>

	<div class="ctrh-card">
		<div class="ctrh-card__head">
			<span class="ctrh-card__heading">
				<span class="ctrh-card__title"><?php esc_html_e( 'Action log', 'counterhand-mcp-for-woocommerce' ); ?></span>
				<span class="ctrh-card__desc"><?php esc_html_e( 'Records what each connection did. Emails and phone numbers are masked before anything is stored.', 'counterhand-mcp-for-woocommerce' ); ?></span>
			</span>
		</div>
		<div class="ctrh-card__body">
			<div class="ctrh-field">
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $ctrh_option ); ?>[action_log_enabled]" value="1"
						<?php checked( (bool) ( $ctrh_values['action_log_enabled'] ?? false ) ); ?>>
					<?php esc_html_e( 'Record every tool call', 'counterhand-mcp-for-woocommerce' ); ?>
				</label>
			</div>

			<div class="ctrh-field">
				<label class="ctrh-label" for="ctrh-retention"><?php esc_html_e( 'Keep log entries for (days)', 'counterhand-mcp-for-woocommerce' ); ?></label>
				<input type="number" id="ctrh-retention" min="1" max="365"
					name="<?php echo esc_attr( $ctrh_option ); ?>[log_retention_days]"
					value="<?php echo esc_attr( (string) ( $ctrh_values['log_retention_days'] ?? 30 ) ); ?>">
			</div>
		</div>
	</div>

	<?php submit_button(); ?>
</form>
