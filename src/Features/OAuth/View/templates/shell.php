<?php
/**
 * Shell for every OAuth flow page.
 *
 * @var string      $page_title     Document + heading title.
 * @var string      $store_name     Site name.
 * @var string|null $store_logo     Store mark URL, or null for a lettermark.
 * @var string      $store_host     Host shown next to the padlock.
 * @var string      $body_template  Absolute path to the state partial.
 * @var array       $context        Variables for the state partial.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$agmcp_css_url = plugins_url( 'assets/oauth/flow.css', AGMCP_PLUGIN_FILE )
	. '?v=' . rawurlencode( (string) filemtime( AGMCP_PLUGIN_DIR . '/assets/oauth/flow.css' ) );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title><?php echo esc_html( $page_title . ' — ' . $store_name ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( $agmcp_css_url ); ?>">
</head>
<body class="agmcp-flow">
	<header class="agmcp-siteheader">
		<?php if ( null !== $store_logo ) : ?>
			<img class="agmcp-siteheader__logo" src="<?php echo esc_url( $store_logo ); ?>"
				alt="<?php echo esc_attr( $store_name ); ?>" width="48" height="48">
		<?php else : ?>
			<span class="agmcp-siteheader__mark" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( $store_name, 0, 1 ) ) ); ?></span>
		<?php endif; ?>

		<span class="agmcp-siteheader__text">
			<span class="agmcp-siteheader__name"><?php echo esc_html( $store_name ); ?></span>
			<span class="agmcp-siteheader__host">
				<svg class="agmcp-lock" viewBox="0 0 24 24" width="12" height="12" aria-hidden="true" focusable="false">
					<path d="M7 10V7a5 5 0 0110 0v3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
					<rect x="4.5" y="10" width="15" height="10" rx="2" fill="currentColor"/>
				</svg>
				<?php echo esc_html( $store_host ); ?>
			</span>
		</span>
	</header>

	<main class="agmcp-card" role="main">
		<?php
		// The state partial renders the card body.
		require $body_template;
		?>
	</main>

	<footer class="agmcp-sitefooter">
		<a class="agmcp-sitefooter__link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php
			printf(
				/* translators: %s: store name */
				esc_html__( 'Back to %s', 'agentgate-mcp-for-woocommerce' ),
				esc_html( $store_name )
			);
			?>
		</a>
		<span class="agmcp-sitefooter__sep" aria-hidden="true">·</span>
		<span class="agmcp-sitefooter__note"><?php esc_html_e( 'Secured by AgentGate MCP', 'agentgate-mcp-for-woocommerce' ); ?></span>
	</footer>
</body>
</html>