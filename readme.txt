=== Counterhand MCP for WooCommerce – MCP Server & AI Agent for Claude, ChatGPT & Cursor ===
Contributors: mirumd
Donate link: https://github.com/sponsors/miroslav-balan-at
Tags: woocommerce, mcp, mcp-server, claude, ai-agent
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let Claude, ChatGPT or Cursor work your WooCommerce store: sales, stock, orders, product texts. You approve every app and every risky change.

== Description ==

**Your WooCommerce store, inside Claude, ChatGPT and Cursor.** Ask what sold last week, find low stock, draft better product texts or look up an order, from the AI app you already use or from a chat inside wp-admin. Each app gets only the areas you tick, and anything risky waits for your Approve.

* **Approve, don't paste keys** — apps connect through a consent screen in your browser. You tick what each one may read and change, and take it back with one click.
* **Changes wait for you** — anything that moves money or reaches a customer, such as a price change, a refund or a new coupon, runs only after you approve it. A price change shows the price before and after.
* **Free and self-hosted** — open source (GPL), no account, no service of ours in between. Store data reaches only the apps you connect and the AI model you choose.

= Ask it things like =

* "What sold last week, and how does that compare with the week before?"
* "Which products are running low? Draft a restock list."
* "Rewrite the short descriptions in Kitchen & Table to sound warmer."
* "Which orders have been on hold for more than three days?"
* "Raise the price of the sage mug to 16." It waits for your Approve.

