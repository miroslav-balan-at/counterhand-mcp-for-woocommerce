# Changelog

The two most recent releases are also in `readme.txt`; everything older lives here.

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

