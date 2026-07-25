=== AgentGate MCP for WooCommerce ===
Contributors: miroslavbalan
Tags: woocommerce, mcp, ai, claude, oauth
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn your WooCommerce store into a secure MCP server so AI assistants like Claude and Cursor can manage products, orders and reports — with OAuth consent.

== Description ==

AgentGate MCP connects your WooCommerce store to AI assistants through the Model Context Protocol (MCP) — with security as the first-class feature.

Your store gets a clean MCP endpoint at `https://yourstore.com/mcp`. Assistants connect through a **browser consent flow** (OAuth 2.1) — no tokens to copy. When an assistant connects, your browser opens a consent screen where you, as a store administrator, choose exactly what it may do and approve.

* **OAuth 2.1 with PKCE** — the modern MCP authorization standard. Client identity via Client ID Metadata Documents (CIMD); no client secrets, no manual registration.
* **You approve scopes per connection** — the consent screen shows the requested scopes as checkboxes you can narrow before approving: products read/write, orders read/write, customers read, reports read. Write never implies read.
* **Fail-closed security** — disabled tool groups and missing scopes make tools invisible AND uncallable. A connection can never do more than the administrator who approved it; demoting or removing that user instantly disables it.
* **Audience-bound, hashed tokens** — access tokens are bound to your store (RFC 8707) and stored as SHA-256 hashes. Constant-time verification, no authentication oracle, per-connection rate limiting.
* **Safe defaults for writes** — products are created as drafts for human review; deletion moves to trash unless permanent deletion is explicitly requested.
* **Revoke anytime** — the Connections tab lists every connected assistant; one click cuts off its access.
* **Opt-in action log** — record every tool call with PII (emails, phone numbers) masked before storage, configurable retention, one-click clearing.

= 14 tools out of the box =

* Products: list, get, create, update, delete
* Orders: list, get, update status, add note
* Customers: list, get
* Reports: sales, top sellers, store overview

Tools delegate to WooCommerce's own REST controllers, so validation, stock handling and HPOS compatibility behave exactly like the standard API.

= Connect in minutes =

The Connect tab shows your endpoint and copy-paste configuration for Claude Code, Claude Desktop and Cursor. Add the endpoint URL, run the connect command, approve the consent screen in your browser — done. No local proxy, no Node.js required.

== Frequently Asked Questions ==

= How does an assistant connect? =

Add the endpoint URL (`https://yourstore.com/mcp`) to your MCP client — no token needed. The client discovers the OAuth authorization server, opens your browser to a consent screen, and you approve which scopes it may use. Approved assistants appear on the Connections tab.

= The endpoint or discovery documents return 404 or 403 =

Two causes: (1) pretty permalinks — the endpoint is also available at `/wp-json/agentgate/v1/mcp`, and re-saving Settings → Permalinks refreshes rewrite rules; (2) some servers block `/.well-known/` paths. OAuth discovery lives at `/.well-known/oauth-protected-resource`, so your server must allow that path. On nginx, add before any dotfile-deny rule:

`location ^~ /.well-known/ { try_files $uri $uri/ /index.php?$args; }`

= Which AI clients work? =

Any MCP client implementing the 2025-06-18 authorization spec (OAuth 2.1 + PKCE + Protected Resource Metadata): Claude Code, Claude Desktop, Cursor, and others.

= Can I connect a client that only supports bearer headers? =

The security model is OAuth-first. If you need a raw bearer token for a script or CI job, use the `agmcp_rate_limit` and related filters documented in the plugin, or open an issue — a developer token path may be added.

== Changelog ==

= 0.1.0 =
* Initial release: OAuth 2.1 + CIMD browser-consent authorization, MCP server endpoint, 14 WooCommerce tools, per-connection scoped access with audience binding, Connections management with revoke, opt-in action log with PII masking.
