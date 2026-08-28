=== Counterhand MCP for WooCommerce ===
Contributors: miroslavbalan
Donate link: https://github.com/sponsors/miroslav-balan-at
Tags: woocommerce, mcp, ai, claude, chatgpt
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn your WooCommerce store into a secure MCP server so AI assistants like Claude, ChatGPT and Cursor can manage products, orders and reports — with OAuth consent.

== Description ==

Counterhand MCP connects your WooCommerce store to AI assistants through the Model Context Protocol (MCP) — with security as the first-class feature. It is free, open source (GPL) and self-hosted: nothing about your store passes through a third-party service.

Your store gets a clean MCP endpoint at `https://yourstore.com/mcp`. Assistants connect through a **browser consent flow** (OAuth 2.1) — no tokens to copy. When an assistant connects, your browser opens a consent screen where you, as a store administrator, choose exactly what it may do and approve.

* **OAuth 2.1 with PKCE** — the modern MCP authorization standard. Client identity via Client ID Metadata Documents (CIMD); no client secrets, no manual registration.
* **You approve scopes per connection** — the consent screen groups the requested scopes under plain headings (Catalog, Sales, Insights, Content, Store setup, Advanced) as checkboxes you can narrow before approving. Write never implies read, and anything under Advanced is collapsed and never pre-ticked.
* **Fail-closed security** — disabled tool groups and missing scopes make tools invisible AND uncallable. A connection can never do more than the administrator who approved it; demoting or removing that user instantly disables it.
* **Audience-bound, hashed tokens** — access tokens are bound to your store (RFC 8707) and stored as SHA-256 hashes. Constant-time verification, no authentication oracle, per-connection rate limiting.
* **Safe defaults for writes** — products and posts are created as drafts for human review; deletion moves to trash unless permanent deletion is explicitly requested.
* **Confirmation for the dangerous few** — changing a store setting, enabling or disabling a payment gateway, or running a WooCommerce maintenance tool each require an explicit confirmation argument, so an assistant has to tell you what it is about to do and get your agreement first.
* **Credentials stay out of reach** — settings named like an API key, secret, password or token are never writable through the API, payment gateway credentials are neither read nor written, and maintenance routines that cannot be undone (resetting user roles, deleting tax rates, dropping order tables, running the database migration) are refused outright.
* **Custom fields are guarded** — WordPress's own private fields are hidden, and the keys that hold roles, capabilities and login sessions can be neither read nor written. Customer custom fields are read-only.
* **Revoke anytime** — the Connections tab lists every connected assistant; one click cuts off its access.
* **Opt-in action log** — record every tool call with PII (emails, phone numbers) masked before storage, configurable retention, one-click clearing.

= 127 tools, in groups you switch on individually =

Every group is a separate switch in Settings, with read and write as separate axes. A fresh install exposes **Products, Orders and Reports, read-only** — everything else is off until you turn it on, and upgrading never widens what your store exposes.

* **Catalog** — Products (9), Categories & tags (25), Variations (5), Reviews (5), Coupons (8)
* **Sales** — Orders (10), Refunds (4), Customers (3)
* **Insights** — Reports (8)
* **Content** — Posts & pages (10)
* **Store setup** — Shipping (14), Taxes (8), Reference data (7)
* **Advanced** — Store settings (4), Payment gateways (3), System & maintenance (4)

Tools delegate to WooCommerce's own REST controllers, so validation, stock handling and HPOS compatibility behave exactly like the standard API. Their input schemas are read from those controllers at runtime rather than copied into this plugin, so a WooCommerce update that adds a field surfaces it automatically, and one that removes a field cannot leave a tool advertising it.

Each tool offers the ten or so fields that matter rather than all hundred WooCommerce declares, which keeps assistants accurate. When one of the rest is needed, `describe_woocommerce_fields` returns the full list for any tool — and writes accept those extra fields directly.

Visibility is decided by WooCommerce too: before a tool is offered, this plugin runs WooCommerce's own permission check for that endpoint. A shop manager therefore sees a different set of tools from an administrator, without this plugin keeping a list of who may do what.

= Two ways to use AI with your store =

