<?php
/**
 * Connect AI apps: one URL, pasted into whichever app you already use.
 *
 * This is the OUTWARD-facing system — apps elsewhere using this store. The Chat
 * screen is the inward-facing one: AI used here, on a model this store pays for.
 *
 * @var string                                                          $endpoint_url
 * @var string                                                          $fallback_url
 * @var list<\Counterhand\Features\Settings\McpClient>               $connect_clients
 * @var array<string, string>                                           $connected_clients
 */

declare( strict_types=1 );

use Counterhand\Features\Settings\ClientGroup;

defined( 'ABSPATH' ) || exit;

$counterhand_groups = [];

foreach ( $connect_clients as $counterhand_client ) {
	$counterhand_groups[ $counterhand_client->group->value ][] = $counterhand_client;
}

// Enum order decides section order; groups with no clients simply drop out.
$counterhand_ordered_groups = array_values(
	array_filter(
		ClientGroup::cases(),
		static fn ( ClientGroup $group ): bool => ! empty( $counterhand_groups[ $group->value ] )
	)
);
?>
<div class="counterhand-connect">
	<div class="counterhand-card">
		<div class="counterhand-card__head">
			<span class="counterhand-card__heading">
				<h3 class="counterhand-card__title"><?php esc_html_e( 'Your store address', 'counterhand-mcp-for-woocommerce' ); ?></h3>
				<span class="counterhand-card__desc">
					<?php esc_html_e( 'Paste this one URL into any AI app. The app identifies itself with its own published address, so there is no token to create and nothing to copy back — you just approve what it may do.', 'counterhand-mcp-for-woocommerce' ); ?>
				</span>
			</span>
		</div>

		<div class="counterhand-card__body">
			<div class="counterhand-endpoint-row">
				<code class="counterhand-endpoint"><?php echo esc_html( $endpoint_url ); ?></code>
				<button type="button" class="button counterhand-copy" data-copy="<?php echo esc_attr( $endpoint_url ); ?>"
					data-copied-label="<?php esc_attr_e( 'Copied!', 'counterhand-mcp-for-woocommerce' ); ?>">
					<?php esc_html_e( 'Copy', 'counterhand-mcp-for-woocommerce' ); ?>
				</button>
			</div>

			<p class="counterhand-actions">
				<span class="counterhand-chip counterhand-chip--pending" id="counterhand-readiness">
					<span class="counterhand-chip__dot" aria-hidden="true"></span>
					<span class="counterhand-chip__text" role="status"><?php esc_html_e( 'Checking the store…', 'counterhand-mcp-for-woocommerce' ); ?></span>
				</span>
				<button type="button" class="button" id="counterhand-recheck"><?php esc_html_e( 'Check again', 'counterhand-mcp-for-woocommerce' ); ?></button>
			</p>

			<p class="counterhand-field__hint" id="counterhand-readiness-detail"></p>
		</div>
	</div>

	<?php foreach ( $counterhand_ordered_groups as $counterhand_group ) : ?>
		<h2 class="counterhand-group-title"><?php echo esc_html( $counterhand_group->label() ); ?></h2>
		<p class="counterhand-subtitle"><?php echo esc_html( $counterhand_group->hint() ); ?></p>

		<div class="counterhand-clients"
			<?php echo $counterhand_group->needs_public_store() ? 'data-needs-public="1"' : ''; ?>>
			<?php foreach ( $counterhand_groups[ $counterhand_group->value ] as $counterhand_client ) : ?>
				<details class="counterhand-card counterhand-card--collapsible" data-client="<?php echo esc_attr( $counterhand_client->id ); ?>">
					<summary class="counterhand-card__head">
						<span class="counterhand-card__heading">
							<h3 class="counterhand-card__title"><?php echo esc_html( $counterhand_client->name ); ?></h3>
							<span class="counterhand-card__desc"><?php echo esc_html( $counterhand_client->blurb ); ?></span>
						</span>

						<?php if ( isset( $connected_clients[ $counterhand_client->id ] ) ) : ?>
							<span class="counterhand-connected" title="<?php echo esc_attr( $connected_clients[ $counterhand_client->id ] ); ?>">
								<?php esc_html_e( '✓ Connected', 'counterhand-mcp-for-woocommerce' ); ?>
							</span>
						<?php endif; ?>
					</summary>

					<div class="counterhand-card__body">
						<?php if ( '' !== $counterhand_client->install_url || '' !== $counterhand_client->open_url ) : ?>
							<p class="counterhand-actions">
								<?php if ( '' !== $counterhand_client->install_url ) : ?>
									<a class="button button-primary" href="<?php echo esc_url( $counterhand_client->install_url, [ 'cursor', 'vscode', 'vscode-insiders' ] ); ?>">
										<?php echo esc_html( $counterhand_client->install_label ); ?>
									</a>
								<?php endif; ?>

								<?php if ( '' !== $counterhand_client->open_url ) : ?>
									<?php
									/*
									 * Copies the URL and opens the app's own connector page in the
									 * same click, so pasting is the very next keystroke rather than
									 * a trip back here.
									 */
									?>
									<a class="button button-primary counterhand-copy-open"
										href="<?php echo esc_url( $counterhand_client->open_url ); ?>"
										target="_blank" rel="noreferrer noopener"
										data-copy="<?php echo esc_attr( $endpoint_url ); ?>"
										data-client="<?php echo esc_attr( $counterhand_client->id ); ?>">
										<?php echo esc_html( $counterhand_client->open_label ); ?>
									</a>
								<?php endif; ?>
							</p>
						<?php endif; ?>

						<ol class="counterhand-steps">
							<?php foreach ( $counterhand_client->steps as $counterhand_step ) : ?>
								<li><?php echo esc_html( $counterhand_step ); ?></li>
							<?php endforeach; ?>
						</ol>

						<?php if ( '' !== $counterhand_client->snippet ) : ?>
							<div class="counterhand-snippet">
								<span class="counterhand-label"><?php echo esc_html( $counterhand_client->snippet_label ); ?></span>
								<pre><code><?php echo esc_html( $counterhand_client->snippet ); ?></code></pre>
								<button type="button" class="button counterhand-copy" data-copy="<?php echo esc_attr( $counterhand_client->snippet ); ?>"
									data-copied-label="<?php esc_attr_e( 'Copied!', 'counterhand-mcp-for-woocommerce' ); ?>">
									<?php esc_html_e( 'Copy', 'counterhand-mcp-for-woocommerce' ); ?>
								</button>
							</div>
						<?php endif; ?>

						<?php if ( '' !== $counterhand_client->docs_url ) : ?>
							<p class="counterhand-docs-link">
								<a href="<?php echo esc_url( $counterhand_client->docs_url ); ?>" target="_blank" rel="noreferrer noopener">
									<?php esc_html_e( 'Official setup documentation', 'counterhand-mcp-for-woocommerce' ); ?> ↗
								</a>
							</p>
						<?php endif; ?>
					</div>
				</details>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>

	<p class="counterhand-subtitle">
		<?php
		printf(
			/* translators: %s: fallback REST URL */
			esc_html__( 'If your host breaks pretty permalinks, the same endpoint also answers at %s.', 'counterhand-mcp-for-woocommerce' ),
			'<code>' . esc_html( $fallback_url ) . '</code>'
		);
		?>
		<?php esc_html_e( 'Approved apps appear on the Connections tab, where you can revoke any of them.', 'counterhand-mcp-for-woocommerce' ); ?>
	</p>

	<span class="screen-reader-text" role="status" id="counterhand-copy-status"></span>
</div>
