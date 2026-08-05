<?php
/**
 * The group rows of one consent section. Included by consent.php once per
 * section, inside whichever wrapper that section needs.
 *
 * Each group contributes one row holding the scopes the client asked for, so
 * the read and write axes of the same data sit together and an admin skimming
 * for "what can it change" reads down one column instead of across a flat list.
 *
 * @var \Counterhand\Features\OAuth\View\ConsentSection $counterhand_section
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<?php foreach ( $counterhand_section->groups as $counterhand_group ) : ?>
	<div class="counterhand-scope-group">
		<span class="counterhand-scope-group__name"><?php echo esc_html( $counterhand_group->group->label() ); ?></span>

		<?php foreach ( [ $counterhand_group->read, $counterhand_group->write ] as $counterhand_scope ) : ?>
			<?php if ( null !== $counterhand_scope ) : ?>
				<?php $counterhand_id = 'counterhand-scope-' . sanitize_html_class( $counterhand_scope->value ); ?>
				<label class="counterhand-scope <?php echo $counterhand_scope->is_write() ? 'counterhand-scope--write' : ''; ?>" for="<?php echo esc_attr( $counterhand_id ); ?>">
					<input type="checkbox" id="<?php echo esc_attr( $counterhand_id ); ?>"
						name="counterhand_scopes[]" value="<?php echo esc_attr( $counterhand_scope->value ); ?>"
						<?php checked( $counterhand_group->pre_checked() ); ?>>
					<span class="counterhand-scope__text">
						<span class="counterhand-scope__name"><?php echo esc_html( $counterhand_scope->label() ); ?></span>
						<span class="counterhand-scope__desc"><?php echo esc_html( $counterhand_scope->description() ); ?></span>
					</span>
					<?php if ( $counterhand_scope->is_write() ) : ?>
						<span class="counterhand-tag counterhand-tag--write"><?php esc_html_e( 'Can change data', 'counterhand-mcp-for-woocommerce' ); ?></span>
					<?php endif; ?>
				</label>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
<?php endforeach; ?>
