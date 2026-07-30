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

$ctrh_groups = [];

foreach ( $connect_clients as $ctrh_client ) {
	$ctrh_groups[ $ctrh_client->group->value ][] = $ctrh_client;
}

// Enum order decides section order; groups with no clients simply drop out.
$ctrh_ordered_groups = array_values(
	array_filter(
		ClientGroup::cases(),
		static fn ( ClientGroup $group ): bool => ! empty( $ctrh_groups[ $group->value ] )
	)
);
?>
<div class="ctrh-connect">
	<div class="ctrh-card">
		<div class="ctrh-card__head">
			<span class="ctrh-card__heading">
				<span class="ctrh-card__title"><?php esc_html_e( 'Your store address', 'counterhand-mcp-for-woocommerce' ); ?></span>
				<span class="ctrh-card__desc">
					<?php esc_html_e( 'Paste this one URL into any AI app. The app identifies itself with its own published address, so there is no token to create and nothing to copy back — you just approve what it may do.', 'counterhand-mcp-for-woocommerce' ); ?>
				</span>
			</span>
		</div>

		<div class="ctrh-card__body">
			<div class="ctrh-endpoint-row">
				<code class="ctrh-endpoint"><?php echo esc_html( $endpoint_url ); ?></code>
				<button type="button" class="button ctrh-copy" data-copy="<?php echo esc_attr( $endpoint_url ); ?>"
					data-copied-label="<?php esc_attr_e( 'Copied!', 'counterhand-mcp-for-woocommerce' ); ?>">
					<?php esc_html_e( 'Copy', 'counterhand-mcp-for-woocommerce' ); ?>
				</button>
			</div>

			<p class="ctrh-actions">
				<span class="ctrh-chip ctrh-chip--pending" id="ctrh-readiness">
					<span class="ctrh-chip__dot" aria-hidden="true"></span>
					<span class="ctrh-chip__text" role="status"><?php esc_html_e( 'Checking the store…', 'counterhand-mcp-for-woocommerce' ); ?></span>
				</span>
				<button type="button" class="button" id="ctrh-recheck"><?php esc_html_e( 'Check again', 'counterhand-mcp-for-woocommerce' ); ?></button>
			</p>

			<p class="ctrh-field__hint" id="ctrh-readiness-detail"></p>
		</div>
	</div>

	<?php foreach ( $ctrh_ordered_groups as $ctrh_group ) : ?>
		<p class="ctrh-group-title"><?php echo esc_html( $ctrh_group->label() ); ?></p>
		<p class="ctrh-subtitle"><?php echo esc_html( $ctrh_group->hint() ); ?></p>

		<div class="ctrh-clients"
			<?php echo $ctrh_group->needs_public_store() ? 'data-needs-public="1"' : ''; ?>>
			<?php foreach ( $ctrh_groups[ $ctrh_group->value ] as $ctrh_client ) : ?>
				<details class="ctrh-card ctrh-card--collapsible" data-client="<?php echo esc_attr( $ctrh_client->id ); ?>">
					<summary class="ctrh-card__head">
						<span class="ctrh-card__heading">
							<span class="ctrh-card__title"><?php echo esc_html( $ctrh_client->name ); ?></span>
							<span class="ctrh-card__desc"><?php echo esc_html( $ctrh_client->blurb ); ?></span>
						</span>

						<?php if ( isset( $connected_clients[ $ctrh_client->id ] ) ) : ?>
							<span class="ctrh-connected" title="<?php echo esc_attr( $connected_clients[ $ctrh_client->id ] ); ?>">
								<?php esc_html_e( '✓ Connected', 'counterhand-mcp-for-woocommerce' ); ?>
							</span>
						<?php endif; ?>
					</summary>

					<div class="ctrh-card__body">
						<?php if ( '' !== $ctrh_client->install_url || '' !== $ctrh_client->open_url ) : ?>
							<p class="ctrh-actions">
								<?php if ( '' !== $ctrh_client->install_url ) : ?>
									<a class="button button-primary" href="<?php echo esc_url( $ctrh_client->install_url, [ 'cursor', 'vscode', 'vscode-insiders' ] ); ?>">
										<?php echo esc_html( $ctrh_client->install_label ); ?>
									</a>
								<?php endif; ?>

								<?php if ( '' !== $ctrh_client->open_url ) : ?>
									<?php
									/*
									 * Copies the URL and opens the app's own connector page in the
									 * same click, so pasting is the very next keystroke rather than
									 * a trip back here.
									 */
									?>
									<a class="button button-primary ctrh-copy-open"
										href="<?php echo esc_url( $ctrh_client->open_url ); ?>"
										target="_blank" rel="noreferrer noopener"
										data-copy="<?php echo esc_attr( $endpoint_url ); ?>"
										data-client="<?php echo esc_attr( $ctrh_client->id ); ?>">
										<?php echo esc_html( $ctrh_client->open_label ); ?>
									</a>
								<?php endif; ?>
							</p>
						<?php endif; ?>

						<ol class="ctrh-steps">
							<?php foreach ( $ctrh_client->steps as $ctrh_step ) : ?>
								<li><?php echo esc_html( $ctrh_step ); ?></li>
							<?php endforeach; ?>
						</ol>

						<?php if ( '' !== $ctrh_client->snippet ) : ?>
							<div class="ctrh-snippet">
								<span class="ctrh-label"><?php echo esc_html( $ctrh_client->snippet_label ); ?></span>
								<pre><code><?php echo esc_html( $ctrh_client->snippet ); ?></code></pre>
								<button type="button" class="button ctrh-copy" data-copy="<?php echo esc_attr( $ctrh_client->snippet ); ?>"
									data-copied-label="<?php esc_attr_e( 'Copied!', 'counterhand-mcp-for-woocommerce' ); ?>">
									<?php esc_html_e( 'Copy', 'counterhand-mcp-for-woocommerce' ); ?>
								</button>
							</div>
						<?php endif; ?>

						<?php if ( '' !== $ctrh_client->docs_url ) : ?>
							<p class="ctrh-docs-link">
								<a href="<?php echo esc_url( $ctrh_client->docs_url ); ?>" target="_blank" rel="noreferrer noopener">
									<?php esc_html_e( 'Official setup documentation', 'counterhand-mcp-for-woocommerce' ); ?> ↗
								</a>
							</p>
						<?php endif; ?>
					</div>
				</details>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>

	<p class="ctrh-subtitle">
		<?php
		printf(
			/* translators: %s: fallback REST URL */
			esc_html__( 'If your host breaks pretty permalinks, the same endpoint also answers at %s.', 'counterhand-mcp-for-woocommerce' ),
			'<code>' . esc_html( $fallback_url ) . '</code>'
		);
		?>
		<?php esc_html_e( 'Approved apps appear on the Connections tab, where you can revoke any of them.', 'counterhand-mcp-for-woocommerce' ); ?>
	</p>
</div>
