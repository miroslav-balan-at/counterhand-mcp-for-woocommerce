# Changelog

The two most recent releases are also in `readme.txt`; everything older lives here.

## Unreleased

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

