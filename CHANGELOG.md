# Changelog

The two most recent releases are also in `readme.txt`; everything older lives here.

## 2.0.0

* Changed: tested with WooCommerce 11.3.
* Changed: the privacy policy text WordPress suggests now names Rewrites and the weekly report's AI summary, and the readme's external services section describes what each sends.
* Changed: the consent screen asks "Let Claude work with your store?" and puts who approves, the app and where access goes in one card, with only the app's address marked as verified, because its name is its own claim. Each section (Catalog, Sales, …) slides open and closed: one holding something to grant starts open, one switched off for the store starts closed and says so, and Advanced and Other plugins stay closed and unticked. Write permissions read "Change products" rather than "Manage products".
* Changed: the chat's first screen is "Choose an AI model" and says what else uses the model: Rewrites and the weekly summary.
* Changed: the menu follows what you do, in this order: Overview, Chat, Rewrites, Connect apps, Access and Activity. Overview opens first and lists what is still to set up. Settings and Other plugins are now one screen, Access, with two forms that each save only their own part; the old Other plugins address leads there. Other plugins also names the supported plugins that are not active yet, and the store areas table is striped, with each area's description on its own line. The Dashboard is called Overview and the action log Activity. Bookmarks and links in weekly report emails keep working.
* Changed: one name for each thing across every screen: an area (was tool group), Can read and Can change (was Read and Write), an app (was connection or client), AI access (was the connector). Pointers to tabs that no longer exist now name the screen.
* Changed: every Counterhand screen opens with one slim header: the mark, the screen's title and what it is for, whether AI access is on, and links to every Counterhand screen. Everything below it keeps WordPress's own look and your admin colour scheme.
* Fixed: on Bulk rewrites, applying a text while Write for Products was off failed it for good, so only a new job could bring it back. Apply now refuses before touching any text, says why, and is greyed out until writes are on again.
* Fixed: a rewrite for a product deleted since showed an "Edit product" link that went nowhere, and applying it failed with WooCommerce's raw error. The row now says the product no longer exists.
* Fixed: saving Settings or Other plugins now says "Settings saved.". Both screens post to WordPress's options handler, whose notice WordPress prints only on pages under Settings. Notices from WordPress and other plugins now sit below each screen's header instead of squeezed in beside its title.
* Added: BackWPup is named and described on its own. Its backup logs are not offered, because they name the database server, folder paths and where backups are stored. BackWPup marks none of its abilities as read-only, so each asks first.
* Fixed: a browser could fill a saved site login into the chat's model and API key fields, and connecting would then send that password to the AI provider as a key. Both fields now tell browsers they are not a login.
* Fixed: active plugins on Other plugins, the Dashboard and the consent screen are named as their own header names them, such as "WP Mail SMTP" rather than "Wp Mail Smtp".
* Changed: the server name suggested to Cursor and VS Code comes from the store's address, such as `hygienemitsystem-at`, rather than its title cut to 32 characters. A store added before keeps its old entry: adding it again creates a second one, so remove the old entry in the client and revoke its connection under Connected apps.
* Fixed: Connected apps listed a connection past its refresh window as active, with a Revoke button, until it was next used. It now reads as expired, matching the Dashboard's count.
* Fixed: on a store with WPML and Solid Security, creating a translation reported a failure although it was saved. The tool now answers with the translation.
* Changed: changing a category, tag or attribute term translation now takes its language as `lang`. Without it, WPML compared the name against the original and refused the change.
* Fixed: a report or background job that acts for several people in one run no longer reuses the first person's view of what they may reach.
* Fixed: undo history read before anyone opened the admin after an update no longer logs a database error.
* New: the chat offers the same playbooks MCP clients get as prompts, in place of four fixed questions, and each person can save their own questions.
* New: store notes, written by an administrator, that every chat on the store is told.
* New: a conversation survives reloading the page in the same tab, with a running count of its tokens.
* New: approving a price change in the chat shows the price now and after, from the tool's own preview.
* Fixed: changing the chat's model no longer resets the areas chosen for it.
* New: a weekly report by email, which each person asks for on the Dashboard. It has last week's sales against the week before, the best sellers and the orders waiting, all as WooCommerce's reports state them. With a model connected, a short summary written by AI comes first, labelled as such. It is sent only while Reports are switched on.
* New: Bulk rewrites. The model connected to the chat drafts new descriptions or short descriptions for up to 25 products in a category, in the background. Nothing changes until you approve a text, a text edited since the model read it is not overwritten, and with undo history on, applied texts can be undone.
* New: hosts that support MCP Apps, such as Claude, can show the sales report, an order and a product change preview as a view in the conversation. Views only display: changes still go through the usual confirmation. Other clients get the same text as before.
* New: a Dashboard says whether the connector is on, how many apps are connected, which areas can be changed, and what happened today. With the action log off it says calls are not recorded rather than showing a zero.
* New: tools from other plugins have their own screen, with one card per plugin. Existing choices carry over, and saving either screen leaves the other's alone.
* Fixed: screens now follow the admin colour scheme. Almost none of the WordPress design token names we read existed, so accents stayed one blue on every scheme.
* Changed: shop managers now see Settings and Other plugins as read-only, with a note saying why. Saving was already limited to administrators and used to end on an error page.
* Changed: the Connect screen's two views use a plain link navigation in place of WordPress's older tab bar.
* Fixed: on a store with WPML, tool calls missed WooCommerce Multilingual's REST hooks. A product changed through any tool now updates its translations, and a translation sent with a language is saved in that language and linked.
* Changed: tools can no longer write protected custom fields through `meta_data`. A client setting underscore keys that way, such as Yoast fields through `update_product`, now gets an error.
* Added: integrations for WPML, WooCommerce Shipment Tracking, Germanized Pro invoices, WooCommerce Cost of Goods, WooCommerce URL Coupons, Germanized, Advanced Custom Fields, Contact Form 7 and Flamingo, and the abilities other plugins offer to AI clients. All ship off, each with its own grant.
* Added: `preview` on catalog, content, shipping and tax writes, and `undo_change` and `get_recent_changes` behind a new "Undo history" grant.
* Added: support for the MCP 2026-07-28 protocol revision alongside the earlier one.
* Added: an Agent Skill, `counterhand-woocommerce`, under `skills/`, separate from the plugin.
* Changed: WordPress 6.6 is now required. The filter that loads the bundled translations arrived in that version.
* Changed: text a model reads stays in English whatever the site's language, and the chat is told to answer in the language the person writes in.
* Added: JSON-RPC batches for clients on the 2025-03-26 revision, up to 20 requests, each counted against the rate limit.
* Changed: an authorization request that cannot go ahead now returns its error to the client when the redirect address checks out, instead of stopping on a page.
* Changed: a client's metadata document is cached as its `Cache-Control` says, between one minute and one hour.
* Added: chat with Claude uses prompt caching and keeps the most common tools loaded, resumes a paused turn, and says when the model declines.
* Added: MCP prompts for common store routines, a `counterhand://store/profile` resource, and envelope output schemas on read tools.

