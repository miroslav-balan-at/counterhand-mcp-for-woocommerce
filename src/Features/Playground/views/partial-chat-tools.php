<?php
/**
 * Which areas of the store this chat may reach.
 *
 * Collapsed by default: it is a set-once decision, not something to meet on the
 * way to every message. The count in the summary is what makes opening it worth
 * considering — a model handed too many tool schemas starts picking badly long
 * before any provider limit is hit.
 *
 * Areas the store withholds stay tickable rather than disabled. A disabled box
 * says "no" without saying why or where to fix it, and the choice is still worth
 * recording for the moment the area is switched on in Settings.
 *
 * @var list<\Counterhand\Shared\Tool\ToolInterface>              $tools
 * @var list<\Counterhand\Shared\Tool\ToolGroup>                  $chat_groups
 * @var list<\Counterhand\Shared\Tool\ToolSection>                $tool_sections
 * @var array<string, \Counterhand\Features\Playground\ChatArea>  $chat_areas
 * @var string                                                   $store_settings_url
 * @var int|null                                                 $tool_limit Null when the model searches its tools.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$counterhand_overruled = array_filter( $chat_areas, static fn ( $area ): bool => $area->is_selected && $area->is_overruled_by_store() );
$counterhand_readonly  = array_filter( $chat_areas, static fn ( $area ): bool => $area->is_read_only_by_store() );
$counterhand_withheld  = array_sum( array_map( static fn ( $area ): int => $area->tool_count, $counterhand_overruled ) );

// Over the limit every message fails, so this outranks anything else the panel
// has to say — including its own tool count, which is the cause here.
$counterhand_over_limit = null !== $tool_limit && count( $tools ) > $tool_limit;
$counterhand_excess     = null !== $tool_limit ? count( $tools ) - $tool_limit : 0;
?>
<details class="counterhand-chat-tools"<?php echo $counterhand_over_limit ? ' open' : ''; ?>>
	<summary class="counterhand-chat-tools__summary">
		<span class="counterhand-chat-tools__count<?php echo $counterhand_over_limit ? ' counterhand-chat-tools__count--over' : ''; ?>">
			<?php
			printf(
				/* translators: %d: how many tools the chat can currently call. */
				esc_html( _n( '%d tool', '%d tools', count( $tools ), 'counterhand-mcp-for-woocommerce' ) ),
				count( $tools )
			);
			?>
		</span>
		<span class="counterhand-chat-tools__summary-label"><?php esc_html_e( 'available to chat', 'counterhand-mcp-for-woocommerce' ); ?></span>

		<?php if ( $counterhand_over_limit ) : ?>
			<span class="counterhand-pill counterhand-pill--error">
				<?php
				printf(
					/* translators: %d: the most tools one message can carry. */
					esc_html__( 'over the %d-tool limit', 'counterhand-mcp-for-woocommerce' ),
					(int) $tool_limit
				);
				?>
			</span>
		<?php elseif ( [] !== $counterhand_overruled ) : ?>
			<span class="counterhand-pill counterhand-pill--warn">
				<?php
				printf(
					/* translators: %d: how many further tools are waiting on a store setting. */
					esc_html( _n( '%d more waiting on Settings', '%d more waiting on Settings', $counterhand_withheld, 'counterhand-mcp-for-woocommerce' ) ),
					(int) $counterhand_withheld
				);
				?>
			</span>
		<?php endif; ?>
	</summary>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="counterhand-chat-tools__form">
		<input type="hidden" name="action" value="counterhand_save_chat_tools">
		<?php wp_nonce_field( 'counterhand_save_chat_tools' ); ?>

		<p class="counterhand-chat-tools__lede">
			<?php esc_html_e( 'Each area you pick is described to the model on every message, so keep it to what you actually ask about.', 'counterhand-mcp-for-woocommerce' ); ?>
		</p>

		<?php if ( $counterhand_over_limit ) : ?>
			<div class="counterhand-callout counterhand-callout--error">
				<span class="counterhand-callout__icon" aria-hidden="true">!</span>
				<div class="counterhand-callout__body">
					<strong class="counterhand-callout__title">
						<?php
						printf(
							/* translators: 1: how many tools are selected, 2: the per-message maximum. */
							esc_html__( 'Too many areas picked: %1$d tools, and one message can carry %2$d.', 'counterhand-mcp-for-woocommerce' ),
							count( $tools ),
							(int) $tool_limit
						);
						?>
					</strong>
					<p class="counterhand-callout__text">
						<?php
						printf(
							/* translators: %d: how many tools have to go. */
							esc_html( _n( 'Every message fails until you untick at least %d tool below. Past this many, a model starts picking the wrong tool anyway — and the definitions cost more than the conversation. Unticking here only narrows the chat; your other AI apps keep everything.', 'Every message fails until you untick at least %d tools below. Past this many, a model starts picking the wrong tool anyway — and the definitions cost more than the conversation. Unticking here only narrows the chat; your other AI apps keep everything.', $counterhand_excess, 'counterhand-mcp-for-woocommerce' ) ),
							(int) $counterhand_excess
						);
						?>
					</p>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( [] !== $counterhand_overruled ) : ?>
			<div class="counterhand-callout counterhand-callout--warn">
				<span class="counterhand-callout__icon" aria-hidden="true">!</span>
				<div class="counterhand-callout__body">
					<strong class="counterhand-callout__title">
						<?php
						printf(
							/* translators: %s: comma-separated list of area names. */
							esc_html__( '%s is switched off for the whole store', 'counterhand-mcp-for-woocommerce' ),
							esc_html(
								implode( ', ', array_map( static fn ( $area ): string => $area->group->label(), $counterhand_overruled ) )
							)
						);
						?>
					</strong>
					<p class="counterhand-callout__text">
						<?php esc_html_e( 'Picking it here is remembered, but the chat cannot reach it until the store exposes it. Settings decides what every AI client may touch; this panel only narrows it further for chat.', 'counterhand-mcp-for-woocommerce' ); ?>
					</p>
					<a class="counterhand-callout__action" href="<?php echo esc_url( $store_settings_url ); ?>">
						<?php esc_html_e( 'Switch it on in Settings', 'counterhand-mcp-for-woocommerce' ); ?>
						<span aria-hidden="true">&rarr;</span>
					</a>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( [] !== $counterhand_readonly ) : ?>
			<p class="counterhand-chat-tools__hint">
				<?php
				printf(
					/* translators: %s: comma-separated list of area names. */
					esc_html__( 'The chat can read but not change %s — writing is still off for it in Settings.', 'counterhand-mcp-for-woocommerce' ),
					esc_html(
						implode( ', ', array_map( static fn ( $area ): string => $area->group->label(), $counterhand_readonly ) )
					)
				);
				?>
			</p>
		<?php endif; ?>

		<?php foreach ( $tool_sections as $counterhand_section ) : ?>
			<fieldset class="counterhand-chat-tools__section">
				<legend class="counterhand-chat-tools__legend"><?php echo esc_html( $counterhand_section->label() ); ?></legend>

				<div class="counterhand-chat-tools__grid">
					<?php
					foreach ( $counterhand_section->groups() as $counterhand_group ) :
						$counterhand_area = $chat_areas[ $counterhand_group->value ];
						?>
						<label class="counterhand-area<?php echo $counterhand_area->is_overruled_by_store() ? ' counterhand-area--withheld' : ''; ?>">
							<input class="counterhand-area__input" type="checkbox" name="counterhand_chat_groups[]"
								value="<?php echo esc_attr( $counterhand_group->value ); ?>"
								<?php checked( $counterhand_area->is_selected ); ?>>
							<span class="counterhand-area__name"><?php echo esc_html( $counterhand_group->label() ); ?></span>

							<?php if ( $counterhand_area->is_overruled_by_store() ) : ?>
								<span class="counterhand-area__badge">
									<?php
									printf(
										/* translators: %d: how many tools this area would add. */
										esc_html( _n( '%d tool off', '%d tools off', $counterhand_area->tool_count, 'counterhand-mcp-for-woocommerce' ) ),
										(int) $counterhand_area->tool_count
									);
									?>
								</span>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endforeach; ?>

		<?php submit_button( __( 'Save areas', 'counterhand-mcp-for-woocommerce' ), 'secondary', 'submit', false ); ?>
	</form>
</details>
