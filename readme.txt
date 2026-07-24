=== AgentGate MCP for WooCommerce ===
Contributors: miroslavbalan
Tags: woocommerce, mcp, ai, claude, chatgpt
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn your WooCommerce store into a secure MCP server so AI assistants like Claude, ChatGPT and Cursor can manage products, orders and reports.

== Description ==

AgentGate MCP connects your WooCommerce store to AI assistants through the Model Context Protocol (MCP) — with security as the first-class feature.

Your store gets a clean MCP endpoint at `https://yourstore.com/mcp`. AI assistants authenticate with **scoped, revocable API tokens** that you create and control:

* **Scoped tokens** — grant exactly what each assistant needs: products read/write, orders read/write, customers read, reports read. Write never implies read.
* **Fail-closed security** — disabled tool groups and missing scopes make tools invisible AND uncallable. A token can never do more than the administrator who created it; demoting or removing that user instantly disables their tokens.
* **Hashed at rest** — token secrets are stored as SHA-256 hashes and shown exactly once at creation. Constant-time verification, no authentication oracle, per-token rate limiting.
* **Safe defaults for writes** — products are created as drafts for human review; deletion moves to trash unless permanent deletion is explicitly requested.
* **Opt-in action log** — record every tool call with PII (emails, phone numbers) masked before storage, configurable retention, one-click clearing.

= 14 tools out of the box =

* Products: list, get, create, update, delete
* Orders: list, get, update status, add note
* Customers: list, get
* Reports: sales, top sellers, store overview

Tools delegate to WooCommerce's own REST controllers, so validation, stock handling and HPOS compatibility behave exactly like the standard API.

= Connect in minutes =

The Connect tab shows your endpoint, a connection verifier, and copy-paste configuration for Claude Code, Claude Desktop and Cursor. No local proxy, no Node.js required.

== Frequently Asked Questions ==

= The endpoint returns 404 =

Your host may not support pretty permalinks for custom routes. The same endpoint is always available at `/wp-json/agentgate/v1/mcp`, and re-saving Settings → Permalinks refreshes rewrite rules.

= Authentication fails although the token is correct =

Some Apache/CGI hosts strip the Authorization header. Send the token in the `X-AgentGate-Token` header instead, or add `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1` to your .htaccess.

= Which AI clients work? =

Any MCP client speaking streamable HTTP with header authentication: Claude Code, Claude Desktop, Cursor, and others. Web-based connectors that require OAuth are on the roadmap.

== Changelog ==

= 0.1.0 =
* Initial release: MCP server endpoint, scoped token system, 14 WooCommerce tools, admin UI with connection verifier, opt-in action log with PII masking.
