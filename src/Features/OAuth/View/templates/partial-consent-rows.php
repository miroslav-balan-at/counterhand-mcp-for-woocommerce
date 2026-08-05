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

		<?php foreach ( $counterhand_group->scopes as $counterhand_row ) : ?>
			<?php
			$counterhand_scope = $counterhand_row->scope;
			$counterhand_id    = 'counterhand-scope-' . sanitize_html_class( $counterhand_scope->value );
			$counterhand_off   = ! $counterhand_row->available();
			?>
			<label
				class="counterhand-scope<?php echo $counterhand_scope->is_write() ? ' counterhand-scope--write' : ''; ?><?php echo $counterhand_off ? ' counterhand-scope--off' : ''; ?>"
				for="<?php echo esc_attr( $counterhand_id ); ?>"
				<?php if ( $counterhand_off ) : ?>
					title="<?php echo esc_attr( $counterhand_row->unavailable_reason() ); ?>"
				<?php endif; ?>
			>
				<input type="checkbox" id="<?php echo esc_attr( $counterhand_id ); ?>"
					name="counterhand_scopes[]" value="<?php echo esc_attr( $counterhand_scope->value ); ?>"
					<?php checked( $counterhand_row->pre_checked ); ?>
					<?php disabled( $counterhand_off ); ?>
					<?php if ( $counterhand_off ) : ?>
						aria-describedby="<?php echo esc_attr( $counterhand_id ); ?>-off"
					<?php endif; ?>>
				<span class="counterhand-scope__text">
					<span class="counterhand-scope__name"><?php echo esc_html( $counterhand_scope->label() ); ?></span>
					<span class="counterhand-scope__desc"><?php echo esc_html( $counterhand_scope->description() ); ?></span>
					<?php if ( $counterhand_off ) : ?>
						<?php // Tooltips need a pointer; this line is the same answer for touch and screen readers. ?>
						<span class="counterhand-scope__off-hint" id="<?php echo esc_attr( $counterhand_id ); ?>-off">
							<?php echo esc_html( $counterhand_row->unavailable_reason() ); ?>
						</span>
					<?php endif; ?>
				</span>
				<?php if ( $counterhand_off ) : ?>
					<span class="counterhand-tag counterhand-tag--off"><?php echo esc_html( $counterhand_row->availability->tag() ); ?></span>
				<?php elseif ( $counterhand_scope->is_write() ) : ?>
					<span class="counterhand-tag counterhand-tag--write"><?php esc_html_e( 'Can change data', 'counterhand-mcp-for-woocommerce' ); ?></span>
				<?php endif; ?>
			</label>
		<?php endforeach; ?>
	</div>
<?php endforeach; ?>
