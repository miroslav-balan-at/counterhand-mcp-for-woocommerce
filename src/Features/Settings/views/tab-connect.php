<?php
/**
 * Connect AI apps: one URL, pasted into whichever app you already use.
 *
 * This is the OUTWARD-facing system — apps elsewhere using this store. The Chat
 * screen is the inward-facing one: AI used here, on a model this store pays for.
 *
 * @var string                                                          $endpoint_url
 * @var string                                                          $fallback_url
 * @var list<\AgentGateMcp\Features\Settings\McpClient>               $connect_clients
 * @var array<string, string>                                           $connected_clients
 */

declare( strict_types=1 );

use AgentGateMcp\Features\Settings\ClientGroup;

defined( 'ABSPATH' ) || exit;

$agmcp_groups = [];

foreach ( $connect_clients as $agmcp_client ) {
	$agmcp_groups[ $agmcp_client->group->value ][] = $agmcp_client;
}

// Enum order decides section order; groups with no clients simply drop out.
$agmcp_ordered_groups = array_values(
	array_filter(
		ClientGroup::cases(),
		static fn ( ClientGroup $group ): bool => ! empty( $agmcp_groups[ $group->value ] )
	)
);
?>
<div class="agmcp-connect">
	<div class="agmcp-card">
		<div class="agmcp-card__head">
			<span class="agmcp-card__heading">
				<span class="agmcp-card__title"><?php esc_html_e( 'Your store address', 'agentgate-mcp-for-woocommerce' ); ?></span>
				<span class="agmcp-card__desc">
					<?php esc_html_e( 'Paste this one URL into any AI app. The app identifies itself with its own published address, so there is no token to create and nothing to copy back — you just approve what it may do.', 'agentgate-mcp-for-woocommerce' ); ?>
				</span>
			</span>
		</div>

		<div class="agmcp-card__body">
			<div class="agmcp-endpoint-row">
				<code class="agmcp-endpoint"><?php echo esc_html( $endpoint_url ); ?></code>
				<button type="button" class="button agmcp-copy" data-copy="<?php echo esc_attr( $endpoint_url ); ?>"
					data-copied-label="<?php esc_attr_e( 'Copied!', 'agentgate-mcp-for-woocommerce' ); ?>">
					<?php esc_html_e( 'Copy', 'agentgate-mcp-for-woocommerce' ); ?>
				</button>
			</div>

			<p class="agmcp-actions">
				<span class="agmcp-chip agmcp-chip--pending" id="agmcp-readiness">
					<span class="agmcp-chip__dot" aria-hidden="true"></span>
					<span class="agmcp-chip__text" role="status"><?php esc_html_e( 'Checking the store…', 'agentgate-mcp-for-woocommerce' ); ?></span>
				</span>
				<button type="button" class="button" id="agmcp-recheck"><?php esc_html_e( 'Check again', 'agentgate-mcp-for-woocommerce' ); ?></button>
			</p>

			<p class="agmcp-field__hint" id="agmcp-readiness-detail"></p>
		</div>
	</div>

	<?php foreach ( $agmcp_ordered_groups as $agmcp_group ) : ?>
		<p class="agmcp-group-title"><?php echo esc_html( $agmcp_group->label() ); ?></p>
		<p class="agmcp-subtitle"><?php echo esc_html( $agmcp_group->hint() ); ?></p>

		<div class="agmcp-clients"
			<?php echo $agmcp_group->needs_public_store() ? 'data-needs-public="1"' : ''; ?>>
			<?php foreach ( $agmcp_groups[ $agmcp_group->value ] as $agmcp_client ) : ?>
				<details class="agmcp-card agmcp-card--collapsible" data-client="<?php echo esc_attr( $agmcp_client->id ); ?>">
					<summary class="agmcp-card__head">
						<span class="agmcp-card__heading">
							<span class="agmcp-card__title"><?php echo esc_html( $agmcp_client->name ); ?></span>
							<span class="agmcp-card__desc"><?php echo esc_html( $agmcp_client->blurb ); ?></span>
						</span>

						<?php if ( isset( $connected_clients[ $agmcp_client->id ] ) ) : ?>
							<span class="agmcp-connected" title="<?php echo esc_attr( $connected_clients[ $agmcp_client->id ] ); ?>">
								<?php esc_html_e( '✓ Connected', 'agentgate-mcp-for-woocommerce' ); ?>
							</span>
						<?php endif; ?>
					</summary>

					<div class="agmcp-card__body">
						<?php if ( '' !== $agmcp_client->install_url || '' !== $agmcp_client->open_url ) : ?>
							<p class="agmcp-actions">
								<?php if ( '' !== $agmcp_client->install_url ) : ?>
									<a class="button button-primary" href="<?php echo esc_url( $agmcp_client->install_url, [ 'cursor', 'vscode', 'vscode-insiders' ] ); ?>">
										<?php echo esc_html( $agmcp_client->install_label ); ?>
									</a>
								<?php endif; ?>

								<?php if ( '' !== $agmcp_client->open_url ) : ?>
									<?php
									/*
									 * Copies the URL and opens the app's own connector page in the
									 * same click, so pasting is the very next keystroke rather than
									 * a trip back here.
									 */
									?>
									<a class="button button-primary agmcp-copy-open"
										href="<?php echo esc_url( $agmcp_client->open_url ); ?>"
										target="_blank" rel="noreferrer noopener"
										data-copy="<?php echo esc_attr( $endpoint_url ); ?>"
										data-client="<?php echo esc_attr( $agmcp_client->id ); ?>">
										<?php echo esc_html( $agmcp_client->open_label ); ?>
									</a>
								<?php endif; ?>
							</p>
						<?php endif; ?>

						<ol class="agmcp-steps">
							<?php foreach ( $agmcp_client->steps as $agmcp_step ) : ?>
								<li><?php echo esc_html( $agmcp_step ); ?></li>
							<?php endforeach; ?>
						</ol>

						<?php if ( '' !== $agmcp_client->snippet ) : ?>
							<div class="agmcp-snippet">
								<span class="agmcp-label"><?php echo esc_html( $agmcp_client->snippet_label ); ?></span>
								<pre><code><?php echo esc_html( $agmcp_client->snippet ); ?></code></pre>
								<button type="button" class="button agmcp-copy" data-copy="<?php echo esc_attr( $agmcp_client->snippet ); ?>"
									data-copied-label="<?php esc_attr_e( 'Copied!', 'agentgate-mcp-for-woocommerce' ); ?>">
									<?php esc_html_e( 'Copy', 'agentgate-mcp-for-woocommerce' ); ?>
								</button>
							</div>
						<?php endif; ?>

						<?php if ( '' !== $agmcp_client->docs_url ) : ?>
							<p class="agmcp-docs-link">
								<a href="<?php echo esc_url( $agmcp_client->docs_url ); ?>" target="_blank" rel="noreferrer noopener">
									<?php esc_html_e( 'Official setup documentation', 'agentgate-mcp-for-woocommerce' ); ?> ↗
								</a>
							</p>
						<?php endif; ?>
					</div>
				</details>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>

	<p class="agmcp-subtitle">
		<?php
		printf(
			/* translators: %s: fallback REST URL */
			esc_html__( 'If your host breaks pretty permalinks, the same endpoint also answers at %s.', 'agentgate-mcp-for-woocommerce' ),
			'<code>' . esc_html( $fallback_url ) . '</code>'
		);
		?>
		<?php esc_html_e( 'Approved apps appear on the Connections tab, where you can revoke any of them.', 'agentgate-mcp-for-woocommerce' ); ?>
	</p>
</div>
