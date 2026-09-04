=== Counterhand MCP for WooCommerce ===
Contributors: mirumd
Donate link: https://github.com/sponsors/miroslav-balan-at
Tags: woocommerce, mcp, ai, claude, chatgpt
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Make your WooCommerce store an MCP server, so Claude, ChatGPT and Cursor can manage products, orders and reports — with OAuth consent.

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

The plugin contacts no server of its own, collects no usage data and phones no telemetry home. It makes no outbound request until you ask it to — see the "External services" section below for exactly which services can be contacted, what is sent and when. Nothing is sent to the author of this plugin, ever.

== External services ==

This plugin does not depend on any service of its own. It never contacts the plugin author, and it sends no analytics or telemetry anywhere. Every outbound request below happens only as a direct result of something you do in wp-admin, and most stores will only ever use one of them.

= AI model providers (in-admin chat only) =

The "Chat" tab lets a store administrator ask questions about the store in plain language. Answering a question means sending it to an AI model, so this is the one feature that transmits store data to a third party. It is used only when you open the Chat tab and send a message; if you never use the chat, no request is ever made to any of these services.

On WordPress 7.0 and later the chat uses the AI model WordPress itself manages under Settings → Connectors, so this plugin never handles the API key and the provider is whichever one you configured in WordPress. On earlier WordPress versions, you choose a provider on the Chat tab and supply your own API key.

**What is sent, and when:** only when an administrator sends a chat message (and on each follow-up step of answering it) the plugin transmits, to the provider you chose: your message text, the earlier messages in that chat conversation, the list of enabled tool definitions (tool names, descriptions and argument schemas), and the results of any tool the model calls to answer you. **Those tool results contain your store's data** — for example product, order, customer or report records the model looked up in order to answer. A short system instruction accompanies the request, authenticated with your own API key (or, on WordPress 7.0 and later, by WordPress's connector, so this plugin never sees the key). Nothing is sent on a schedule or in the background.

There is one other, smaller request: when you save an API key on the Chat tab, the plugin sends a single "ping" message to the provider to check the key works before storing it, so a wrong key is reported immediately. That check contains no store data.

The provider you select determines the destination:

* **Anthropic (Claude)** — `https://api.anthropic.com`. [Terms of service](https://www.anthropic.com/legal/consumer-terms), [privacy policy](https://www.anthropic.com/legal/privacy).
* **OpenAI (ChatGPT)** — `https://api.openai.com`. [Terms of use](https://openai.com/policies/terms-of-use/), [privacy policy](https://openai.com/policies/privacy-policy/).
* **Google (Gemini)** — `https://generativelanguage.googleapis.com`. [Terms of service](https://policies.google.com/terms), [privacy policy](https://policies.google.com/privacy).
* **Ollama** — a model running on your own server (`http://localhost:11434` by default). No data leaves your machine and no account or key is needed.
* **Custom OpenAI-compatible endpoint** — any URL you enter yourself. The data goes wherever you point it, under that operator's terms; if that is a self-hosted model, nothing leaves your infrastructure.

Choosing Ollama or a self-hosted custom endpoint means the chat sends no store data to any third party.

= MCP client identity documents (when an AI app connects) =

When an AI assistant connects to your store, it identifies itself with a Client ID Metadata Document (CIMD) — a URL it publishes, as required by the MCP authorization specification. To show you on the consent screen which app is actually asking for access, the plugin fetches that URL once and caches the result.

**What is sent, and when:** an ordinary HTTP GET to the URL the connecting app supplied, at the moment someone starts a connection. It carries no store data, no personal data and no credentials — only the request itself. The URL must be HTTPS, and the request goes through WordPress's own safe HTTP function, so private-network and loopback addresses are refused. The destination is not fixed: it is whichever app you are connecting (for example `https://claude.ai/...` for Claude or `https://chatgpt.com/...` for ChatGPT), so the applicable terms are those of the AI app you chose to connect. If the document cannot be fetched or does not match, the connection is refused.

= WordPress.org (installing an AI provider plugin) =

On WordPress 7.0 and later, the Chat tab can install the official "AI Provider for Anthropic", "AI Provider for OpenAI" or "AI Provider for Google" plugin for you. This happens only when an administrator with permission to install plugins clicks the matching button. WordPress then fetches the plugin from the WordPress.org plugin directory the same way Plugins → Add New does, subject to the [WordPress.org privacy policy](https://wordpress.org/about/privacy/). No store data is sent; the API key for the provider is entered afterwards on WordPress's own Settings → Connectors screen.

= Your own store (reachability check) =

The "Connect AI apps" tab reports whether your MCP endpoint is actually reachable and advertising OAuth discovery. To find out, the plugin requests **your own site's** URLs (`/mcp` and `/.well-known/oauth-protected-resource`) over HTTP.

This is not a third-party service: the request goes to your own domain and no data leaves your server's control. It runs only while an administrator is viewing that tab.

= Links on the Connect AI apps tab =

That tab also shows documentation links for Claude, ChatGPT, Cursor and VS Code, and "add to editor" buttons. These are ordinary links and buttons in your browser — the plugin makes no request to those sites, and nothing is sent unless you click through.

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
2. Connect AI apps — one endpoint URL for Claude, ChatGPT, Cursor, VS Code and Claude Code, with a reachability check.
3. Chat — ask your store questions in plain language from wp-admin, on a model WordPress manages or your own key.
4. Action log — every tool call, with personal data masked before it is stored.

== Changelog ==

= 1.2.2 =
* The connecting app's identity document is fetched through WordPress's safe HTTP function, which refuses private-network and loopback addresses.
* Uninstall no longer runs a raw query against the options table; the plugin's short-lived transients expire on their own and WordPress's daily cleanup removes them.
* The readme discloses that the Chat tab can install the official AI Provider plugins from WordPress.org on request.
* On WordPress 7.0 and later the Chat tab no longer offers its own field for a WordPress connector key. It now reports which providers WordPress has and whether their key works, asked of the WordPress AI Client, and links to Settings → Connectors for entering the key — so the plugin never reads or writes a connector's stored API key.

= 1.2.1 =
* The OAuth consent pages load their stylesheets through WordPress's own style queue instead of writing `<link>` tags, and print only this plugin's own two sheets so nothing else can inject assets into a consent screen.
* The readme now documents every external service the plugin can contact, what is sent to each and when.

Older releases are listed in CHANGELOG.md in the plugin's GitHub repository.

== Upgrade Notice ==

= 1.2.2 =
Connector keys for the WordPress-managed model are now entered on the WordPress Settings → Connectors screen only; the plugin no longer touches them. Nothing changes for connected assistants.
