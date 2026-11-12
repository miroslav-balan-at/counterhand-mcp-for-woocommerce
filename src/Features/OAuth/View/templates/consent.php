<?php
/**
 * Consent state: the store admin approves (and may narrow) requested scopes.
 *
 * @var array $context {
 *     @type string                                              $client_name
 *     @type string                                              $client_host
 *     @type \Counterhand\Features\OAuth\View\ConsentScopes      $scopes
 *     @type string                                              $settings_url
 *     @type array<string, string>                               $hidden
 * }
 */

declare( strict_types=1 );

use Counterhand\Features\Settings\AdminScreen;

defined( 'ABSPATH' ) || exit;

$counterhand_scopes         = $context['scopes'];
$counterhand_settings_url   = $context['settings_url'];
$counterhand_has_write      = $counterhand_scopes->has_write();
$counterhand_client_name    = $context['client_name'];
$counterhand_offers_nothing = ! $counterhand_scopes->has_grantable();
?>
<div class="counterhand-card__body">
	<p class="counterhand-eyebrow"><?php esc_html_e( 'An app asks to connect', 'counterhand-mcp-for-woocommerce' ); ?></p>
	<h1 class="counterhand-title">
		<?php
		printf(
			/* translators: %s: name the app gives itself, e.g. "Claude". */
			esc_html__( 'Let %s work with your store?', 'counterhand-mcp-for-woocommerce' ),
			esc_html( $counterhand_client_name )
		);
		?>
	</h1>
	<p class="counterhand-lede"><?php esc_html_e( 'It can do what you tick below, and nothing else. You can take its access away at any time.', 'counterhand-mcp-for-woocommerce' ); ?></p>

	<?php
	// The name is the app's own claim; only the address it was fetched from is checked.
	$counterhand_admin_name = wp_get_current_user()->display_name;
	?>
	<dl class="counterhand-request">
		<div class="counterhand-request__row">
			<dt><?php esc_html_e( 'Approving as', 'counterhand-mcp-for-woocommerce' ); ?></dt>
			<dd>
				<span class="counterhand-identity__avatar" aria-hidden="true"><?php echo esc_html( mb_substr( $counterhand_admin_name, 0, 1 ) ); ?></span>
				<span class="counterhand-identity__name"><?php echo esc_html( $counterhand_admin_name ); ?></span>
			</dd>
		</div>
		<div class="counterhand-request__row">
			<dt><?php esc_html_e( 'App', 'counterhand-mcp-for-woocommerce' ); ?></dt>
			<dd>
				<span class="counterhand-identity__name"><?php echo esc_html( $counterhand_client_name ); ?></span>
				<code><?php echo esc_html( $context['client_host'] ); ?></code>
				<span class="counterhand-tag counterhand-tag--verified"><?php esc_html_e( 'Address verified', 'counterhand-mcp-for-woocommerce' ); ?></span>
			</dd>
		</div>
		<div class="counterhand-request__row">
			<dt><?php esc_html_e( 'Access goes to', 'counterhand-mcp-for-woocommerce' ); ?></dt>
			<dd><code><?php echo esc_html( $context['return_host'] ); ?></code></dd>
		</div>
	</dl>

	<?php if ( $context['returns_to_this_computer'] ) : ?>
		<p class="counterhand-notice counterhand-notice--warning"><strong><?php esc_html_e( "This hands access to a program on your own computer. Approve only if you just started this connection yourself: any program there could ask under this app's name.", 'counterhand-mcp-for-woocommerce' ); ?></strong></p>
	<?php endif; ?>

	<form method="post" class="counterhand-form">
		<?php
		wp_nonce_field( 'counterhand_authorize' );
		foreach ( $context['hidden'] as $counterhand_key => $counterhand_value ) {
			printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $counterhand_key ), esc_attr( $counterhand_value ) );
		}
		?>

		<?php if ( $counterhand_offers_nothing ) : ?>
			<p class="counterhand-notice counterhand-notice--warning">
				<?php esc_html_e( 'Nothing this app asked for is currently switched on for this store, so there is nothing to approve yet.', 'counterhand-mcp-for-woocommerce' ); ?>
			</p>
		<?php endif; ?>

		<fieldset class="counterhand-scopes">
			<legend class="counterhand-scopes__legend"><?php esc_html_e( 'Choose what it may do', 'counterhand-mcp-for-woocommerce' ); ?></legend>

			<?php foreach ( $counterhand_scopes->sections as $counterhand_section ) : ?>
				<details class="counterhand-scopes__section<?php echo $counterhand_section->is_collapsed() ? ' counterhand-scopes__section--advanced' : ''; ?>" <?php echo $counterhand_section->starts_open() ? 'open' : ''; ?>>
					<summary class="counterhand-scopes__heading">
						<span class="counterhand-scopes__heading-text">
							<?php echo esc_html( $counterhand_section->section->label() ); ?>
							<span class="counterhand-scopes__heading-desc"><?php echo esc_html( $counterhand_section->section->description() ); ?></span>
						</span>
						<span class="counterhand-scopes__count">
							<?php
							echo esc_html(
								0 === $counterhand_section->grantable_count()
									? __( 'Off for this store', 'counterhand-mcp-for-woocommerce' )
									: sprintf(
										/* translators: 1: how many permissions in this section can be granted, 2: how many it has. */
										__( '%1$s of %2$s can be granted', 'counterhand-mcp-for-woocommerce' ),
										number_format_i18n( $counterhand_section->grantable_count() ),
										number_format_i18n( $counterhand_section->row_count() )
									)
							);
							?>
						</span>
					</summary>
					<div class="counterhand-scopes__body">
						<?php require __DIR__ . '/partial-consent-rows.php'; ?>
					</div>
				</details>
			<?php endforeach; ?>
		</fieldset>

		<p class="counterhand-hint"><?php esc_html_e( 'Uncheck anything you would rather not grant. You can revoke the whole connection later.', 'counterhand-mcp-for-woocommerce' ); ?></p>

		<?php if ( $counterhand_scopes->has_withheld() ) : ?>
			<p class="counterhand-hint">
				<?php
				printf(
					/* translators: 1: link to the Access screen, named as in the menu, e.g. "Counterhand MCP › Access". */
					esc_html__( 'Grayed-out areas are switched off for this store. Switch them on under %1$s, then connect the app again — a connection only ever holds what it was approved with.', 'counterhand-mcp-for-woocommerce' ),
					'<a href="' . esc_url( $counterhand_settings_url ) . '" target="_blank" rel="noopener">' . esc_html( __( 'Counterhand MCP', 'counterhand-mcp-for-woocommerce' ) . ' › ' . AdminScreen::Access->menu_title() ) . '</a>'
				);
				?>
			</p>
		<?php endif; ?>

			<?php if ( $counterhand_has_write ) : ?>
			<p class="counterhand-notice counterhand-notice--warning">
				<?php esc_html_e( 'This request includes permission to change store data. New products are always created as drafts for you to review.', 'counterhand-mcp-for-woocommerce' ); ?>
			</p>
		<?php endif; ?>

		<?php
		// Approve first in the DOM so keyboard order matches the stacked mobile
		// layout; CSS puts Deny on the left on wider screens. Reversing this in
		// markup instead would make a keyboard user tab upwards on a phone, on
		// the screen that grants an app access to the store.
		?>
		<div class="counterhand-actions">
			<?php if ( ! $counterhand_offers_nothing ) : ?>
				<button type="submit" name="counterhand_approve" value="1" class="counterhand-button counterhand-button--primary">
					<?php esc_html_e( 'Approve access', 'counterhand-mcp-for-woocommerce' ); ?>
				</button>
			<?php endif; ?>
			<button type="submit" name="counterhand_deny" value="1" class="counterhand-button counterhand-button--secondary">
				<?php echo esc_html( $counterhand_offers_nothing ? __( 'Return to the app', 'counterhand-mcp-for-woocommerce' ) : __( 'Deny', 'counterhand-mcp-for-woocommerce' ) ); ?>
			</button>
		</div>
	</form>
</div>

<footer class="counterhand-card__foot">
	<?php
	printf(
		/* translators: %s: where connected apps are listed in the menu, e.g. "Counterhand MCP › Connect apps › Connected apps". */
		esc_html__( 'You can take this app\'s access away any time under %s.', 'counterhand-mcp-for-woocommerce' ),
		esc_html( __( 'Counterhand MCP', 'counterhand-mcp-for-woocommerce' ) . ' › ' . AdminScreen::Connect->menu_title() . ' › ' . __( 'Connected apps', 'counterhand-mcp-for-woocommerce' ) )
	);
	?>
</footer>
