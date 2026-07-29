<?php
/**
 * Which areas of the store this chat may reach.
 *
 * Collapsed by default: it is a set-once decision, not something to meet on the
 * way to every message. The count in the summary is what makes opening it worth
 * considering — a model handed too many tool schemas starts picking badly long
 * before any provider limit is hit.
 *
 * @var list<\AgentGateMcp\Shared\Tool\ToolInterface>  $tools
 * @var list<\AgentGateMcp\Shared\Tool\ToolGroup>      $chat_groups
 * @var list<\AgentGateMcp\Shared\Tool\ToolSection>    $tool_sections
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$agmcp_selected = array_map( static fn ( $group ): string => $group->value, $chat_groups );
?>
<details class="agmcp-chat-tools">
	<summary class="agmcp-chat-tools__summary">
		<?php
		printf(
			/* translators: %d: how many tools the chat can currently call. */
			esc_html( _n( 'Chat can use %d tool', 'Chat can use %d tools', count( $tools ), 'agentgate-mcp-for-woocommerce' ) ),
			count( $tools )
		);
		?>
	</summary>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="agmcp-chat-tools__form">
		<input type="hidden" name="action" value="agmcp_save_chat_tools">
		<?php wp_nonce_field( 'agmcp_save_chat_tools' ); ?>

		<p class="agmcp-chat-tools__lede">
			<?php esc_html_e( 'Every area you tick here is described to the model on every message, so keep it to what you actually ask about. Areas switched off in Settings stay off here too.', 'agentgate-mcp-for-woocommerce' ); ?>
		</p>

		<?php foreach ( $tool_sections as $agmcp_section ) : ?>
			<fieldset class="agmcp-chat-tools__section">
				<legend><?php echo esc_html( $agmcp_section->label() ); ?></legend>

				<?php foreach ( $agmcp_section->groups() as $agmcp_group ) : ?>
					<label class="agmcp-chat-tools__group">
						<input type="checkbox" name="agmcp_chat_groups[]"
							value="<?php echo esc_attr( $agmcp_group->value ); ?>"
							<?php checked( in_array( $agmcp_group->value, $agmcp_selected, true ) ); ?>>
						<span><?php echo esc_html( $agmcp_group->label() ); ?></span>
					</label>
				<?php endforeach; ?>
			</fieldset>
		<?php endforeach; ?>

		<?php submit_button( __( 'Save areas', 'agentgate-mcp-for-woocommerce' ), 'secondary', 'submit', false ); ?>
	</form>
</details>