**Chat with your store, inside WooCommerce.** Ask questions in plain language from wp-admin and the assistant looks the answer up with the same tools an outside app would use. On WordPress 7.0 and later it uses the AI model WordPress already manages under Settings → Connectors, so this plugin never handles an API key. On older WordPress, connect Claude, ChatGPT, Gemini or a local Ollama model with your own key — the Chat tab tests it before saving, so a wrong key is caught immediately.

**Connect AI apps you already use.** The Connect AI apps tab shows one URL to paste into Claude, ChatGPT, Claude Code or any other MCP client — one click installs it into Cursor and VS Code. There is no token to create and nothing to copy back: the app identifies itself with its own published address (CIMD), and you approve exactly what it may do on a consent screen in your browser. No local proxy, no Node.js required.

= How this differs from WooCommerce's built-in MCP =

WooCommerce ships an experimental MCP server behind a feature flag, authenticated with REST API keys and covering a handful of product and order abilities. Counterhand adds what a self-hosted store still lacks: OAuth 2.1 browser consent instead of copied keys, the whole WooCommerce and WordPress surface (coupons, customers, reports, shipping, tax, settings, posts and pages), per-connection scopes with one-click revocation, confirmation-gated risky writes, an audit log, and the in-admin chat. The two can run side by side.

= Free, open source, sponsor-supported =