## 1.3.2
* Added: `create_order` — creates an unpaid order and returns its payment link, so an agent can take an order over email or chat and send the customer somewhere to pay. Always pending; WooCommerce sends no email of its own for it.

## 1.3.1
* Fixed: the OAuth discovery documents are now served from the request path, so another plugin registering the same `/.well-known/` rewrite can no longer answer for this one and break the connection.

## 1.3.0
* Changes that need approval are now approved by you, not the model: the Chat tab pauses before a risky change, shows exactly what the assistant wants to run, and only your Approve button lets it happen. Nothing in that turn runs until you decide.
* AI apps that declare refresh support (Claude Code, for example) receive one-hour access tokens and a rotating refresh token, so a leaked token is useful for an hour rather than a month. A refresh token replayed after it was retired revokes the connection. Apps without refresh support keep the 30-day token they had, so nothing changes for them.
* An app's identity document URL must now carry a path, as the Client ID Metadata Document specification requires.
* The OAuth consent pages refuse to be shown inside another site's frame, so a store administrator cannot be tricked into approving a connection they did not open.
* Authorization responses now carry the issuer (RFC 9207) and the discovery document says so, which lets AI apps detect a mix-up between authorization servers.
* The connecting app's identity document is read with a size limit.
* Deleting the plugin also removes the Chat tab's saved provider key.
* Speaks MCP protocol revision 2025-11-25 alongside the earlier ones.
* Tested with WooCommerce 11.1.

