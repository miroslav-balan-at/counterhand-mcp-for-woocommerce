# Contributing

Thanks for looking. Bug reports, tool requests and pull requests are all
welcome; this file is what you need to get a change from idea to merged.

## Setup

```sh
git clone https://github.com/miroslav-balan-at/counterhand-mcp-for-woocommerce.git
cd counterhand-mcp-for-woocommerce
composer install
```

The plugin has **zero runtime Composer dependencies** — everything under
`vendor/` is development tooling. Symlink or copy the checkout into a local
WordPress + WooCommerce install's `wp-content/plugins/` to run it.

## Quality gates

All three must be green before a pull request is reviewed:

```sh
composer run lint                          # phpcs, WordPress-Extra + Security + PHPCompatibilityWP
composer run analyse -- --memory-limit=1G  # phpstan level 6
composer run test                          # phpunit, no WordPress needed
```

CI runs the same on PHP 8.2, 8.3 and 8.4.

## Verifying against a real store

Unit tests run without WordPress, so they cannot tell you whether a field name
exists, whether a route is registered, or whether a permission probe answers
true. Any change to a tool descriptor must be checked on a real store with
WP-CLI: build the tool set, confirm every profile field is a declared route
argument, confirm every tool is visible to an administrator, and dispatch a
sample call from each group. `tests/Fixtures` holds checked-in copies of
WooCommerce's route args so a wrong name fails in CI too.

## How the code is organised

- `src/Features/<Name>/` — one feature slice each (McpServer, OAuth, Tokens,
  Settings, Playground, ActionLog, WooCommerceTools). A slice owns its views,
  admin glue and services; cross-slice dependencies go through interfaces the
  owning slice publishes.
- `src/Shared/` — only things with no natural owner (`FeatureInterface`,
  `JsonRpc/`, `Exception/`, `Tool/`). One consumer means it belongs in the slice.
- Tools are **declared, not written**: a new WooCommerce resource is a
  `DescriptorProvider` under `Features/WooCommerceTools/Descriptors/` plus one
  line in `StaticDescriptorCatalog::shipped()`. Field profiles carry names only;
  types and descriptions come from the live REST schema.
- Only `Features/McpServer` may build or parse JSON-RPC and MCP result shapes.

`CLAUDE.md` in the repository root is the full set of coding rules — it is
written for AI coding assistants but every rule applies to humans too.

## Rules that get pull requests sent back

- **Full words, never abbreviations**, in every kind of name: PHP, hooks,
  options, CSS classes, JS globals. The prefix is `counterhand_`, spelled out.
- PHP 8.2+: `final readonly` classes, constructor promotion, backed enums,
  `match`. Guard clauses first, happy path last, avoid `else`.
- Value objects and enums over array shapes at any boundary.
- No hardcoded capabilities — visibility comes from WooCommerce's own
  `permission_callback`. `phpcs.xml.dist` allows only `manage_woocommerce`.
- New tool groups ship disabled and need their own `ApiScope` case.
- Comments say why, never what, and only when the code cannot say it.
- No remote assets, no telemetry, no vendored libraries.

## Translations

Strings use the `counterhand-mcp-for-woocommerce` text domain. After changing
strings: `wp i18n make-pot . languages/counterhand-mcp-for-woocommerce.pot`,
then `wp i18n update-po` and `wp i18n make-mo` for `de_DE`. Once the plugin is
on wordpress.org, new languages go through translate.wordpress.org.

## Releasing

Maintainers only — see `RELEASE-CHECKLIST.md`.