Counterhand is free for every store, with no paid tier and no locked features. Development and support are funded by [GitHub Sponsors](https://github.com/sponsors/miroslav-balan-at). The source is on [GitHub](https://github.com/miroslav-balan-at/counterhand-mcp-for-woocommerce) — issues and pull requests are welcome.

= Privacy =

The plugin contacts no server of its own and collects no usage data. Requests leave your site only when you initiate them: to the AI provider you configured for the in-admin chat (with your own key, or through WordPress's AI connectors), to an MCP client's published metadata URL when it connects, and to your own site for the reachability check on the Connect AI apps tab.

== Installation ==

1. In wp-admin go to Plugins → Add New, search for "Counterhand MCP" and click Install, then Activate. WooCommerce must already be active.
2. Open WooCommerce → Counterhand MCP. On the Settings tab switch on the tool groups your assistants may use — Products, Orders and Reports are on, read-only, by default.
3. On the Connect AI apps tab copy the endpoint URL (`https://yourstore.com/mcp`) into Claude, ChatGPT, Claude Code, Cursor or VS Code — or use the one-click install buttons.
4. Approve the connection on the consent screen that opens in your browser. It appears on the Connections tab, where you can revoke it at any time.

Your store must be reachable over HTTPS from the public internet for cloud assistants (Claude, ChatGPT); Claude Code, Cursor and VS Code connect from your own machine and work with a local site too.

== Frequently Asked Questions ==

= Is it really free? =

Yes. Every tool, the chat, OAuth and the action log are in the one free plugin, licensed GPLv2 or later. There is no Pro version and nothing is unlocked by paying. If it saves you time, you can support its maintenance through [GitHub Sponsors](https://github.com/sponsors/miroslav-balan-at).

= How does an assistant connect? =

Add the endpoint URL (`https://yourstore.com/mcp`) to your MCP client — no token needed. The client discovers the OAuth authorization server, opens your browser to a consent screen, and you approve which scopes it may use. Approved assistants appear on the Connections tab.

= The endpoint or discovery documents return 404 or 403 =

Two causes: (1) pretty permalinks — the endpoint is also available at `/wp-json/counterhand/v1/mcp`, and re-saving Settings → Permalinks refreshes rewrite rules; (2) some servers block `/.well-known/` paths. OAuth discovery lives at `/.well-known/oauth-protected-resource`, so your server must allow that path. On nginx, add before any dotfile-deny rule:

`location ^~ /.well-known/ { try_files $uri $uri/ /index.php?$args; }`

= Which AI clients work? =

Any MCP client implementing the current authorization spec (OAuth 2.1 + PKCE + Protected Resource Metadata, with CIMD client identity): Claude on web, mobile and desktop, ChatGPT, Claude Code, Cursor, VS Code, and others.

= Claude or ChatGPT cannot see my store =

Those apps connect from the vendor's own servers, not from your browser, so your store has to be reachable from the public internet over HTTPS. A local development site works fine with Claude Code, Cursor and VS Code, which connect from your own machine. The Connect AI apps tab checks this for you and says which of the two situations you are in.

= An assistant says a tool does not exist =

Three things have to agree before a tool is callable: the group is switched on in Settings, the connection was granted that scope on the consent screen, and WooCommerce's own permission check passes for the logged-in owner of the connection. If any one of them says no, the tool is invisible *and* uncallable — it is never merely hidden. Check the Settings tab first, then the connection's scopes on the Connections tab.

= Can an assistant break my store? =

The things that could are gated separately. Store settings, payment gateways and system maintenance are their own groups, off by default, sitting behind a collapsed Advanced heading that is never pre-ticked; each of their write tools requires an explicit confirmation; and the maintenance routines that cannot be undone are refused whatever an assistant sends. Calls to those three groups are recorded in the action log even if you have logging switched off.

= Can I connect a client that only supports bearer headers? =

The security model is OAuth-first. If you need a raw bearer token for a script or CI job, use the `counterhand_rate_limit` and related filters documented in the plugin, or open an issue on GitHub — a developer token path may be added.

= Where do I report a bug or a security issue? =

Bugs and feature requests: the [GitHub issue tracker](https://github.com/miroslav-balan-at/counterhand-mcp-for-woocommerce/issues) or the support forum here. Security issues: please report them privately as described in the repository's SECURITY.md rather than in a public thread.

== Screenshots ==

1. Settings — every tool group is its own switch, with read and write as separate axes.
2. Connect AI apps — one endpoint URL, one-click install for Cursor and VS Code, and a reachability check.
3. The consent screen an assistant's connection opens in your browser — narrow the scopes before approving.
4. Connections — every connected assistant, what it may do, and a revoke button.
5. Chat — ask your store questions in plain language from wp-admin.
6. Action log — every tool call, with personal data masked.

== Changelog ==

= 1.2.0 =
* First release in the wordpress.org plugin directory. Updates and language packs now come from WordPress itself.
* Deleting the plugin removes its tables, options and transients through a standard `uninstall.php`.

= 1.1.1 =
* German translation (de_DE) for the whole plugin, and the bundled language files now load.

= 1.1.0 =
* The action log is paginated: 25 calls per page with WordPress's own pagination, instead of stopping silently at the newest 100. The tab also shows the total number of recorded calls.
* The plugin's admin screens carry the product's mark, and the admin menu icon is the brand glyph in the standard monochrome form WordPress recolours to your admin scheme.
* The log table is easier to read: monospace timestamps and tool names, clearer success and failure states, and an empty state that says how to start recording.

= 1.0.0 =
* 127 tools across 16 switchable groups, covering the catalog, sales, content, store setup and — behind an Advanced heading — configuration and maintenance. Every new group ships disabled: upgrading exposes exactly what it exposed before.
* Tool input schemas are now read from WooCommerce's own REST controllers at runtime instead of being restated in this plugin, so they track WooCommerce across updates and cannot advertise a field that no longer exists. Each tool publishes a curated subset; `describe_woocommerce_fields` reveals the rest on demand.
* Tool visibility now runs WooCommerce's own permission check for each endpoint, so a shop manager and an administrator see different tools without this plugin keeping a capability list.
* Store settings, payment gateway and system maintenance writes require an explicit confirmation; settings named like credentials, payment gateway credentials, and maintenance routines that cannot be undone are refused outright. Calls to those groups are always recorded in the action log.
* Custom field tools for products, orders, coupons and customers, with WordPress's private fields hidden and the keys holding roles, capabilities and login sessions blocked in both directions. Customer custom fields are read-only.
* Consent screen and settings tab are grouped under plain headings, with Advanced collapsed and never pre-ticked. A client that requests no scopes is now offered a conservative read-only default rather than everything.

= 0.1.0 =
* Initial release: OAuth 2.1 + CIMD browser-consent authorization, MCP server endpoint, 14 WooCommerce tools, per-connection scoped access with audience binding, Connections management with revoke, opt-in action log with PII masking.
* In-admin chat with support for the WordPress 7.0 AI Client, so model credentials are managed by WordPress rather than this plugin.
* One-click install links for Cursor and VS Code, and an automatic readiness check that reports whether cloud assistants can reach the store.

== Upgrade Notice ==

= 1.2.0 =
First release in the wordpress.org plugin directory. Nothing changes for connected assistants.
