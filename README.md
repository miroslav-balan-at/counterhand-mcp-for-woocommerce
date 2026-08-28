# Counterhand MCP for WooCommerce

Turn a WooCommerce store into a secure MCP server, so Claude, ChatGPT, Cursor,
VS Code and Claude Code can read and manage products, orders, customers and
reports — behind OAuth 2.1 browser consent, per-connection scopes and an audit
log. Free, GPL, self-hosted.

[![CI](https://github.com/miroslav-balan-at/counterhand-mcp-for-woocommerce/actions/workflows/ci.yml/badge.svg)](https://github.com/miroslav-balan-at/counterhand-mcp-for-woocommerce/actions/workflows/ci.yml)
[![WordPress.org](https://img.shields.io/wordpress/plugin/v/counterhand-mcp-for-woocommerce?label=wordpress.org)](https://wordpress.org/plugins/counterhand-mcp-for-woocommerce/)
[![Sponsor](https://img.shields.io/badge/sponsor-GitHub%20Sponsors-ea4aaa)](https://github.com/sponsors/miroslav-balan-at)

Website: <https://counterhand.app> · Plugin directory: <https://wordpress.org/plugins/counterhand-mcp-for-woocommerce/>

## Install

From wp-admin: Plugins → Add New → search "Counterhand MCP" → Install → Activate.
Or with WP-CLI:

```sh
wp plugin install counterhand-mcp-for-woocommerce --activate
```

WooCommerce ≥ 8.0, WordPress ≥ 6.5, PHP ≥ 8.2. For cloud assistants (Claude,
ChatGPT) the store must be reachable over HTTPS from the internet; Cursor, VS
Code and Claude Code connect from your machine and work with a local site.

## What you get

- **An MCP endpoint at `/mcp`** with OAuth 2.1 + PKCE and CIMD client identity —
  no tokens to copy, no client secrets, no manual registration.
- **Consent per connection.** The assistant asks for scopes; you narrow them on a
  consent screen in your browser. Write never implies read. One click revokes.
- **127 tools in 16 switchable groups** — products, orders, customers, coupons,
  reviews, variations, refunds, reports, shipping, tax, posts and pages, plus
  store settings, payment gateways and maintenance behind an Advanced heading
  that is off by default. A fresh install exposes Products, Orders and Reports,
  read-only.
- **Schemas derived from WooCommerce at runtime**, not copied: tools delegate to
  WooCommerce's own REST controllers and run its permission callbacks, so a
  shop manager and an administrator see different tools without this plugin
  keeping a capability list.
- **Confirmation-gated risky writes**, credential fields that are never
  writable, irreversible maintenance routines refused outright, custom-field
  keys for roles and sessions blocked in both directions.
- **An in-admin chat** that uses the same tools — through WordPress 7.0's AI
  connectors, or with your own Anthropic / OpenAI / Gemini / Ollama key.
- **An opt-in action log** with PII masked before storage.

## How it differs from WooCommerce's built-in MCP

WooCommerce ≥ 10.3 ships an experimental, feature-flagged MCP server exposing a
handful of product and order abilities, authenticated with REST API keys.
Counterhand fills what a self-hosted store still lacks: OAuth 2.1 consent
instead of copied keys, the whole wc/v3 and wp/v2 surface, scoped and revocable
connections, confirmation gates, an audit log and the chat. Both can run side by
side; Counterhand registers nothing under the reserved `woocommerce/` ability
prefix.

## Free and sponsor-supported

There is no Pro version and nothing is unlocked by paying. If Counterhand saves
you or your clients time, [sponsoring it on GitHub](https://github.com/sponsors/miroslav-balan-at)
is what funds updates for new WooCommerce releases, new MCP clients and support.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for the development setup, the coding
rules and how tools are declared. Security issues: [SECURITY.md](SECURITY.md).

## Licence

GPL-2.0-or-later. See [license.txt](license.txt).
