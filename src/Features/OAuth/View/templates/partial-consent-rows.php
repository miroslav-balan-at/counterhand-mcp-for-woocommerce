<?php
/**
 * The group rows of one consent section. Included by consent.php once per
 * section, inside whichever wrapper that section needs.
 *
 * Each group contributes one row holding the scopes the client asked for, so
 * the read and write axes of the same data sit together and an admin skimming
 * for "what can it change" reads down one column instead of across a flat list.
 *
 * @var \Counterhand\Features\OAuth\View\ConsentSection $ctrh_section
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<?php foreach ( $ctrh_section->groups as $ctrh_group ) : ?>
	<div class="ctrh-scope-group">
		<span class="ctrh-scope-group__name"><?php echo esc_html( $ctrh_group->group->label() ); ?></span>

		<?php foreach ( [ $ctrh_group->read, $ctrh_group->write ] as $ctrh_scope ) : ?>
			<?php if ( null !== $ctrh_scope ) : ?>
				<?php $ctrh_id = 'ctrh-scope-' . sanitize_html_class( $ctrh_scope->value ); ?>
				<label class="ctrh-scope <?php echo $ctrh_scope->is_write() ? 'ctrh-scope--write' : ''; ?>" for="<?php echo esc_attr( $ctrh_id ); ?>">
					<input type="checkbox" id="<?php echo esc_attr( $ctrh_id ); ?>"
						name="ctrh_scopes[]" value="<?php echo esc_attr( $ctrh_scope->value ); ?>"
						<?php checked( $ctrh_group->pre_checked() ); ?>>
					<span class="ctrh-scope__text">
						<span class="ctrh-scope__name"><?php echo esc_html( $ctrh_scope->label() ); ?></span>
						<span class="ctrh-scope__desc"><?php echo esc_html( $ctrh_scope->description() ); ?></span>
					</span>
					<?php if ( $ctrh_scope->is_write() ) : ?>
						<span class="ctrh-tag ctrh-tag--write"><?php esc_html_e( 'Can change data', 'counterhand-mcp-for-woocommerce' ); ?></span>
					<?php endif; ?>
				</label>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
<?php endforeach; ?>
