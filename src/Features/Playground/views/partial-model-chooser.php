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
 * @var array<string, \Counterhand\Features\Playground\Provider\ProviderInterface> $chat_own_providers Providers the admin connects with their own account.
 * @var \Counterhand\Features\Playground\ChatSettings                              $chat_settings
 * @var string                                                                      $active_id
 * @var \Counterhand\Features\Playground\ConnectResult|null                       $save_result
 * @var \Counterhand\Features\Playground\CoreAiState|null                          $core_state
 * @var list<\Counterhand\Features\Playground\CoreConnector>                        $core_connectors
 */

declare( strict_types=1 );

use Counterhand\Features\Playground\CoreAiState;
use Counterhand\Features\Playground\ProviderPlugin;
use Counterhand\Features\Playground\Provider\CoreAiClientProvider;
use Counterhand\Features\Playground\Provider\OpenAiCompatibleProvider;

defined( 'ABSPATH' ) || exit;

$ctrh_selected = $chat_own_providers[ $active_id ] ?? null;
$ctrh_key_hint = $chat_settings->masked_key();
?>
<div class="ctrh-chooser">
	<h2 class="ctrh-chooser__title"><?php esc_html_e( 'Connect a model to start chatting', 'counterhand-mcp-for-woocommerce' ); ?></h2>
	<p class="ctrh-chooser__lede">
		<?php esc_html_e( 'One step, then this becomes your conversation.', 'counterhand-mcp-for-woocommerce' ); ?>
	</p>

	<?php if ( null !== $core_state ) : ?>
		<div class="ctrh-card">
			<div class="ctrh-card__head">
				<span class="ctrh-card__heading">
					<h3 class="ctrh-card__title"><?php esc_html_e( 'Let WordPress manage the model', 'counterhand-mcp-for-woocommerce' ); ?></h3>
					<span class="ctrh-card__desc"><?php esc_html_e( 'The recommended way — fewer steps now, nothing to maintain later.', 'counterhand-mcp-for-woocommerce' ); ?></span>
				</span>
			</div>
			<div class="ctrh-card__body">
				<ul class="ctrh-benefits">
					<li><?php esc_html_e( 'One connection for the whole site — every AI plugin shares it instead of each asking for its own key.', 'counterhand-mcp-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'The key stays with WordPress. This plugin never sees or stores it.', 'counterhand-mcp-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'Rotate the key or switch provider in one place, without touching this plugin again.', 'counterhand-mcp-for-woocommerce' ); ?></li>
				</ul>

				<?php if ( CoreAiState::Ready === $core_state ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="ctrh_save_chat">
						<input type="hidden" name="ctrh_chat_provider" value="<?php echo esc_attr( CoreAiClientProvider::ID ); ?>">
						<input type="hidden" name="ctrh_chat_model" value="">
						<?php wp_nonce_field( 'ctrh_save_chat' ); ?>
						<div class="ctrh-actions">
							<button type="submit" class="button button-primary button-hero">
								<?php esc_html_e( 'Use WordPress AI', 'counterhand-mcp-for-woocommerce' ); ?>
							</button>
						</div>
					</form>
				<?php elseif ( CoreAiState::NeedsKey === $core_state ) : ?>
					<?php foreach ( $core_connectors as $ctrh_connector ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ctrh-field">
							<input type="hidden" name="action" value="ctrh_save_connector_key">
							<input type="hidden" name="ctrh_connector_id" value="<?php echo esc_attr( $ctrh_connector->id ); ?>">
							<?php wp_nonce_field( 'ctrh_save_connector_key' ); ?>

							<label class="ctrh-label" for="ctrh-key-<?php echo esc_attr( $ctrh_connector->id ); ?>">
								<?php
								printf(
									/* translators: %s: provider name */
									esc_html__( '%s API key', 'counterhand-mcp-for-woocommerce' ),
									esc_html( $ctrh_connector->name )
								);
								?>
							</label>

							<div class="ctrh-endpoint-row">
								<input type="password" class="regular-text" autocomplete="off"
									id="ctrh-key-<?php echo esc_attr( $ctrh_connector->id ); ?>"
									name="ctrh_connector_key"
									placeholder="<?php echo esc_attr( $ctrh_connector->has_key ? __( 'A key is saved — paste a new one to replace it', 'counterhand-mcp-for-woocommerce' ) : __( 'Paste your API key', 'counterhand-mcp-for-woocommerce' ) ); ?>">
								<button type="submit" class="button button-primary"><?php esc_html_e( 'Save key', 'counterhand-mcp-for-woocommerce' ); ?></button>
							</div>

							<p class="ctrh-field__hint">
								<?php if ( $ctrh_connector->has_key && ! $ctrh_connector->is_connected ) : ?>
									<span class="ctrh-fail">
										<?php
										printf(
											/* translators: %s: provider name */
											esc_html__( '%s did not accept the stored key. Paste a different one.', 'counterhand-mcp-for-woocommerce' ),
											esc_html( $ctrh_connector->name )
										);
										?>
									</span>
									<br>
								<?php elseif ( $ctrh_connector->is_connected ) : ?>
									<span class="ctrh-ok"><?php esc_html_e( 'Key accepted.', 'counterhand-mcp-for-woocommerce' ); ?></span>
									<br>
								<?php endif; ?>
								<?php esc_html_e( 'WordPress stores it, not this plugin.', 'counterhand-mcp-for-woocommerce' ); ?>
								<?php if ( '' !== $ctrh_connector->credentials_url ) : ?>
									<a href="<?php echo esc_url( $ctrh_connector->credentials_url ); ?>" target="_blank" rel="noreferrer noopener">
										<?php esc_html_e( 'Get a key', 'counterhand-mcp-for-woocommerce' ); ?> ↗
									</a>
								<?php endif; ?>
							</p>
						</form>
					<?php endforeach; ?>
				<?php else : ?>
					<p><?php esc_html_e( 'Who do you have an account with? One click installs the official provider for it.', 'counterhand-mcp-for-woocommerce' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
						id="ctrh-install-form" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
						<input type="hidden" name="action" value="ctrh_install_provider">
						<?php wp_nonce_field( 'ctrh_install_provider' ); ?>
						<div class="ctrh-actions">
							<?php foreach ( ProviderPlugin::cases() as $ctrh_plugin ) : ?>
								<button type="submit" class="button button-primary" name="ctrh_provider_slug"
									value="<?php echo esc_attr( $ctrh_plugin->value ); ?>">
									<?php echo esc_html( $ctrh_plugin->label() ); ?>
								</button>
							<?php endforeach; ?>
						</div>
						<p class="ctrh-chooser__result ctrh-fail" id="ctrh-install-error" role="status"></p>
					</form>
				<?php endif; ?>
			</div>
		</div>

	<?php endif; ?>

	<?php if ( null === $core_state ) : ?>
		<p class="ctrh-field__hint">
			<?php esc_html_e( 'On WordPress 7.0 or newer, WordPress manages the model and the key for you — this screen offers it automatically after you update.', 'counterhand-mcp-for-woocommerce' ); ?>
		</p>
	<?php endif; ?>

	<details class="ctrh-card ctrh-card--collapsible" <?php echo null === $core_state ? 'open' : ''; ?>>
		<summary class="ctrh-card__head">
			<span class="ctrh-card__heading">
				<h3 class="ctrh-card__title"><?php esc_html_e( 'Use your own provider account', 'counterhand-mcp-for-woocommerce' ); ?></h3>
				<span class="ctrh-card__desc"><?php esc_html_e( 'Paste an API key from Anthropic, OpenAI or Google — or run a local model with Ollama.', 'counterhand-mcp-for-woocommerce' ); ?></span>
			</span>
		</summary>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ctrh_save_chat">
		<?php wp_nonce_field( 'ctrh_save_chat' ); ?>

		<div class="ctrh-card__body">
			<fieldset>
				<legend class="ctrh-label"><?php esc_html_e( 'Provider', 'counterhand-mcp-for-woocommerce' ); ?></legend>

				<div class="ctrh-chooser__grid">
					<?php foreach ( $chat_own_providers as $ctrh_provider ) : ?>
						<label class="ctrh-provider">
							<span class="ctrh-provider__name">
								<input type="radio" name="ctrh_chat_provider"
									value="<?php echo esc_attr( $ctrh_provider->id() ); ?>"
									<?php checked( $active_id, $ctrh_provider->id() ); ?>>
								<?php echo esc_html( $ctrh_provider->label() ); ?>
							</span>
							<span class="ctrh-provider__meta">
								<?php
								echo esc_html(
									$ctrh_provider->needs_key()
										? __( 'Needs an API key', 'counterhand-mcp-for-woocommerce' )
										: __( 'No key needed', 'counterhand-mcp-for-woocommerce' )
								);
								?>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<div class="ctrh-field">
				<label class="ctrh-label" for="ctrh-chat-model"><?php esc_html_e( 'Model', 'counterhand-mcp-for-woocommerce' ); ?></label>
				<input type="text" id="ctrh-chat-model" name="ctrh_chat_model" class="regular-text"
					list="ctrh-chat-model-list"
					value="<?php echo esc_attr( $chat_settings->model() ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. claude-opus-5', 'counterhand-mcp-for-woocommerce' ); ?>">
				<datalist id="ctrh-chat-model-list">
					<?php
					foreach ( $chat_own_providers as $ctrh_provider ) {
						foreach ( $ctrh_provider->default_models() as $ctrh_model_id => $ctrh_model_label ) {
							printf(
								'<option value="%s">%s</option>',
								esc_attr( (string) $ctrh_model_id ),
								esc_attr( (string) $ctrh_model_label )
							);
						}
					}
					?>
				</datalist>
				<p class="ctrh-field__hint"><?php esc_html_e( 'Pick from the list or type any model your provider offers.', 'counterhand-mcp-for-woocommerce' ); ?></p>
			</div>

			<div class="ctrh-field">
				<label class="ctrh-label" for="ctrh-chat-key"><?php esc_html_e( 'API key', 'counterhand-mcp-for-woocommerce' ); ?></label>
				<?php
				$ctrh_key_placeholder = '' !== $ctrh_key_hint
					/* translators: %s: masked API key */
					? sprintf( __( 'Saved (%s) — leave blank to keep', 'counterhand-mcp-for-woocommerce' ), $ctrh_key_hint )
					: __( 'Paste your provider API key', 'counterhand-mcp-for-woocommerce' );
				?>
				<input type="password" id="ctrh-chat-key" name="ctrh_chat_key" class="regular-text"
					autocomplete="off" placeholder="<?php echo esc_attr( $ctrh_key_placeholder ); ?>">

				<p class="ctrh-field__hint">
					<?php esc_html_e( 'Stored in your database and sent only to the provider you chose. Ollama runs locally and needs no key.', 'counterhand-mcp-for-woocommerce' ); ?>
					<br>
					<?php esc_html_e( 'Get a key:', 'counterhand-mcp-for-woocommerce' ); ?>
					<?php
					$ctrh_links = [];
					foreach ( $chat_own_providers as $ctrh_provider ) {
						if ( '' === $ctrh_provider->key_url() ) {
							continue;
						}

						$ctrh_links[] = sprintf(
							'<a href="%s" target="_blank" rel="noreferrer noopener">%s ↗</a>',
							esc_url( $ctrh_provider->key_url() ),
							esc_html( $ctrh_provider->label() )
						);
					}
					echo wp_kses_post( implode( ' · ', $ctrh_links ) );
					?>
				</p>

				<?php if ( '' !== $ctrh_key_hint ) : ?>
					<p class="ctrh-field__hint">
						<label>
							<input type="checkbox" name="ctrh_chat_forget" value="1">
							<?php esc_html_e( 'Remove the saved key', 'counterhand-mcp-for-woocommerce' ); ?>
						</label>
					</p>
				<?php endif; ?>
			</div>

			<details class="ctrh-card ctrh-card--collapsible"
				<?php echo ( null !== $ctrh_selected && $ctrh_selected->needs_base_url() ) ? 'open' : ''; ?>>
				<summary class="ctrh-card__head">
					<span class="ctrh-card__heading">
						<h3 class="ctrh-card__title"><?php esc_html_e( 'Advanced', 'counterhand-mcp-for-woocommerce' ); ?></h3>
						<span class="ctrh-card__desc"><?php esc_html_e( 'Only for a custom OpenAI-compatible endpoint. The other providers fill this in for you.', 'counterhand-mcp-for-woocommerce' ); ?></span>
					</span>
				</summary>
				<div class="ctrh-card__body">
					<label class="ctrh-label" for="ctrh-chat-base-url"><?php esc_html_e( 'Base URL', 'counterhand-mcp-for-woocommerce' ); ?></label>
					<input type="url" id="ctrh-chat-base-url" name="ctrh_chat_base_url" class="regular-text"
						value="<?php echo esc_attr( $chat_settings->base_url() ); ?>"
						placeholder="<?php echo esc_attr( OpenAiCompatibleProvider::ollama()->default_base_url() ); ?>">
				</div>
			</details>
		</div>

		<div class="ctrh-card__foot">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Connect model', 'counterhand-mcp-for-woocommerce' ); ?>
			</button>
			<span class="ctrh-field__hint">
				<?php esc_html_e( 'We send one tiny test message before saving, so a wrong key is caught here rather than on your first question.', 'counterhand-mcp-for-woocommerce' ); ?>
			</span>
		</div>
	</form>
	</details>
</div>
