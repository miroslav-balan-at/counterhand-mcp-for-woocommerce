<?php
/**
 * Model chooser — shown in place of the conversation until a model is connected.
 *
 * Connecting a model used to live on the Settings tab, behind the connector
 * switch and the tool matrix. It belongs here: the chat is what needs a model,
 * so this is where you pick one.
 *
 * Plain form controls, no JavaScript: radios and a submit button work in every
 * browser and keep the keyboard and screen-reader behaviour the platform
 * already gives us.
 *
 * @var array<string, \AgentGateMcp\Features\Playground\Provider\ProviderInterface> $chat_providers
 * @var \AgentGateMcp\Features\Playground\ChatSettings                              $chat_settings
 * @var string                                                                      $active_id
 * @var \AgentGateMcp\Features\Playground\ConnectResult|null                       $save_result
 * @var \AgentGateMcp\Features\Playground\CoreAiState|null                          $core_state
 * @var list<\AgentGateMcp\Features\Playground\CoreConnector>                        $core_connectors
 */

declare( strict_types=1 );

use AgentGateMcp\Features\Playground\CoreAiState;
use AgentGateMcp\Features\Playground\ProviderPlugin;
use AgentGateMcp\Features\Playground\Provider\CoreAiClientProvider;
use AgentGateMcp\Features\Playground\Provider\OpenAiCompatibleProvider;

defined( 'ABSPATH' ) || exit;

