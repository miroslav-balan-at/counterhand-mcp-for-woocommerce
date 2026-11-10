<?php
/**
 * Store notes: what every chat on this store is told, written by an administrator.
 *
 * @var string $store_memory
 * @var bool   $can_edit_memory
 */

declare( strict_types=1 );

use Counterhand\Features\Playground\ChatNotesFeature;
use Counterhand\Features\Playground\StoreMemory;

defined( 'ABSPATH' ) || exit;
?>
<details class="counterhand-chat-tools counterhand-store-memory">
	<summary class="counterhand-chat-tools__summary">
		<span class="counterhand-chat-tools__count"><?php esc_html_e( 'Store notes', 'counterhand-mcp-for-woocommerce' ); ?></span>
		<span class="counterhand-chat-tools__summary-label">
			<?php
			echo '' === $store_memory
				? esc_html__( 'none yet', 'counterhand-mcp-for-woocommerce' )
				: esc_html__( 'sent with every chat message', 'counterhand-mcp-for-woocommerce' );
			?>
		</span>
	</summary>

	<div class="counterhand-chat-tools__form">
		<p class="counterhand-chat-tools__lede">
			<?php esc_html_e( 'What the assistant should always know, such as your house style, a supplier who is late, or how you name products. Every chat on this store gets these notes, and so does the model provider.', 'counterhand-mcp-for-woocommerce' ); ?>
		</p>

		<?php if ( $can_edit_memory ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( ChatNotesFeature::MEMORY_ACTION ); ?>">
				<?php wp_nonce_field( ChatNotesFeature::MEMORY_ACTION ); ?>
				<label class="screen-reader-text" for="counterhand-store-memory"><?php esc_html_e( 'Store notes', 'counterhand-mcp-for-woocommerce' ); ?></label>
				<textarea id="counterhand-store-memory" name="counterhand_store_memory" rows="5" class="large-text" maxlength="<?php echo esc_attr( (string) StoreMemory::MAX_LENGTH ); ?>"><?php echo esc_textarea( $store_memory ); ?></textarea>
				<p><button type="submit" class="button"><?php esc_html_e( 'Save notes', 'counterhand-mcp-for-woocommerce' ); ?></button></p>
			</form>
		<?php elseif ( '' !== $store_memory ) : ?>
			<p class="counterhand-store-memory__text"><?php echo nl2br( esc_html( $store_memory ) ); ?></p>
			<p class="counterhand-muted"><?php esc_html_e( 'Only an administrator can change these notes.', 'counterhand-mcp-for-woocommerce' ); ?></p>
		<?php else : ?>
			<p class="counterhand-muted"><?php esc_html_e( 'No notes yet. Only an administrator can add them.', 'counterhand-mcp-for-woocommerce' ); ?></p>
		<?php endif; ?>
	</div>
</details>
