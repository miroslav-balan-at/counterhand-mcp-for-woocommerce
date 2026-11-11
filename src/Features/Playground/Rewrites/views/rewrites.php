<?php
/**
 * Bulk rewrites: start a job for a category, then approve each new text one by one.
 *
 * @var string|null                                                     $blocked
 * @var bool                                                            $keeps_undo
 * @var bool                                                            $can_apply
 * @var list<\Counterhand\Features\Playground\Rewrites\RewriteRow>      $rows
 * @var mixed                                                           $notice
 * @var list<\Counterhand\Features\Playground\Rewrites\RewriteField>    $fields
 * @var int                                                             $limit
 */

declare( strict_types=1 );

use Counterhand\Features\Playground\Rewrites\RewritesFeature;
use Counterhand\Features\Playground\Rewrites\RewriteStatus;
use Counterhand\Features\Settings\AdminScreen;

defined( 'ABSPATH' ) || exit;

$counterhand_items   = array_map( static fn ( $row ) => $row->item, $rows );
$counterhand_waiting = count( array_filter( $counterhand_items, static fn ( $item ): bool => RewriteStatus::Queued === $item->status ) );
$counterhand_input   = array_sum( array_map( static fn ( $item ): int => $item->usage->input, $counterhand_items ) );
$counterhand_output  = array_sum( array_map( static fn ( $item ): int => $item->usage->output, $counterhand_items ) );
?>
<?php if ( is_string( $notice ) ) : ?>
	<div class="notice notice-info counterhand-rewrites__notice" role="status"><p><?php echo esc_html( $notice ); ?></p></div>
<?php endif; ?>