Home page and documentation: [counterhand.app](https://counterhand.app/).

= Built for security =

Your store gets one MCP endpoint at `https://yourstore.com/mcp`, and apps connect through a browser consent flow (OAuth 2.1), never a copied key.

* **OAuth 2.1 with PKCE** — the modern MCP authorization standard. Client identity via Client ID Metadata Documents (CIMD); no client secrets, no manual registration.
* **You approve what each app may do** — the consent screen asks "Let this app work with your store?" and lists the requested permissions under plain headings (Catalog, Sales, Insights, Content, Store setup, Advanced, Other plugins) as checkboxes you can narrow before approving. Changing never implies reading, and Advanced and Other plugins stay folded and are never pre-ticked.
* **Fail-closed security** — an area switched off for the store, or a permission the app was not granted, makes its tools invisible AND uncallable. A connection can never do more than the administrator who approved it; demoting or removing that user instantly disables it.
* **Audience-bound, hashed tokens** — access tokens are bound to your store (RFC 8707) and stored as SHA-256 hashes. Constant-time verification, no authentication oracle, per-connection rate limiting. Apps that can refresh get one-hour access tokens and a rotating refresh token; a replayed refresh token revokes the connection.
* **Safe defaults for writes** — products and posts are created as drafts for human review; deletion moves to trash unless permanent deletion is explicitly requested.
* **Confirmation for the dangerous few** — changing a store setting, enabling or disabling a payment gateway, or running a WooCommerce maintenance tool each require an explicit confirmation argument, so an assistant has to tell you what it is about to do and get your agreement first.
* **Credentials stay out of reach** — settings named like an API key, secret, password or token are never writable through the API, payment gateway credentials are neither read nor written, and maintenance routines that cannot be undone (resetting user roles, deleting tax rates, dropping order tables, running the database migration) are refused outright.
* **Custom fields are guarded** — WordPress's own private fields are hidden, and the keys that hold roles, capabilities and login sessions can be neither read nor written. Customer custom fields are read-only.
* **Revoke anytime** — Connect apps › Connected apps lists every app; one click cuts off its access.
* **Opt-in activity log** — record every tool call, with names, addresses, emails and phone numbers masked before storage, configurable retention and one-click clearing.

= 132 tools, in areas you switch on one by one =

Every area is its own switch on the Access screen, with Can read and Can change as separate switches. A fresh install exposes **Products, Orders and Reports, read-only** — everything else is off until you turn it on, and upgrading never widens what your store exposes.

* **Catalog** — Products (11), Categories & tags (25), Variations (5), Reviews (5), Coupons (8)
* **Sales** — Orders (11), Refunds (4), Customers (3)
* **Insights** — Reports (8)
* **Content** — Posts & pages (10)
* **Store setup** — Shipping (14), Taxes (8), Reference data (7)
* **Advanced** — Store settings (4), Payment gateways (3), System & maintenance (4), Undo history (2)

Tools delegate to WooCommerce's own REST controllers, so validation, stock handling and HPOS compatibility behave exactly like the standard API. Their input schemas are read from those controllers at runtime rather than copied into this plugin, so a WooCommerce update that adds a field surfaces it automatically, and one that removes a field cannot leave a tool advertising it.

Each tool offers the ten or so fields that matter, which keeps assistants accurate; `describe_woocommerce_fields` reveals the rest when one is needed.

Visibility is decided by WooCommerce too: before a tool is offered, this plugin runs WooCommerce's own permission check for that endpoint. A shop manager therefore sees a different set of tools from an administrator, without this plugin keeping a list of who may do what.

= Works with your other plugins =

Off until you switch each on: WPML, Germanized and Germanized Pro, Contact Form 7, Flamingo, Advanced Custom Fields, Shipment Tracking, Cost of Goods, URL Coupons, and abilities from Yoast SEO, Rank Math, WPForms and others. An assistant can preview most catalog changes, and undo them for 30 days. The FAQ has the details.

= Two ways to use AI with your store =

**Chat inside wp-admin**, on the AI model WordPress manages under Settings → Connectors (WordPress 7.0 and later), or on Claude, ChatGPT, Gemini or a local Ollama model with your own key. It uses the same tools an outside app would, and a change that is not easily undone waits for your Approve button.

**Connect the AI apps you already use.** Paste one URL into Claude, ChatGPT, Claude Code or any other MCP client, or add the store to Cursor and VS Code in one click. No token to create, no local proxy, no Node.js.

= More than tools =

* **Overview** — opens first: what is still to set up, whether AI access is on, the connected apps, and what AI did today.
* **Rewrites** — the AI model drafts new descriptions for up to 25 products in a category. Nothing changes until you approve each text, and with undo history on an applied text can be undone.
* **Weekly report** — each person can ask for last week's sales, best sellers and waiting orders by email every Monday, as WooCommerce's own reports state them. With an AI model chosen, a short summary written by AI comes first, labelled as such.
* **Activity** — every call apps and the chat made, newest first, if you switch recording on.

= How this differs from WooCommerce's built-in MCP =

WooCommerce registers a handful of product and order abilities, which the separate MCP adapter plugin can offer to AI clients, signed in with an application password. Counterhand adds what a self-hosted store still lacks: OAuth 2.1 browser consent instead of copied keys, the whole WooCommerce and WordPress surface (coupons, customers, reports, shipping, tax, settings, posts and pages), permissions chosen per app with one-click revocation, confirmation-gated risky writes, an activity log, undo, and the in-admin chat. The two can run side by side.

= Free, open source, sponsor-supported =

Counterhand is free for every store, with no paid tier and no locked features. Development and support are funded by [GitHub Sponsors](https://github.com/sponsors/miroslav-balan-at). The source is on [GitHub](https://github.com/miroslav-balan-at/counterhand-mcp-for-woocommerce) — issues and pull requests are welcome.

= Privacy =

The plugin contacts no server of its own, collects no usage data and phones no telemetry home. It makes no outbound request until you ask it to — see the "External services" section below for exactly which services can be contacted, what is sent and when. Nothing is sent to the author of this plugin, ever.

== External services ==

This plugin does not depend on any service of its own. It never contacts the plugin author, and it sends no analytics or telemetry anywhere. Every outbound request below happens only as a direct result of something you do in wp-admin, and most stores will only ever use one of them.

= AI model providers (chat, Rewrites and the weekly report summary) =

Three features send store data to the AI model you choose on the Chat screen: the chat, Rewrites and the AI summary in the weekly report. They are the only features that transmit store data to a third party, and none of them sends anything until you choose a model. If you never choose one, no request is ever made to any of these services.

On WordPress 7.0 and later the AI model can be the one WordPress itself manages under Settings → Connectors, so this plugin never handles the API key and the provider is whichever one you configured in WordPress. Otherwise you choose a provider on the Chat screen and supply your own API key.

**What is sent, and when:** only when an administrator sends a chat message (and on each follow-up step of answering it) the plugin transmits, to the provider you chose: your message text, the earlier messages in that chat conversation, the list of enabled tool definitions (tool names, descriptions and argument schemas), and the results of any tool the model calls to answer you. **Those tool results contain your store's data** — for example product, order, customer or report records the model looked up in order to answer. A short system instruction accompanies the request, authenticated with your own API key (or, on WordPress 7.0 and later, by WordPress's connector, so this plugin never sees the key).

**Rewrites:** only when an administrator or shop manager starts a rewrite job, the plugin sends each chosen product's name, current description or short description and the person's instructions to the model, one product at a time, in the background through WooCommerce's Action Scheduler. Nothing else about the store is sent, and nothing is sent after the job ends.

**Weekly report summary:** only for a person who asked for the weekly report, and only while a model is chosen, the plugin sends the store's name and the figures for last week and the week before (sales totals, best sellers by name and quantity, the number of orders waiting) to the model every Monday morning to write a short summary. No customer data is included. Without a model the report goes out without a summary and nothing is sent.

There is one other, smaller request: when you save an API key on the Chat screen, the plugin sends a single "ping" message to the provider to check the key works before storing it, so a wrong key is reported immediately. That check contains no store data.

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

On WordPress 7.0 and later, the Chat screen can install the official "AI Provider for Anthropic", "AI Provider for OpenAI" or "AI Provider for Google" plugin for you. This happens only when an administrator with permission to install plugins clicks the matching button. WordPress then fetches the plugin from the WordPress.org plugin directory the same way Plugins → Add New does, subject to the [WordPress.org privacy policy](https://wordpress.org/about/privacy/). No store data is sent; the API key for the provider is entered afterwards on WordPress's own Settings → Connectors screen.

= Your own store (reachability check) =

The "Connect apps" screen reports whether your MCP endpoint is actually reachable and advertising OAuth discovery. To find out, the plugin requests **your own site's** URLs (`/mcp` and `/.well-known/oauth-protected-resource`) over HTTP.

This is not a third-party service: the request goes to your own domain and no data leaves your server's control. It runs only while an administrator is viewing that screen.

= Links on the Connect apps screen =

That screen also shows documentation links for Claude, ChatGPT, Cursor and VS Code, and "add to editor" buttons. These are ordinary links and buttons in your browser — the plugin makes no request to those sites, and nothing is sent unless you click through.

== Installation ==

1. In wp-admin go to Plugins → Add New, search for "Counterhand MCP" and click Install, then Activate. WooCommerce must already be active.
2. Open Counterhand MCP in the admin menu. Overview lists what is still to set up. On Access switch on the areas AI may read and change — Products, Orders and Reports can be read by default, and nothing can be changed.
3. On Connect apps copy the endpoint URL (`https://yourstore.com/mcp`) into Claude, ChatGPT, Claude Code, Cursor or VS Code — or use the one-click install buttons.
4. Approve the app on the consent screen that opens in your browser. It appears under Connect apps › Connected apps, where you can revoke it at any time.

Your store must be reachable over HTTPS from the public internet for cloud assistants (Claude, ChatGPT); Claude Code, Cursor and VS Code connect from your own machine and work with a local site too.

== Frequently Asked Questions ==

= Is it really free? =

Yes. Every tool, the chat, Rewrites, the weekly report, OAuth and the activity log are in the one free plugin, licensed GPLv2 or later. There is no Pro version and nothing is unlocked by paying. If it saves you time, you can support its maintenance through [GitHub Sponsors](https://github.com/sponsors/miroslav-balan-at).

= How does an assistant connect? =

Add the endpoint URL (`https://yourstore.com/mcp`) to your MCP client — no token needed. The client discovers the OAuth authorization server, opens your browser to a consent screen, and you approve what it may do. Approved apps appear under Connect apps › Connected apps.

= The endpoint or discovery documents return 404 or 403 =

Two causes: (1) pretty permalinks — the endpoint is also available at `/wp-json/counterhand/v1/mcp`, and re-saving Settings → Permalinks refreshes rewrite rules; (2) some servers block `/.well-known/` paths. OAuth discovery lives at `/.well-known/oauth-protected-resource`, so your server must allow that path. On nginx, add before any dotfile-deny rule:

`location ^~ /.well-known/ { try_files $uri $uri/ /index.php?$args; }`

= Which AI clients work? =

Any MCP client implementing the current authorization spec (OAuth 2.1 + PKCE + Protected Resource Metadata, with CIMD client identity): Claude on web, mobile and desktop, ChatGPT, Claude Code, Cursor, VS Code, and others.

= Claude or ChatGPT cannot see my store =

Those apps connect from the vendor's own servers, not from your browser, so your store has to be reachable from the public internet over HTTPS. A local development site works fine with Claude Code, Cursor and VS Code, which connect from your own machine. The Connect apps screen checks this for you and says which of the two situations you are in.

= An assistant says a tool does not exist =

Three things have to agree before a tool is callable: its area is switched on under Access, the app was granted it on the consent screen, and WooCommerce's own permission check passes for the logged-in owner of the connection. If any one of them says no, the tool is invisible *and* uncallable — it is never merely hidden. Check Access first, then reconnect the app: a connection only ever holds what it was approved with.

= Can an assistant break my store? =

The things that could are gated separately. Store settings, payment gateways and system maintenance are their own areas, off by default, sitting behind a folded Advanced heading that is never pre-ticked; each of their write tools requires an explicit confirmation; and the maintenance routines that cannot be undone are refused whatever an assistant sends. Calls to those three areas are recorded in Activity even if you have recording switched off.

= Can I connect a client that only supports bearer headers? =

The security model is OAuth-first. If you need a raw bearer token for a script or CI job, use the `counterhand_rate_limit` and related filters documented in the plugin, or open an issue on GitHub — a developer token path may be added.

= Does it work with other plugins, through WordPress abilities? =

On WordPress 6.9 or newer, abilities that other plugins offer to AI clients, such as Yoast SEO's post SEO data and WPForms' forms, appear as tools under Access › Other plugins. Each plugin is off until you switch it on, has its own read and write permission on the consent screen, and still runs its own permission check. Every write needs explicit confirmation, and every call is recorded in Activity. A connection holds only the tools listed when you approved it: anything a plugin adds in a later update stays off until you connect the app again. WooCommerce's and WordPress's own abilities are not bridged, because the tools above already cover them under their own permissions.

= Does it work with Contact Form 7 and Flamingo? =

With Contact Form 7 active, an assistant can list, create, edit and delete its forms. With Flamingo active, it can read the form submissions Flamingo stores and move them to the trash. Each is off until you switch it on under Access › Other plugins, and has its own permissions on the consent screen. Flamingo's messages hold personal data, so grant them only to an app you trust with it. Editing or deleting a form, and removing a message, need explicit confirmation.

= Does it work with Advanced Custom Fields? =

With ACF active, an assistant can read and change custom field values on posts, pages and products, for field groups with "Show in REST API" switched on. Password fields are never returned or set. It is off until you switch it on under Access › Other plugins, and it cannot change anything else about those items.

= Does it work with Germanized? =

With Germanized active, an assistant can manage delivery times, units, price labels, deposit types, manufacturers, nutrients and allergens, and set these legal details on products, for example to find products missing a delivery time. It is off until you switch it on under Access › Other plugins. It cannot change a product's price, status or tax status through these tools, though a deposit set on a product is added to what customers pay. Bank details are never included. Changes to products and deposit types, and every deletion, need explicit confirmation.

= Does it work with WPML? =

With WPML and WooCommerce Multilingual active, an assistant can find products, categories, tags and attribute terms missing a translation, add translations and change their text, including variations, custom text fields and strings such as email subjects. It is off until you switch it on under Access › Other plugins. It cannot change an original, or any price or stock: those come from the original. A new product translation starts as a draft, and publishing it needs explicit confirmation. Category, tag and attribute translations are visible as soon as they are saved. Setting a string needs explicit confirmation, and payment settings are out of reach.

Have a person check any translated legal wording, such as withdrawal or safety text. Whatever you switch on, a product changed through any tool now updates its translations too, as it does in the WooCommerce REST API. With Multilingual's multi-currency mode on, the product tools can also read and set a product's own price in each currency.

= Which other paid plugins does it work with? =

Each of these is off until you switch it on under Access › Other plugins.

* WooCommerce Shipment Tracking: read, add and remove tracking numbers on orders. Changes need explicit confirmation.
* Germanized Pro: look up issued invoices. Read-only, with no addresses or files.
* WooCommerce Cost of Goods: read and set what products cost, and see what an order earned. Profit is left out when it cannot be right, such as for an order in another currency.
* WooCommerce URL Coupons: list coupon links, and give a coupon a link. Changes need explicit confirmation, and anything that is the start of an address the store already uses is refused, because a link captures every address starting with it.

= Can an assistant preview a change, or undo one? =

An assistant can ask what a change to a product, variation, category, coupon, shipping or tax setting, post or page would do before saving it. With "Undo history" switched on under Access and granted to an app, it can also undo that connection's own changes for 30 days. An undo needs explicit confirmation, checks the result, and restores the record, not what followed from it: emails already sent stay sent, orders already placed keep their prices, and a price customers saw may still count as your lowest in the last 30 days. It is refused if the fields have changed again, or if something created has since been edited or sold. Orders, refunds, customers, reviews, payment gateways, store settings, custom fields and other plugins' tools are not covered, a deletion cannot be undone, and some changes, such as a shipping method's settings, say they have no undo.

= Are there ready-made routines? =

Clients that support MCP prompts can offer these as slash commands: sales review, restock list, order triage, refund review, catalog check and coupon review, plus missing translations with WPML and a legal details check with Germanized. Each appears only if the connection can reach every tool it needs, and each proposes rather than changes anything. Clients can also read a short store profile, so an assistant knows the currency, tax setup and what it can reach before it starts.

= Where do I report a bug or a security issue? =

Bugs and feature requests: the [GitHub issue tracker](https://github.com/miroslav-balan-at/counterhand-mcp-for-woocommerce/issues) or the support forum here. Security issues: please report them privately as described in the repository's SECURITY.md rather than in a public thread.

= Does it work in a subfolder or on a multisite network? =

Yes. Each site of a network has its own connections, its own switch and its own tables, and starts switched off. In a subfolder, or on a subsite, assistants look for `/your-folder/.well-known/...`, so a web server that blocks every path starting with a dot must let `.well-known` through at that level too.

== Screenshots ==

1. You decide what each AI app may do: Claude, ChatGPT or Cursor connect through a consent screen in your browser.
2. Changes wait for you: the chat shows a price change before and after, and runs it only when you approve.
3. Ask your store in plain words, right in wp-admin, on the AI model you choose.
4. Rewrites: the AI model drafts new product texts for a category, and you approve each one.
5. The weekly report: last week's sales by email every Monday, with a short summary written by AI on top.
6. Access: every store area is its own switch, with Can read and Can change separate.
7. Activity: every call apps and the chat made, with personal data masked before it is stored.
8. Connect apps: one address for Claude, ChatGPT, Cursor and VS Code.

== Changelog ==

= 2.0.0 =
* New: a menu that follows what you do: Overview, Chat, Rewrites, Connect apps, Access and Activity. Overview opens first and lists what is still to set up. Settings and Other plugins are now one screen, Access. Old bookmarks and links in emails keep working.
* New: Rewrites. The chat's AI model drafts new product descriptions for up to 25 products in a category, and nothing changes until you approve each text.
* New: a weekly report by email, which each person asks for on Overview, with an optional summary written by AI on top of WooCommerce's own figures.
* New: the chat offers ready-made routines, saved questions and store notes, keeps a conversation across a reload, and shows a price change before and after you approve it.
* New: the consent screen asks "Let this app work with your store?", names who approves, the app and where access goes, and folds each section.
* New: integrations for WPML, Germanized and Germanized Pro, WooCommerce Shipment Tracking, Cost of Goods, URL Coupons, Advanced Custom Fields, Contact Form 7, Flamingo, BackWPup, and the abilities other plugins such as Yoast SEO, Rank Math and WPForms offer to AI clients. All are off until you switch them on.
* New: an assistant can preview a change before saving it, and with "Undo history" switched on and granted, undo an app's own changes for 30 days.
* New: views for hosts that support MCP Apps, MCP prompts, a store profile resource and support for the MCP 2026-07-28 protocol revision.
* Changed: one name for each thing on every screen: an area with Can read and Can change, an app, AI access, Activity.
* Changed: tools can no longer write protected custom fields (names starting with an underscore) through `meta_data`. A client that set them that way now gets an error.
* Fixed: on a store with WPML, a product changed through any tool now updates its translations.
* Fixed: a browser could fill a saved site login into the chat's API key field.
* Security: the chat's AI model and its key are for administrators only, a model cannot approve its own change in apps that show a form, and ACF, tax and Germanized writes are checked more strictly.
* Tested with WooCommerce 11.3.

= 1.3.2 =
* Added: `create_order` — creates an unpaid order and returns its payment link, so an agent can take an order over email or chat and send the customer somewhere to pay. Always pending; WooCommerce sends no email of its own for it.

Older releases are listed in CHANGELOG.md in the plugin's GitHub repository.

== Upgrade Notice ==

= 2.0.0 =
A new menu by task (Overview, Chat, Rewrites, Connect apps, Access, Activity); old links keep working. Tools no longer write protected custom fields through meta_data. New integrations ship off.