## 1.2.2
* The connecting app's identity document is fetched through WordPress's safe HTTP function, which refuses private-network and loopback addresses.
* Uninstall no longer runs a raw query against the options table; the plugin's short-lived transients expire on their own and WordPress's daily cleanup removes them.
* The readme discloses that the Chat tab can install the official AI Provider plugins from WordPress.org on request.
* On WordPress 7.0 and later the Chat tab no longer offers its own field for a WordPress connector key. It now reports which providers WordPress has and whether their key works, asked of the WordPress AI Client, and links to Settings → Connectors for entering the key — so the plugin never reads or writes a connector's stored API key.

## 1.2.1
* The OAuth consent pages load their stylesheets through WordPress's own style queue instead of writing `<link>` tags, and print only this plugin's own two sheets so nothing else can inject assets into a consent screen.
* The readme now documents every external service the plugin can contact, what is sent to each and when.

## 1.2.0
* First release in the wordpress.org plugin directory. Updates and language packs now come from WordPress itself.
* Deleting the plugin removes its tables, options and transients through a standard `uninstall.php`.

## 1.1.1
* German translation (de_DE) for the whole plugin, and the bundled language files now load.

## 1.1.0
* The action log is paginated: 25 calls per page with WordPress's own pagination, instead of stopping silently at the newest 100. The tab also shows the total number of recorded calls.
* The plugin's admin screens carry the product's mark, and the admin menu icon is the brand glyph in the standard monochrome form WordPress recolours to your admin scheme.
* The log table is easier to read: monospace timestamps and tool names, clearer success and failure states, and an empty state that says how to start recording.

## 1.0.0
* 127 tools across 16 switchable groups, covering the catalog, sales, content, store setup and — behind an Advanced heading — configuration and maintenance. Every new group ships disabled: upgrading exposes exactly what it exposed before.
* Tool input schemas are now read from WooCommerce's own REST controllers at runtime instead of being restated in this plugin, so they track WooCommerce across updates and cannot advertise a field that no longer exists. Each tool publishes a curated subset; `describe_woocommerce_fields` reveals the rest on demand.
* Tool visibility now runs WooCommerce's own permission check for each endpoint, so a shop manager and an administrator see different tools without this plugin keeping a capability list.
* Store settings, payment gateway and system maintenance writes require an explicit confirmation; settings named like credentials, payment gateway credentials, and maintenance routines that cannot be undone are refused outright. Calls to those groups are always recorded in the action log.
* Custom field tools for products, orders, coupons and customers, with WordPress's private fields hidden and the keys holding roles, capabilities and login sessions blocked in both directions. Customer custom fields are read-only.
* Consent screen and settings tab are grouped under plain headings, with Advanced collapsed and never pre-ticked. A client that requests no scopes is now offered a conservative read-only default rather than everything.

## 0.1.0
* Initial release: OAuth 2.1 + CIMD browser-consent authorization, MCP server endpoint, 14 WooCommerce tools, per-connection scoped access with audience binding, Connections management with revoke, opt-in action log with PII masking.
* In-admin chat with support for the WordPress 7.0 AI Client, so model credentials are managed by WordPress rather than this plugin.
* One-click install links for Cursor and VS Code, and an automatic readiness check that reports whether cloud assistants can reach the store.

