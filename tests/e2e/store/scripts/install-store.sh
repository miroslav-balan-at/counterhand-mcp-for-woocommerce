#!/usr/bin/env bash
# Builds the disposable e2e store, or brings an existing one back to the state
# every spec assumes. Idempotent; Playwright's global setup runs it each time.
# Needs the services up: `npm run e2e:store:start`.
set -euo pipefail

socket="$MYSQL_UNIX_PORT"
wordpress="$COUNTERHAND_E2E_WORDPRESS"
credentials_file="$COUNTERHAND_E2E_STATE_DIR/credentials.json"

wp() {
	command wp --path="$wordpress" "$@"
}

if ! mariadb-admin --socket="$socket" ping >/dev/null 2>&1; then
	echo 'The e2e store is not running. Start it with: npm run e2e:store:start' >&2
	exit 1
fi

# No secret worth guarding: the database listens on loopback only and holds a
# throwaway store.
mariadb --socket="$socket" -u root <<'SQL'
CREATE DATABASE IF NOT EXISTS counterhand_e2e CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'counterhand_e2e'@'localhost' IDENTIFIED BY 'counterhand_e2e';
GRANT ALL PRIVILEGES ON counterhand_e2e.* TO 'counterhand_e2e'@'localhost';
SQL

if [ ! -f "$wordpress/wp-settings.php" ]; then
	wp core download --skip-content --force --quiet
fi
mkdir -p "$wordpress"/wp-content/{plugins,themes,uploads,mu-plugins}

if [ ! -f "$wordpress/wp-config.php" ]; then
	wp config create --dbname=counterhand_e2e --dbuser=counterhand_e2e --dbpass=counterhand_e2e \
		--dbhost="localhost:$socket" --skip-check --quiet --extra-php <<'PHP'
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'DISALLOW_FILE_EDIT', true );
PHP
fi

if ! wp core is-installed 2>/dev/null; then
	wp core install --url="$COUNTERHAND_E2E_BASE_URL" --title='Counterhand e2e store' \
		--admin_user=e2e-admin --admin_password="$(openssl rand -hex 16)" \
		--admin_email=e2e-admin@example.test --skip-email --quiet
fi

# A default theme, so the storefront and the admin bar render as on a real store.
wp theme is-installed twentytwentyfive 2>/dev/null || wp theme install twentytwentyfive --activate --quiet

wp plugin is-installed woocommerce 2>/dev/null || wp plugin install woocommerce --quiet
ln -sfn "$COUNTERHAND_E2E_PLUGIN_DIR" "$wordpress/wp-content/plugins/counterhand-mcp-for-woocommerce"
ln -sf "$COUNTERHAND_E2E_PLUGIN_DIR/tests/e2e/mu-plugins/counterhand-e2e-fixtures.php" "$wordpress/wp-content/mu-plugins/counterhand-e2e-fixtures.php"
wp plugin activate woocommerce counterhand-mcp-for-woocommerce --quiet

# Installed unpinned, so a fresh store (every CI run) tests the latest releases.
# Off unless a spec switches them on: their admin assets would trip admin-ui.spec.
for integration in contact-form-7 flamingo; do
	wp plugin is-installed "$integration" 2>/dev/null || wp plugin install "$integration" --quiet
	wp plugin deactivate "$integration" --quiet 2>/dev/null || true
done

wp rewrite structure '/%postname%/' --quiet
wp rewrite flush --quiet

# WooCommerce would otherwise send wp-admin into onboarding and hide the shop.
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json --quiet
wp option update woocommerce_coming_soon no --quiet
# The admin bar's Gravatar is the only remote request a bare admin page makes;
# without it the no-remote-assets check sees only the plugin's own traffic.
wp option update show_avatars 0 --quiet

# Reset what the specs depend on: the connector on, every group at its shipped
# default, the chat unconfigured.
wp option update counterhand_settings '{"enabled":true}' --format=json --quiet
wp option delete counterhand_chat --quiet 2>/dev/null || true
wp transient delete --all --quiet

# Fresh passwords every run; the file sits outside the repo and the web root.
admin_password="$(openssl rand -hex 16)"
manager_password="$(openssl rand -hex 16)"
wp user get e2e-shop-manager --field=ID >/dev/null 2>&1 \
	|| wp user create e2e-shop-manager e2e-shop-manager@example.test --role=shop_manager --quiet >/dev/null
wp user update e2e-admin --user_pass="$admin_password" --skip-email --quiet
wp user update e2e-shop-manager --user_pass="$manager_password" --skip-email --quiet
(
	umask 077
	printf '{"admin":{"username":"e2e-admin","password":"%s"},"shopManager":{"username":"e2e-shop-manager","password":"%s"}}\n' \
		"$admin_password" "$manager_password" >"$credentials_file"
)

if [ -z "$(wp post list --post_type=product --meta_key=_sku --meta_value=e2e-mug --format=ids)" ]; then
	wp wc product create --user=e2e-admin --name='E2E Mug' --regular_price=12.50 --sku=e2e-mug --status=publish --quiet >/dev/null
fi

echo "e2e store ready at $COUNTERHAND_E2E_BASE_URL"
