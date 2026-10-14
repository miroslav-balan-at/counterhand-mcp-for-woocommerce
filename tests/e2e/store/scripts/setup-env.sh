#!/usr/bin/env bash
# Environment for the disposable e2e store. Sourced from devbox.json's
# init_hook, so everything exported here reaches process-compose and WP-CLI.
#
# The store's files live outside the repo (COUNTERHAND_E2E_STATE_DIR): the
# plugin is symlinked into the store's plugins directory, and a store inside
# the repo would put the repo inside itself for every tool that walks it.

export COUNTERHAND_E2E_PLUGIN_DIR
COUNTERHAND_E2E_PLUGIN_DIR="$(cd "$DEVBOX_PROJECT_ROOT/../../.." && pwd)"
export COUNTERHAND_E2E_STATE_DIR="${COUNTERHAND_E2E_STATE_DIR:-$HOME/.cache/counterhand-e2e}"
export COUNTERHAND_E2E_WORDPRESS="$COUNTERHAND_E2E_STATE_DIR/wordpress"

# One two-digit offset for every port, as in the other ~/Work projects. 07 is
# unused there.
export COUNTERHAND_E2E_PORT_OFFSET="${COUNTERHAND_E2E_PORT_OFFSET:-07}"
export COUNTERHAND_E2E_HTTP_PORT="80${COUNTERHAND_E2E_PORT_OFFSET}"
export COUNTERHAND_E2E_PHP_FPM_PORT="90${COUNTERHAND_E2E_PORT_OFFSET}"
export COUNTERHAND_E2E_DATABASE_PORT="33${COUNTERHAND_E2E_PORT_OFFSET}"
# localhost, not 127.0.0.1: the fixture client's callback is on 127.0.0.1, and
# the store treats a callback on its own host as self-hosted and stops there.
export COUNTERHAND_E2E_BASE_URL="http://localhost:${COUNTERHAND_E2E_HTTP_PORT}"

export MYSQL_HOME="$COUNTERHAND_E2E_STATE_DIR/mariadb"
export MYSQL_UNIX_PORT="$COUNTERHAND_E2E_STATE_DIR/mariadb/mysql.sock"

mkdir -p \
	"$COUNTERHAND_E2E_STATE_DIR"/{mariadb/data,log,php/sessions} \
	"$COUNTERHAND_E2E_STATE_DIR"/nginx/temp/{client_body,proxy,fastcgi,uwsgi,scgi} \
	"$COUNTERHAND_E2E_WORDPRESS"
chmod 700 "$COUNTERHAND_E2E_STATE_DIR"

# nginx needs its own store's conf dir for mime.types and fastcgi_params.
COUNTERHAND_E2E_NGINX_CONF="$(nginx -V 2>&1 | grep -o 'prefix=[^ ]*' | cut -d= -f2)/conf"
export COUNTERHAND_E2E_NGINX_CONF

envsubst '${COUNTERHAND_E2E_STATE_DIR} ${COUNTERHAND_E2E_WORDPRESS} ${COUNTERHAND_E2E_NGINX_CONF} ${COUNTERHAND_E2E_HTTP_PORT} ${COUNTERHAND_E2E_PHP_FPM_PORT}' \
	<"$DEVBOX_PROJECT_ROOT/config/nginx.template" >"$COUNTERHAND_E2E_STATE_DIR/nginx/nginx.conf"

envsubst '${COUNTERHAND_E2E_STATE_DIR} ${COUNTERHAND_E2E_PHP_FPM_PORT}' \
	<"$DEVBOX_PROJECT_ROOT/config/php-fpm.template" >"$COUNTERHAND_E2E_STATE_DIR/php/php-fpm.conf"
