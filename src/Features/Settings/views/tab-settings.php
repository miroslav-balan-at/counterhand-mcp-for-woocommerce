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

$counterhand_values = $settings->all();
$counterhand_option = PluginSettings::OPTION;
?>
<form method="post" action="options.php" class="counterhand-settings-form">
	<?php settings_fields( 'counterhand_settings_group' ); ?>

	<div class="counterhand-card">
		<div class="counterhand-card__head">
			<span class="counterhand-card__heading">
				<h3 class="counterhand-card__title"><?php esc_html_e( 'Connector', 'counterhand-mcp-for-woocommerce' ); ?></h3>
				<span class="counterhand-card__desc"><?php esc_html_e( 'When the connector is off, the /mcp endpoint does not exist — no tools and no keys are exposed to any assistant.', 'counterhand-mcp-for-woocommerce' ); ?></span>
			</span>
		</div>
		<div class="counterhand-card__body">
			<label class="counterhand-master-switch">
				<input type="checkbox" name="<?php echo esc_attr( $counterhand_option ); ?>[enabled]" value="1" <?php checked( (bool) $counterhand_values['enabled'] ); ?>>
				<strong><?php esc_html_e( 'Enable the MCP connector', 'counterhand-mcp-for-woocommerce' ); ?></strong>
			</label>
		</div>
	</div>

	<div class="counterhand-card">
		<div class="counterhand-card__head">
			<span class="counterhand-card__heading">
				<h3 class="counterhand-card__title"><?php esc_html_e( 'Tool groups', 'counterhand-mcp-for-woocommerce' ); ?></h3>
				<span class="counterhand-card__desc"><?php esc_html_e( 'Disabled groups are withheld from every AI client and enforced when a tool runs — not just hidden from the list. A connection additionally needs the matching scope.', 'counterhand-mcp-for-woocommerce' ); ?></span>
			</span>
		</div>
		<div class="counterhand-card__body">
			<table class="widefat counterhand-groups-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Group', 'counterhand-mcp-for-woocommerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Read', 'counterhand-mcp-for-woocommerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Write', 'counterhand-mcp-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<?php foreach ( $tool_sections as $counterhand_section ) : ?>
					<tbody>
						<tr class="counterhand-groups-table__section">
							<th colspan="3" scope="colgroup">
								<span class="counterhand-groups-table__section-title"><?php echo esc_html( $counterhand_section->label() ); ?></span>
								<span class="counterhand-muted"><?php echo esc_html( $counterhand_section->description() ); ?></span>
							</th>
						</tr>
						<?php foreach ( $counterhand_section->groups() as $counterhand_group ) : ?>
							<tr>
								<th scope="row">
									<strong><?php echo esc_html( $counterhand_group->label() ); ?></strong>
									<span class="counterhand-muted"><?php echo esc_html( $counterhand_group->description() ); ?></span>
								</th>
								<td>
									<label class="screen-reader-text" for="counterhand-<?php echo esc_attr( $counterhand_group->read_option_key() ); ?>">
										<?php
										printf(
											/* translators: %s: name of a tool group, e.g. "Products". */
											esc_html__( 'Allow reading %s', 'counterhand-mcp-for-woocommerce' ),
											esc_html( $counterhand_group->label() )
										);
										?>
									</label>
									<input type="checkbox"
										id="counterhand-<?php echo esc_attr( $counterhand_group->read_option_key() ); ?>"
										name="<?php echo esc_attr( $counterhand_option ); ?>[<?php echo esc_attr( $counterhand_group->read_option_key() ); ?>]"
										value="1" <?php checked( (bool) ( $counterhand_values[ $counterhand_group->read_option_key() ] ?? false ) ); ?>>
								</td>
								<td>
									<?php if ( ! $counterhand_group->has_write() ) : ?>
										<span class="counterhand-muted" aria-hidden="true">—</span>
										<span class="screen-reader-text"><?php esc_html_e( 'This area is read-only and cannot be changed by an AI client.', 'counterhand-mcp-for-woocommerce' ); ?></span>
									<?php else : ?>
										<label class="screen-reader-text" for="counterhand-<?php echo esc_attr( $counterhand_group->write_option_key() ); ?>">
											<?php
											printf(
												/* translators: %s: name of a tool group, e.g. "Products". */
												esc_html__( 'Allow changing %s', 'counterhand-mcp-for-woocommerce' ),
												esc_html( $counterhand_group->label() )
											);
											?>
										</label>
										<input type="checkbox"
											id="counterhand-<?php echo esc_attr( $counterhand_group->write_option_key() ); ?>"
											name="<?php echo esc_attr( $counterhand_option ); ?>[<?php echo esc_attr( $counterhand_group->write_option_key() ); ?>]"
											value="1" <?php checked( (bool) ( $counterhand_values[ $counterhand_group->write_option_key() ] ?? false ) ); ?>>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				<?php endforeach; ?>
			</table>
		</div>
	</div>

	<div class="counterhand-card">
		<div class="counterhand-card__head">
			<span class="counterhand-card__heading">
				<h3 class="counterhand-card__title"><?php esc_html_e( 'Limits', 'counterhand-mcp-for-woocommerce' ); ?></h3>
				<span class="counterhand-card__desc"><?php esc_html_e( 'Caps how fast a single connection can call the store.', 'counterhand-mcp-for-woocommerce' ); ?></span>
			</span>
		</div>
		<div class="counterhand-card__body">
			<div class="counterhand-field">
				<label class="counterhand-label" for="counterhand-rate-limit"><?php esc_html_e( 'Requests per minute per connection', 'counterhand-mcp-for-woocommerce' ); ?></label>
				<input type="number" id="counterhand-rate-limit" min="1" max="1000"
					name="<?php echo esc_attr( $counterhand_option ); ?>[rate_limit_per_minute]"
					value="<?php echo esc_attr( (string) $counterhand_values['rate_limit_per_minute'] ); ?>">
			</div>
		</div>
	</div>

	<div class="counterhand-card">
		<div class="counterhand-card__head">
			<span class="counterhand-card__heading">
				<h3 class="counterhand-card__title"><?php esc_html_e( 'Action log', 'counterhand-mcp-for-woocommerce' ); ?></h3>
				<span class="counterhand-card__desc"><?php esc_html_e( 'Records what each connection did. Emails and phone numbers are masked before anything is stored.', 'counterhand-mcp-for-woocommerce' ); ?></span>
			</span>
		</div>
		<div class="counterhand-card__body">
			<div class="counterhand-field">
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $counterhand_option ); ?>[action_log_enabled]" value="1"
						<?php checked( (bool) ( $counterhand_values['action_log_enabled'] ?? false ) ); ?>>
					<?php esc_html_e( 'Record every tool call', 'counterhand-mcp-for-woocommerce' ); ?>
				</label>
			</div>

			<div class="counterhand-field">
				<label class="counterhand-label" for="counterhand-retention"><?php esc_html_e( 'Keep log entries for (days)', 'counterhand-mcp-for-woocommerce' ); ?></label>
				<input type="number" id="counterhand-retention" min="1" max="365"
					name="<?php echo esc_attr( $counterhand_option ); ?>[log_retention_days]"
					value="<?php echo esc_attr( (string) ( $counterhand_values['log_retention_days'] ?? 30 ) ); ?>">
			</div>
		</div>
	</div>

	<?php submit_button(); ?>
</form>
