<?php
/**
 * Access, the store's form: AI access, what AI may read and change in each area,
 * limits and Activity. Other plugins follow in their own form (integrations.php).
 *
 * The AI model is deliberately not here: it lives on the Chat screen, with the
 * thing that needs it.
 *
 * One idea per card, following WooCommerce's extension settings guidance.
 *
 * @var \Counterhand\Features\Settings\PluginSettings $settings
 * @var list<\Counterhand\Shared\Tool\ToolSection>    $tool_sections
 * @var bool                                         $other_mcp_server
 * @var bool                                         $can_save
 */

declare( strict_types=1 );

use Counterhand\Features\Settings\PluginSettings;
use Counterhand\Features\Settings\SettingsAccess;
use Counterhand\Features\Settings\SettingsForm;

defined( 'ABSPATH' ) || exit;

$counterhand_values = $settings->all();
$counterhand_option = PluginSettings::OPTION;
?>
<form method="post" action="options.php" class="counterhand-settings-form">
	<?php if ( $can_save ) : ?>
		<?php settings_fields( SettingsAccess::OPTION_GROUP ); ?>
		<input type="hidden" name="<?php echo esc_attr( $counterhand_option ); ?>[form]" value="<?php echo esc_attr( SettingsForm::Store->value ); ?>">
	<?php endif; ?>

	<div class="counterhand-card">
		<div class="counterhand-card__head">
			<span class="counterhand-card__heading">
				<h3 class="counterhand-card__title"><?php esc_html_e( 'AI access', 'counterhand-mcp-for-woocommerce' ); ?></h3>
				<span class="counterhand-card__desc"><?php esc_html_e( 'Off means the store\'s address for apps does not exist: no app and no chat can reach anything. Use it to stop everything at once.', 'counterhand-mcp-for-woocommerce' ); ?></span>
			</span>
		</div>
		<div class="counterhand-card__body">
			<label class="counterhand-master-switch">
				<input type="checkbox" name="<?php echo esc_attr( $counterhand_option ); ?>[enabled]" value="1" <?php checked( (bool) $counterhand_values['enabled'] ); ?> <?php disabled( ! $can_save ); ?>>
				<strong><?php esc_html_e( 'AI access is on', 'counterhand-mcp-for-woocommerce' ); ?></strong>
			</label>
		</div>
	</div>

	<div class="counterhand-card">
		<div class="counterhand-card__head">
			<span class="counterhand-card__heading">
				<h3 class="counterhand-card__title"><?php esc_html_e( 'Store areas', 'counterhand-mcp-for-woocommerce' ); ?></h3>
				<span class="counterhand-card__desc"><?php esc_html_e( 'What apps and the chat may read and change. An area switched off here is out of reach for every app, whatever it was approved for. An app also needs your approval for each area when it connects.', 'counterhand-mcp-for-woocommerce' ); ?></span>
			</span>
		</div>
		<div class="counterhand-card__body">
			<table class="widefat counterhand-groups-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Area', 'counterhand-mcp-for-woocommerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Can read', 'counterhand-mcp-for-woocommerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Can change', 'counterhand-mcp-for-woocommerce' ); ?></th>
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
										value="1" <?php checked( (bool) ( $counterhand_values[ $counterhand_group->read_option_key() ] ?? false ) ); ?> <?php disabled( ! $can_save ); ?>>
								</td>
								<td>
									<?php if ( ! $counterhand_group->has_write() ) : ?>
										<span class="counterhand-muted" aria-hidden="true">—</span>
										<span class="screen-reader-text"><?php esc_html_e( 'AI can only read this area.', 'counterhand-mcp-for-woocommerce' ); ?></span>
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
											value="1" <?php checked( (bool) ( $counterhand_values[ $counterhand_group->write_option_key() ] ?? false ) ); ?> <?php disabled( ! $can_save ); ?>>
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
				<span class="counterhand-card__desc"><?php esc_html_e( 'How fast one app may call the store.', 'counterhand-mcp-for-woocommerce' ); ?></span>
			</span>
		</div>
		<div class="counterhand-card__body">
			<div class="counterhand-field">
				<label class="counterhand-label" for="counterhand-rate-limit"><?php esc_html_e( 'Requests per minute, per app', 'counterhand-mcp-for-woocommerce' ); ?></label>
				<input type="number" id="counterhand-rate-limit" min="1" max="1000"
					name="<?php echo esc_attr( $counterhand_option ); ?>[rate_limit_per_minute]"
					value="<?php echo esc_attr( (string) $counterhand_values['rate_limit_per_minute'] ); ?>" <?php disabled( ! $can_save ); ?>>
			</div>
		</div>
	</div>

	<div class="counterhand-card">
		<div class="counterhand-card__head">
			<span class="counterhand-card__heading">
				<h3 class="counterhand-card__title"><?php esc_html_e( 'Activity', 'counterhand-mcp-for-woocommerce' ); ?></h3>
				<span class="counterhand-card__desc"><?php esc_html_e( 'Records what each app and the chat did. Emails and phone numbers are masked before anything is stored. Refunds are always recorded.', 'counterhand-mcp-for-woocommerce' ); ?></span>
			</span>
		</div>
		<div class="counterhand-card__body">
			<div class="counterhand-field">
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $counterhand_option ); ?>[action_log_enabled]" value="1"
						<?php checked( (bool) ( $counterhand_values['action_log_enabled'] ?? false ) ); ?> <?php disabled( ! $can_save ); ?>>
					<?php esc_html_e( 'Record every call', 'counterhand-mcp-for-woocommerce' ); ?>
				</label>
			</div>

			<div class="counterhand-field">
				<label class="counterhand-label" for="counterhand-retention"><?php esc_html_e( 'Keep records for (days)', 'counterhand-mcp-for-woocommerce' ); ?></label>
				<input type="number" id="counterhand-retention" min="1" max="365"
					name="<?php echo esc_attr( $counterhand_option ); ?>[log_retention_days]"
					value="<?php echo esc_attr( (string) ( $counterhand_values['log_retention_days'] ?? 30 ) ); ?>" <?php disabled( ! $can_save ); ?>>
			</div>
		</div>
	</div>

	<?php if ( $can_save ) : ?>
		<?php submit_button( __( 'Save access', 'counterhand-mcp-for-woocommerce' ) ); ?>
	<?php endif; ?>
</form>
