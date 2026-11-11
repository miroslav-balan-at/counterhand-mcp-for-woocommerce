<?php
/**
 * Access, the other plugins' form: each plugin's AI actions ship off and are granted
 * to an app on their own. Its own form, so saving it never resets the store's areas.
 *
 * @var \Counterhand\Features\Settings\PluginSettings                 $settings
 * @var list<\Counterhand\Features\Tokens\Domain\ScopeGroupInterface> $integrations
 * @var list<string>                                                  $supported
 * @var bool                                                          $other_mcp_server
 * @var bool                                                          $can_save
 */

declare( strict_types=1 );

use Counterhand\Features\Settings\AdminScreen;
use Counterhand\Features\Settings\PluginSettings;
use Counterhand\Features\Settings\SettingsAccess;
use Counterhand\Features\Settings\SettingsForm;

defined( 'ABSPATH' ) || exit;

$counterhand_option = PluginSettings::OPTION;
?>
<h2 class="counterhand-group-title" id="<?php echo esc_attr( AdminScreen::OTHER_PLUGINS_ANCHOR ); ?>"><?php esc_html_e( 'Other plugins', 'counterhand-mcp-for-woocommerce' ); ?></h2>
<p class="description counterhand-integrations-intro">
	<?php esc_html_e( 'AI actions other plugins offer, under the same rules as the store areas. Each plugin still checks its own permissions. An app reaches a plugin only if it is switched on here and the app was approved for it; to add one to an app already connected, connect the app again.', 'counterhand-mcp-for-woocommerce' ); ?>
</p>
<?php if ( [] !== $supported ) : ?>
	<p class="description counterhand-integrations-intro">
		<?php
		printf(
			/* translators: %s: plugin names, e.g. "WPML, Yoast SEO". */
			esc_html__( 'Also works with these plugins once they are active: %s.', 'counterhand-mcp-for-woocommerce' ),
			esc_html( wp_sprintf( '%l', $supported ) )
		);
		?>
	</p>
<?php endif; ?>

<?php if ( [] === $integrations ) : ?>
	<div class="counterhand-card">
		<div class="counterhand-card__head">
			<span class="counterhand-card__heading">
				<h2 class="counterhand-card__title"><?php esc_html_e( 'No other plugin offers AI actions yet', 'counterhand-mcp-for-woocommerce' ); ?></h2>
				<span class="counterhand-card__desc"><?php esc_html_e( 'A plugin appears here once it is active and has something to offer. Some, such as SEO plugins, need their own setup finished first.', 'counterhand-mcp-for-woocommerce' ); ?></span>
			</span>
		</div>
	</div>
	<?php return; ?>
<?php endif; ?>

<form method="post" action="options.php" class="counterhand-settings-form">
	<?php if ( $can_save ) : ?>
		<?php settings_fields( SettingsAccess::OPTION_GROUP ); ?>
		<input type="hidden" name="<?php echo esc_attr( $counterhand_option ); ?>[form]" value="<?php echo esc_attr( SettingsForm::Integrations->value ); ?>">
	<?php endif; ?>

	<?php foreach ( $integrations as $counterhand_group ) : ?>
		<div class="counterhand-card">
			<div class="counterhand-card__head">
				<span class="counterhand-card__heading">
					<h2 class="counterhand-card__title"><?php echo esc_html( $counterhand_group->label() ); ?></h2>
					<span class="counterhand-card__desc"><?php echo esc_html( $counterhand_group->description() ); ?></span>
				</span>
			</div>
			<div class="counterhand-card__body">
				<div class="counterhand-field">
					<label>
						<input type="checkbox"
							id="counterhand-<?php echo esc_attr( $counterhand_group->read_option_key() ); ?>"
							name="<?php echo esc_attr( $counterhand_option ); ?>[<?php echo esc_attr( $counterhand_group->read_option_key() ); ?>]"
							value="1" <?php checked( $settings->is_group_read_enabled( $counterhand_group ) ); ?> <?php disabled( ! $can_save ); ?>>
						<?php echo esc_html( $counterhand_group->read_scope()->description() ); ?>
					</label>
				</div>
				<?php if ( $counterhand_group->has_write() && null !== $counterhand_group->write_scope() ) : ?>
					<div class="counterhand-field">
						<label>
							<input type="checkbox"
								id="counterhand-<?php echo esc_attr( $counterhand_group->write_option_key() ); ?>"
								name="<?php echo esc_attr( $counterhand_option ); ?>[<?php echo esc_attr( $counterhand_group->write_option_key() ); ?>]"
								value="1" <?php checked( $settings->is_group_write_enabled( $counterhand_group ) ); ?> <?php disabled( ! $can_save ); ?>>
							<?php echo esc_html( $counterhand_group->write_scope()->description() ); ?>
						</label>
					</div>
				<?php endif; ?>
			</div>
		</div>
	<?php endforeach; ?>

	<?php if ( $can_save ) : ?>
		<?php submit_button( __( 'Save other plugins', 'counterhand-mcp-for-woocommerce' ) ); ?>
	<?php endif; ?>
</form>