<div class="counterhand-rewrites">
	<section class="counterhand-card" aria-labelledby="counterhand-rewrites-start">
		<div class="counterhand-card__head">
			<span class="counterhand-card__heading">
				<h2 class="counterhand-card__title" id="counterhand-rewrites-start"><?php esc_html_e( 'Start a rewrite', 'counterhand-mcp-for-woocommerce' ); ?></h2>
				<span class="counterhand-card__desc">
					<?php
					printf(
						/* translators: %d: maximum number of products in one job. */
						esc_html__( 'The model connected on the Chat screen writes a new text for up to %d products in the category. Nothing changes on your store until you approve a text below.', 'counterhand-mcp-for-woocommerce' ),
						(int) $limit
					);
					?>
				</span>
			</span>
		</div>
		<div class="counterhand-card__body">
			<?php if ( null !== $blocked ) : ?>
				<p class="counterhand-chip counterhand-chip--warn"><?php echo esc_html( $blocked ); ?></p>
			<?php else : ?>
				<?php if ( ! $keeps_undo ) : ?>
					<p class="counterhand-chip counterhand-chip--warn"><?php esc_html_e( 'Undo history is off, so applied texts cannot be undone here. To keep that option, switch on Can read and Can change for Undo history in Access.', 'counterhand-mcp-for-woocommerce' ); ?></p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="counterhand-rewrites__form">
					<input type="hidden" name="action" value="<?php echo esc_attr( RewritesFeature::START_ACTION ); ?>">
					<?php wp_nonce_field( RewritesFeature::START_ACTION ); ?>
					<p class="counterhand-field">
						<label class="counterhand-label" for="counterhand_rewrite_category"><?php esc_html_e( 'Category', 'counterhand-mcp-for-woocommerce' ); ?></label>
						<?php
						wp_dropdown_categories(
							[
								'taxonomy'     => 'product_cat',
								'name'         => 'counterhand_rewrite_category',
								'id'           => 'counterhand_rewrite_category',
								'hierarchical' => true,
								'show_count'   => true,
								'hide_empty'   => true,
							]
						);
						?>
					</p>
					<p class="counterhand-field">
						<label class="counterhand-label" for="counterhand_rewrite_field"><?php esc_html_e( 'Text to rewrite', 'counterhand-mcp-for-woocommerce' ); ?></label>
						<select name="counterhand_rewrite_field" id="counterhand_rewrite_field">
							<?php foreach ( $fields as $counterhand_field ) : ?>
								<option value="<?php echo esc_attr( $counterhand_field->value ); ?>"><?php echo esc_html( $counterhand_field->label() ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p class="counterhand-field">
						<label class="counterhand-label" for="counterhand_rewrite_instruction"><?php esc_html_e( 'What should the new text do differently?', 'counterhand-mcp-for-woocommerce' ); ?></label>
						<textarea name="counterhand_rewrite_instruction" id="counterhand_rewrite_instruction" rows="3" class="large-text" required placeholder="<?php esc_attr_e( 'For example: shorter, friendlier, and start with what the product is for.', 'counterhand-mcp-for-woocommerce' ); ?>"></textarea>
					</p>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Write new texts', 'counterhand-mcp-for-woocommerce' ); ?></button></p>
				</form>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( [] !== $rows ) : ?>
		<section class="counterhand-card" aria-labelledby="counterhand-rewrites-review">
			<div class="counterhand-card__head">
				<span class="counterhand-card__heading">
					<h2 class="counterhand-card__title" id="counterhand-rewrites-review"><?php esc_html_e( 'Review', 'counterhand-mcp-for-woocommerce' ); ?></h2>
					<span class="counterhand-card__desc">
						<?php
						printf(
							/* translators: 1: tokens sent to the model, 2: tokens the model wrote. */
							esc_html__( 'Model usage so far: %1$s tokens in, %2$s tokens out.', 'counterhand-mcp-for-woocommerce' ),
							esc_html( number_format_i18n( $counterhand_input ) ),
							esc_html( number_format_i18n( $counterhand_output ) )
						);
						?>
					</span>
				</span>
				<?php if ( $counterhand_waiting > 0 ) : ?>
					<a class="button" href="<?php echo esc_url( AdminScreen::Rewrites->url() ); ?>">
						<?php
						printf(
							/* translators: %d: number of products still waiting for the model. */
							esc_html__( 'Refresh (%d waiting)', 'counterhand-mcp-for-woocommerce' ),
							(int) $counterhand_waiting
						);
						?>
					</a>
				<?php endif; ?>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="counterhand-card__body">
				<input type="hidden" name="action" value="<?php echo esc_attr( RewritesFeature::REVIEW_ACTION ); ?>">
				<?php wp_nonce_field( RewritesFeature::REVIEW_ACTION ); ?>
				<table class="widefat striped counterhand-rewrites__table">
					<thead>
						<tr>
							<td class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Select', 'counterhand-mcp-for-woocommerce' ); ?></span></td>
							<th scope="col"><?php esc_html_e( 'Product', 'counterhand-mcp-for-woocommerce' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Current text', 'counterhand-mcp-for-woocommerce' ); ?></th>
							<th scope="col"><?php esc_html_e( 'New text', 'counterhand-mcp-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $counterhand_row ) : ?>
							<?php $counterhand_item = $counterhand_row->item; ?>
							<tr data-product="<?php echo esc_attr( (string) $counterhand_item->product_id ); ?>">
								<th scope="row" class="check-column">
									<?php if ( in_array( $counterhand_item->status, [ RewriteStatus::Proposed, RewriteStatus::Applied ], true ) ) : ?>
										<input type="checkbox" name="counterhand_rewrite_items[]" value="<?php echo esc_attr( (string) $counterhand_item->id ); ?>" id="counterhand-rewrite-<?php echo esc_attr( (string) $counterhand_item->id ); ?>">
									<?php endif; ?>
								</th>
								<td>
									<label for="counterhand-rewrite-<?php echo esc_attr( (string) $counterhand_item->id ); ?>">
										<strong><?php echo esc_html( '' !== $counterhand_item->product_name ? $counterhand_item->product_name : '#' . $counterhand_item->product_id ); ?></strong>
									</label>
									<br><?php echo esc_html( $counterhand_item->field->label() ); ?>
									<br><span class="counterhand-rewrites__status counterhand-rewrites__status--<?php echo esc_attr( $counterhand_item->status->value ); ?>"><?php echo esc_html( $counterhand_item->status->label() ); ?></span>
									<?php if ( '' !== $counterhand_item->message ) : ?>
										<br><span class="counterhand-muted"><?php echo esc_html( $counterhand_item->message ); ?></span>
									<?php endif; ?>
									<?php if ( ! $counterhand_row->product_exists ) : ?>
										<br><span class="counterhand-muted"><?php esc_html_e( 'This product no longer exists.', 'counterhand-mcp-for-woocommerce' ); ?></span>
									<?php elseif ( null !== $counterhand_row->edit_url ) : ?>
										<br><a href="<?php echo esc_url( $counterhand_row->edit_url ); ?>"><?php esc_html_e( 'Edit product', 'counterhand-mcp-for-woocommerce' ); ?></a>
									<?php endif; ?>
								</td>
								<td><div class="counterhand-rewrites__text"><?php echo wp_kses_post( $counterhand_item->original ); ?></div></td>
								<td><div class="counterhand-rewrites__text counterhand-rewrites__text--new"><?php echo wp_kses_post( $counterhand_item->proposal ); ?></div></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="counterhand-actions">
					<button type="submit" class="button button-primary" name="counterhand_rewrite_decision" value="apply" <?php disabled( ! $can_apply ); ?>><?php esc_html_e( 'Apply ticked texts', 'counterhand-mcp-for-woocommerce' ); ?></button>
					<button type="submit" class="button" name="counterhand_rewrite_decision" value="discard"><?php esc_html_e( 'Discard ticked texts', 'counterhand-mcp-for-woocommerce' ); ?></button>
					<button type="submit" class="button" name="counterhand_rewrite_decision" value="undo"><?php esc_html_e( 'Undo ticked texts', 'counterhand-mcp-for-woocommerce' ); ?></button>
				</p>
				<?php if ( ! $can_apply ) : ?>
					<p class="counterhand-chip counterhand-chip--warn"><?php esc_html_e( 'Products cannot be changed by AI on this store. Switch on Can change for Products in Access, then apply again.', 'counterhand-mcp-for-woocommerce' ); ?></p>
				<?php endif; ?>
				<p class="counterhand-muted"><?php esc_html_e( 'Apply writes only ticked texts that are ready to review, discard drops them, and undo puts back ticked texts already applied. Read each one first: the model can get facts wrong.', 'counterhand-mcp-for-woocommerce' ); ?></p>
			</form>
		</section>
	<?php endif; ?>
</div>