$agmcp_selected = $chat_providers[ $active_id ] ?? null;
$agmcp_key_hint = $chat_settings->masked_key();
?>
<div class="agmcp-chooser">
	<h2 class="agmcp-chooser__title"><?php esc_html_e( 'Connect a model to start chatting', 'agentgate-mcp-for-woocommerce' ); ?></h2>
	<p class="agmcp-chooser__lede">
		<?php esc_html_e( 'One step, then this becomes your conversation.', 'agentgate-mcp-for-woocommerce' ); ?>
	</p>

	<?php if ( null !== $core_state ) : ?>
		<div class="agmcp-card">
			<div class="agmcp-card__head">
				<span class="agmcp-card__heading">
					<span class="agmcp-card__title"><?php esc_html_e( 'Let WordPress manage the model', 'agentgate-mcp-for-woocommerce' ); ?></span>
					<span class="agmcp-card__desc"><?php esc_html_e( 'The recommended way — fewer steps now, nothing to maintain later.', 'agentgate-mcp-for-woocommerce' ); ?></span>
				</span>
			</div>
			<div class="agmcp-card__body">
				<ul class="agmcp-benefits">
					<li><?php esc_html_e( 'One connection for the whole site — every AI plugin shares it instead of each asking for its own key.', 'agentgate-mcp-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'The key stays with WordPress. This plugin never sees or stores it.', 'agentgate-mcp-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'Rotate the key or switch provider in one place, without touching this plugin again.', 'agentgate-mcp-for-woocommerce' ); ?></li>
				</ul>

				<?php if ( CoreAiState::Ready === $core_state ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="agmcp_save_chat">
						<input type="hidden" name="agmcp_chat_provider" value="<?php echo esc_attr( CoreAiClientProvider::ID ); ?>">
						<input type="hidden" name="agmcp_chat_model" value="">
						<?php wp_nonce_field( 'agmcp_save_chat' ); ?>
						<div class="agmcp-actions">
							<button type="submit" class="button button-primary button-hero">
								<?php esc_html_e( 'Use WordPress AI', 'agentgate-mcp-for-woocommerce' ); ?>
							</button>
						</div>
					</form>
				<?php elseif ( CoreAiState::NeedsKey === $core_state ) : ?>
					<?php foreach ( $core_connectors as $agmcp_connector ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="agmcp-field">
							<input type="hidden" name="action" value="agmcp_save_connector_key">
							<input type="hidden" name="agmcp_connector_id" value="<?php echo esc_attr( $agmcp_connector->id ); ?>">
							<?php wp_nonce_field( 'agmcp_save_connector_key' ); ?>

							<label class="agmcp-label" for="agmcp-key-<?php echo esc_attr( $agmcp_connector->id ); ?>">
								<?php
								printf(
									/* translators: %s: provider name */
									esc_html__( '%s API key', 'agentgate-mcp-for-woocommerce' ),
									esc_html( $agmcp_connector->name )
								);
								?>
							</label>

							<div class="agmcp-endpoint-row">
								<input type="password" class="regular-text" autocomplete="off"
									id="agmcp-key-<?php echo esc_attr( $agmcp_connector->id ); ?>"
									name="agmcp_connector_key"
									placeholder="<?php echo esc_attr( $agmcp_connector->has_key ? __( 'A key is saved — paste a new one to replace it', 'agentgate-mcp-for-woocommerce' ) : __( 'Paste your API key', 'agentgate-mcp-for-woocommerce' ) ); ?>">
								<button type="submit" class="button button-primary"><?php esc_html_e( 'Save key', 'agentgate-mcp-for-woocommerce' ); ?></button>
							</div>

							<p class="agmcp-field__hint">
								<?php if ( $agmcp_connector->has_key && ! $agmcp_connector->is_connected ) : ?>
									<span class="agmcp-fail">
										<?php
										printf(
											/* translators: %s: provider name */
											esc_html__( '%s did not accept the stored key. Paste a different one.', 'agentgate-mcp-for-woocommerce' ),
											esc_html( $agmcp_connector->name )
										);
										?>
									</span>
									<br>
								<?php elseif ( $agmcp_connector->is_connected ) : ?>
									<span class="agmcp-ok"><?php esc_html_e( 'Key accepted.', 'agentgate-mcp-for-woocommerce' ); ?></span>
									<br>
								<?php endif; ?>
								<?php esc_html_e( 'WordPress stores it, not this plugin.', 'agentgate-mcp-for-woocommerce' ); ?>
								<?php if ( '' !== $agmcp_connector->credentials_url ) : ?>
									<a href="<?php echo esc_url( $agmcp_connector->credentials_url ); ?>" target="_blank" rel="noreferrer noopener">
										<?php esc_html_e( 'Get a key', 'agentgate-mcp-for-woocommerce' ); ?> ↗
									</a>
								<?php endif; ?>
							</p>
						</form>
					<?php endforeach; ?>
				<?php else : ?>
					<p><?php esc_html_e( 'Who do you have an account with? One click installs the official provider for it.', 'agentgate-mcp-for-woocommerce' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
						id="agmcp-install-form" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
						<input type="hidden" name="action" value="agmcp_install_provider">
						<?php wp_nonce_field( 'agmcp_install_provider' ); ?>
						<div class="agmcp-actions">
							<?php foreach ( ProviderPlugin::cases() as $agmcp_plugin ) : ?>
								<button type="submit" class="button button-primary" name="agmcp_provider_slug"
									value="<?php echo esc_attr( $agmcp_plugin->value ); ?>">
									<?php echo esc_html( $agmcp_plugin->label() ); ?>
								</button>
							<?php endforeach; ?>
						</div>
						<p class="agmcp-chooser__result agmcp-fail" id="agmcp-install-error" role="status"></p>
					</form>
				<?php endif; ?>
			</div>
		</div>

	<?php endif; ?>

	<?php if ( null === $core_state ) : ?>
		<p class="agmcp-field__hint">
			<?php esc_html_e( 'On WordPress 7.0 or newer, WordPress manages the model and the key for you — this screen offers it automatically after you update.', 'agentgate-mcp-for-woocommerce' ); ?>
		</p>
	<?php endif; ?>

	<details class="agmcp-card agmcp-card--collapsible" <?php echo null === $core_state ? 'open' : ''; ?>>
		<summary class="agmcp-card__head">
			<span class="agmcp-card__heading">
				<span class="agmcp-card__title"><?php esc_html_e( 'Use your own provider account', 'agentgate-mcp-for-woocommerce' ); ?></span>
				<span class="agmcp-card__desc"><?php esc_html_e( 'Paste an API key from Anthropic, OpenAI or Google — or run a local model with Ollama.', 'agentgate-mcp-for-woocommerce' ); ?></span>
			</span>
		</summary>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="agmcp_save_chat">
		<?php wp_nonce_field( 'agmcp_save_chat' ); ?>

		<div class="agmcp-card__body">
			<fieldset>
				<legend class="agmcp-label"><?php esc_html_e( 'Provider', 'agentgate-mcp-for-woocommerce' ); ?></legend>

				<div class="agmcp-chooser__grid">
					<?php
					foreach ( $chat_providers as $agmcp_provider ) :
						if ( $agmcp_provider instanceof CoreAiClientProvider ) {
							continue;
						}
						?>
						<label class="agmcp-provider">
							<span class="agmcp-provider__name">
								<input type="radio" name="agmcp_chat_provider"
									value="<?php echo esc_attr( $agmcp_provider->id() ); ?>"
									<?php checked( $active_id, $agmcp_provider->id() ); ?>>
								<?php echo esc_html( $agmcp_provider->label() ); ?>
							</span>
							<span class="agmcp-provider__meta">
								<?php
								echo esc_html(
									$agmcp_provider->needs_key()
										? __( 'Needs an API key', 'agentgate-mcp-for-woocommerce' )
										: __( 'No key needed', 'agentgate-mcp-for-woocommerce' )
								);
								?>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<div class="agmcp-field">
				<label class="agmcp-label" for="agmcp-chat-model"><?php esc_html_e( 'Model', 'agentgate-mcp-for-woocommerce' ); ?></label>
				<input type="text" id="agmcp-chat-model" name="agmcp_chat_model" class="regular-text"
					list="agmcp-chat-model-list"
					value="<?php echo esc_attr( $chat_settings->model() ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. claude-opus-5', 'agentgate-mcp-for-woocommerce' ); ?>">
				<datalist id="agmcp-chat-model-list">
					<?php
					foreach ( $chat_providers as $agmcp_provider ) {
						foreach ( $agmcp_provider->default_models() as $agmcp_model_id => $agmcp_model_label ) {
							printf(
								'<option value="%s">%s</option>',
								esc_attr( (string) $agmcp_model_id ),
								esc_attr( (string) $agmcp_model_label )
							);
						}
					}
					?>
				</datalist>
				<p class="agmcp-field__hint"><?php esc_html_e( 'Pick from the list or type any model your provider offers.', 'agentgate-mcp-for-woocommerce' ); ?></p>
			</div>

			<div class="agmcp-field">
				<label class="agmcp-label" for="agmcp-chat-key"><?php esc_html_e( 'API key', 'agentgate-mcp-for-woocommerce' ); ?></label>
				<?php
				$agmcp_key_placeholder = '' !== $agmcp_key_hint
					/* translators: %s: masked API key */
					? sprintf( __( 'Saved (%s) — leave blank to keep', 'agentgate-mcp-for-woocommerce' ), $agmcp_key_hint )
					: __( 'Paste your provider API key', 'agentgate-mcp-for-woocommerce' );
				?>
				<input type="password" id="agmcp-chat-key" name="agmcp_chat_key" class="regular-text"
					autocomplete="off" placeholder="<?php echo esc_attr( $agmcp_key_placeholder ); ?>">

				<p class="agmcp-field__hint">
					<?php esc_html_e( 'Stored in your database and sent only to the provider you chose. Ollama runs locally and needs no key.', 'agentgate-mcp-for-woocommerce' ); ?>
					<br>
					<?php esc_html_e( 'Get a key:', 'agentgate-mcp-for-woocommerce' ); ?>
					<?php
					$agmcp_links = [];
					foreach ( $chat_providers as $agmcp_provider ) {
						if ( '' === $agmcp_provider->key_url() || $agmcp_provider instanceof CoreAiClientProvider ) {
							continue;
						}

						$agmcp_links[] = sprintf(
							'<a href="%s" target="_blank" rel="noreferrer noopener">%s ↗</a>',
							esc_url( $agmcp_provider->key_url() ),
							esc_html( $agmcp_provider->label() )
						);
					}
					echo wp_kses_post( implode( ' · ', $agmcp_links ) );
					?>
				</p>

				<?php if ( '' !== $agmcp_key_hint ) : ?>
					<p class="agmcp-field__hint">
						<label>
							<input type="checkbox" name="agmcp_chat_forget" value="1">
							<?php esc_html_e( 'Remove the saved key', 'agentgate-mcp-for-woocommerce' ); ?>
						</label>
					</p>
				<?php endif; ?>
			</div>

			<details class="agmcp-card agmcp-card--collapsible"
				<?php echo ( null !== $agmcp_selected && $agmcp_selected->needs_base_url() ) ? 'open' : ''; ?>>
				<summary class="agmcp-card__head">
					<span class="agmcp-card__heading">
						<span class="agmcp-card__title"><?php esc_html_e( 'Advanced', 'agentgate-mcp-for-woocommerce' ); ?></span>
						<span class="agmcp-card__desc"><?php esc_html_e( 'Only for a custom OpenAI-compatible endpoint. The other providers fill this in for you.', 'agentgate-mcp-for-woocommerce' ); ?></span>
					</span>
				</summary>
				<div class="agmcp-card__body">
					<label class="agmcp-label" for="agmcp-chat-base-url"><?php esc_html_e( 'Base URL', 'agentgate-mcp-for-woocommerce' ); ?></label>
					<input type="url" id="agmcp-chat-base-url" name="agmcp_chat_base_url" class="regular-text"
						value="<?php echo esc_attr( $chat_settings->base_url() ); ?>"
						placeholder="<?php echo esc_attr( OpenAiCompatibleProvider::ollama()->default_base_url() ); ?>">
				</div>
			</details>
		</div>

		<div class="agmcp-card__foot">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Connect model', 'agentgate-mcp-for-woocommerce' ); ?>
			</button>
			<span class="agmcp-field__hint">
				<?php esc_html_e( 'We send one tiny test message before saving, so a wrong key is caught here rather than on your first question.', 'agentgate-mcp-for-woocommerce' ); ?>
			</span>
		</div>
	</form>
	</details>
</div>
