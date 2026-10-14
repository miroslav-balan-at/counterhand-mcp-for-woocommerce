# End-to-end tests (Playwright)

The suite drives a real store. It runs the OAuth consent flow in a browser and calls the MCP endpoint
over HTTP with a token that flow minted. It also covers the chat, the action log and every admin screen.
Unit tests cannot see any of that, because they run without WordPress.

## The store

The store is a disposable WordPress + WooCommerce store run by **devbox** (no Docker). Its definition
lives in `store/`:
- `devbox.json` and `process-compose.yaml`: MariaDB, PHP-FPM and nginx
- `config/`: nginx and php-fpm templates
- `scripts/setup-env.sh`: ports and paths
- `scripts/install-store.sh`: an idempotent install and reset

Everything it writes lives **outside the repo**, in `~/.cache/counterhand-e2e/` (WordPress, the
database, the generated test passwords, traces and the HTML report). The plugin is symlinked into the
store, and a store inside the repo would put the repo inside itself. nginx refuses the plugin's
`tests/`, `vendor/`, `node_modules/`, `bin/` and `build/` paths, so the store serves only what the
release zip ships.

```bash
npm ci && npx playwright install chromium   # once
npm run e2e:store:start                      # MariaDB, PHP-FPM, nginx on http://localhost:8007
npm run e2e                                  # installs/resets the store, then runs every spec
npx playwright test tests/e2e/specs/chat.spec.ts
npm run e2e:report                           # last HTML report
npm run e2e:store:stop
```

- `COUNTERHAND_E2E_PORT_OFFSET` (default `07`: nginx 8007, php-fpm 9007, MariaDB 3307) moves the ports.
- `COUNTERHAND_E2E_STATE_DIR` moves the state.
- To start from nothing: stop the store, delete `~/.cache/counterhand-e2e`, start it again.

The store answers on `localhost`, not `127.0.0.1`. The fixture client's callback is on `127.0.0.1`, and
the plugin treats a callback on its own host as self-hosted and finishes the flow in-page instead.

## What every run resets

Global setup runs `store/scripts/install-store.sh`, which brings the store to a known state:
- **Plugins:** WooCommerce and this plugin are active; the fixtures mu-plugin is linked in.
- **Permalinks:** pretty, so the plugin's routes resolve.
- **Connector:** switched on, with every tool group at its shipped default and the chat unconfigured.
- **Users:** `e2e-admin` and `e2e-shop-manager` get fresh random passwords in
  `~/.cache/counterhand-e2e/credentials.json`.
- **Content:** one product, *E2E Mug*.
- **Avatars:** off, so the remote-request check sees only the plugin's own traffic.

Specs that change settings restore them in `finally`/`afterAll`.

## The fixtures

`mu-plugins/counterhand-e2e-fixtures.php`:
- **CIMD documents** for any `https://*.client.example.test/oauth/client-metadata.json`, answered through
  `pre_http_request` because `wp_safe_remote_get()` rightly refuses every host a test runner could serve
  them from. `uniqueClientId()` gives a spec a connection no other spec shares.
- **A scripted chat model** (`e2e-scripted`), so the chat runs with no key and no network. It answers
  "list products" with a `list_products` call and "change the store address" with a
  confirmation-gated `update_setting` call.

## Writing a spec

- Talk to `/mcp` from `anonymousContext()`, a request context without cookies, like a real client.
- Get a token with `connect( page, baseURL, scopes )`, which works the consent screen as an admin would
  (Advanced scopes included). Never mint one in PHP.
- Use `wp()` and `patchSettings()` only for state a spec is not trying to prove, and restore it.
- Assert on real tool names. A `not.toContain` against a name that does not exist passes for the wrong
  reason; prove the name with a positive case first, as the fail-closed spec does.
