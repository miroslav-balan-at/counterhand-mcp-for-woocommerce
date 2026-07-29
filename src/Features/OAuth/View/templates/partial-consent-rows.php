<?php
/**
 * The group rows of one consent section. Included by consent.php once per
 * section, inside whichever wrapper that section needs.
 *
 * Each group contributes one row holding the scopes the client asked for, so
 * the read and write axes of the same data sit together and an admin skimming
 * for "what can it change" reads down one column instead of across a flat list.
 *
 * @var \AgentGateMcp\Features\OAuth\View\ConsentSection $agmcp_section
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<?php foreach ( $agmcp_section->groups as $agmcp_group ) : ?>
	<div class="agmcp-scope-group">
		<span class="agmcp-scope-group__name"><?php echo esc_html( $agmcp_group->group->label() ); ?></span>

		<?php foreach ( [ $agmcp_group->read, $agmcp_group->write ] as $agmcp_scope ) : ?>
			<?php if ( null !== $agmcp_scope ) : ?>
				<?php $agmcp_id = 'agmcp-scope-' . sanitize_html_class( $agmcp_scope->value ); ?>
				<label class="agmcp-scope <?php echo $agmcp_scope->is_write() ? 'agmcp-scope--write' : ''; ?>" for="<?php echo esc_attr( $agmcp_id ); ?>">
					<input type="checkbox" id="<?php echo esc_attr( $agmcp_id ); ?>"
						name="agmcp_scopes[]" value="<?php echo esc_attr( $agmcp_scope->value ); ?>"
						<?php checked( $agmcp_group->pre_checked() ); ?>>
					<span class="agmcp-scope__text">
						<span class="agmcp-scope__name"><?php echo esc_html( $agmcp_scope->label() ); ?></span>
						<span class="agmcp-scope__desc"><?php echo esc_html( $agmcp_scope->description() ); ?></span>
					</span>
					<?php if ( $agmcp_scope->is_write() ) : ?>
						<span class="agmcp-tag agmcp-tag--write"><?php esc_html_e( 'Can change data', 'agentgate-mcp-for-woocommerce' ); ?></span>
					<?php endif; ?>
				</label>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
<?php endforeach; ?>
