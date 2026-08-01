# Counterhand MCP for WooCommerce — coding rules

## Architecture
- **DDD feature modules**: each feature under `src/Features/<Name>/` owns its views, admin glue and services. Domain objects live in `<Feature>/Domain/`. Cross-feature dependencies go through interfaces (e.g. `TokenRepositoryInterface`), never concrete classes of another feature.
- **Placement rule — the owning feature publishes its contract.** `Shared/` is only for things with no natural owner (`FeatureInterface`, `JsonRpc/`, `Exception/`, `Tool/`). An interface belongs next to the feature that defines it, even when six others import it: `ApiScope` and `TokenRepositoryInterface` live in `Features/Tokens/Domain/`, `ProviderInterface` in `Features/Playground/Provider/`, `RestGatewayInterface` in `Features/WooCommerceTools/Infrastructure/`. Before adding to `Shared/`, count the consumers — **one consumer means it belongs in the slice**. `src/Shared/Tool/` is three contract files and must stay that way; nothing WooCommerce-specific goes in `src/Shared/`.
- **SOLID**: no `instanceof` branching on concrete types in feature/application code — put the varying behaviour on the interface. One responsibility per class; extract a service when a feature class grows a second workflow.
- **Value objects over arrays**: structured data crossing a boundary (return values, transients, JSON payloads, view data) is a `final readonly` VO or a backed enum, not an `array{...}` shape. `to_array()` only at the serialization edge (`wp_send_json_*`, transients).
- **DRY**: compute once, pass down — views receive data, they don't re-fetch it.

## Protocol boundaries
- **The MCP wire format belongs to one slice.** Only `Features/McpServer` may build or parse JSON-RPC envelopes and MCP result shapes (`content`, `isError`, `structuredContent`, error codes). Business logic elsewhere consumes `ToolInterface` / `ToolRegistry` directly — the way `AgentLoop::tool_definitions()` already does — never the wire format.
- **In-process tool calls need a seam, not an envelope.** `AgentLoop::dispatch()` hand-builds a `tools/call` envelope and reverse-engineers the result shape; this is known debt, not a pattern to copy. The fix shape: McpServer publishes a transport-neutral dispatch contract (defaults → validation → scope gate → execute → `ctrh_tool_called`) that both the JSON-RPC handler and Playground consume. Any new in-process caller waits for that seam rather than adding a third envelope-builder.
- **One tool pipeline.** Schema-default application, argument validation, scope enforcement and the audit hook run in exactly one place. If a caller can reach `ToolInterface::execute()` without passing through that pipeline, that is a bug, not a shortcut.
- **The `/mcp` path is declared once.** It currently appears in four places (`McpServerFeature`, `CanonicalUri`, `ConnectReadiness`, `SettingsFeature`); do not add a fifth — route any new consumer through `CanonicalUri`.
- **Features expose UI to Settings through a contract.** `render_tab()` is an informal convention with no interface behind it, and `SettingsFeature` already injects three concrete siblings for it. Don't add a fourth concrete — introduce/extend a tab contract instead.

## Tool-surface size
- **Never cap the surface when the provider can defer it.** Selection accuracy falls off past roughly 30–50 *eagerly loaded* tools — that is the real constraint, not any wire limit. Anthropic's answer is the tool search tool: send every definition, mark the tail `defer_loading: true`, and Claude searches names/descriptions/argument names on demand. Deferred definitions stay out of the system-prompt prefix, so they cost nothing until surfaced and the prompt cache survives. Keep 3–5 tools eager (the API rejects a request with everything deferred) and never defer the search tool itself.
- **The ceiling is provider knowledge, not loop knowledge.** `ProviderInterface::max_eager_tools()` returns null when the provider can search a catalogue and a number when it cannot; `with_tool_search()` decides how a catalogue is spelled. `AgentLoop` asks — it must never hardcode a limit or branch on a provider. OpenAI and Gemini both hard-reject more than 128 tools and have no deferred-loading equivalent, so their adapters return a real number.
- **A refusal is the last resort, and must say what to do.** Only a provider with no deferred loading refuses, and the message names the count, the ceiling, how many to untick, and that an Anthropic model removes the limit.

## The official WordPress MCP stack (state as of July 2026)
Know the landscape before building or duplicating anything:
- **Abilities API is WordPress core since 6.9** (`wp_register_ability` on `wp_abilities_api_init`, JSON-schema in/out, `permission_callback`). **WooCommerce ≥10.3 ships its own experimental MCP server** (feature-flagged, developer preview): as of 10.9 it exposes 7 hand-authored product/order abilities via the bundled `wordpress/mcp-adapter`, authenticated with REST API keys / application passwords.
- **This plugin's reason to exist is the gaps**: OAuth 2.1 for self-hosted HTTP transport (core has none — WordPress.com only), breadth beyond products/orders (coupons, customers, reports, settings, shipping, tax, wp/v2), scoped tokens with per-client consent, confirmation-gated risky writes, and the chat playground. Don't reimplement what core now does well; don't drop what core still lacks.
- **Runtime derivation from wc/v3 is validated, keep it.** WooCommerce's own 10.3 `AbilitiesRestBridge` used the same pattern (derive schema + permissions from live REST controllers). Core later hand-authored its 7 canonical abilities for its *public contract* — that is curation, not a repudiation of derivation; our `FieldProfile` pruning plays the same role.
- **Never register anything under the `woocommerce/` ability prefix** — reserved for core (10.9 announcement). If we ever bridge our tools into the Abilities API for interop with the official adapter, use our own prefix and treat it as a thin adapter over `ToolInterface`, not a second tool implementation.
- **Do not vendor `wordpress/mcp-adapter`.** It is pre-1.0 (breaking changes at 0.3.0), WP ≥6.9 only, and recommends Jetpack Autoloader — all three collide with the zero-runtime-dependency rule. The in-house `Shared/JsonRpc` layer stays.

## Adding a tool
Tools are **declared, not written**. `GeneratedTool` serves the whole wc/v3 and wp/v2 surface; a new resource is a `DescriptorProvider` under `Features/WooCommerceTools/Descriptors/` plus one line in `StaticDescriptorCatalog::shipped()`.

- **Never restate a schema.** A `FieldProfile` carries *field names only* — types, enums, defaults and descriptions are read off the live route at runtime. A profile that also carried types would be a second copy of WooCommerce's schema, and copies rot. Verify names against the checked-in fixtures (`WcRouteArgsFixtureTest` fails on a name WooCommerce does not declare) and against a real store, not against memory.
- **Prune hard, but only because there is an escape hatch.** `describe_woocommerce_fields` returns any tool's full derived schema, and writes set `additionalProperties: true` so a revealed field can be sent straight back. Curate to the ten or so fields that matter; do not widen a profile just because a field exists.
- **Never hardcode a capability.** Visibility comes from `RoutePermissionProbe` running WooCommerce's own `permission_callback`. If the `WordPress.WP.Capabilities` sniff fires on a new literal, something was reimplemented — `phpcs.xml.dist` must keep listing only `manage_woocommerce`.
- **Probe the collection, not the item.** `map_meta_cap()` denies an id-less item capability, so probing `/products/{id}` hides the tool from administrators too. Use `read_probe`/`write_probe` only where the collection genuinely cannot answer — and say why in a comment, because the override looks wrong without one.
- **A new group ships disabled** (`enabled_by_default()` false, no `in_chat_by_default()`) and its write axis needs its own `ApiScope` case. Omitting the `:write` case is how a group is kept read-only; the factory refuses to build a write with no scope to gate it.
- **Risky writes** declare `requires_confirmation` and, where the danger is in the arguments rather than the route, an `ArgumentPolicy`. Put the rule on the interface, never as a branch inside `GeneratedTool`.
- Update `ShippedSurfaceTest::SHIPPED` deliberately, by reading the catalogue — never by pasting what the failure printed.

## Verifying against a real store
Unit tests run without WordPress, so they cannot tell you whether a field name is real, whether a route exists, or whether a probe answers true. Every bug found in the tool expansion was found this way and could not have been found any other way. The store, how to drive it, and the findings so far are in memory (`local-woocommerce-store`, `wc-schema-derivation-verified`, `wc-meta-security-findings`, `wc-risky-write-gating`). Run `wp eval-file` scripts that build the real tool set and check: every profile name is a declared route arg, every tool is available to an administrator, and a sample of each group dispatches.

## Naming
- **Full words, never abbreviations.** `counterhand_freemius()`, not `ctrh_fs()`; `$licence`, not `$lic`. The `ctrh_` / `CTRH_` prefix on hooks, options, CSS classes and constants is the one exception — it is a namespace claim WordPress forces on us, not a shortened word.

## PHP
- **PHP 8.2+**, not 8.1: `final readonly class` is an 8.2 feature (readonly *properties* are 8.1) and 86 files use it. `composer.json`, `phpcs.xml.dist` (`testVersion 8.2-`) and `readme.txt` all agree; keep them that way, because WordPress.org enforces the readme value and a mismatch blocks installs on stores that would otherwise be fine.
- Use: `final readonly` classes, constructor promotion, backed enums, `match`, named arguments, `never` return type where applicable.
- **Avoid `else` — happy path last.** Guard clauses return/throw/redirect early; the main flow reads top-to-bottom unindented. (`if/else` in templates for markup branching is fine.)
- Zero runtime Composer dependencies. Never vendor a library that could collide with core or other plugins (`src/Autoloader.php` explains why). WordPress-bundled libraries (e.g. the WP 7.0 AI Client) are used behind a capability check and a stub file for PHPStan.
- WPCS via `phpcs.xml.dist` (WordPress-Extra, Yoda conditions, i18n text domain `counterhand-mcp-for-woocommerce`). PHPStan level 6 with stubs in `tests/phpstan-*.php`.

## Comments
- Only when needed, and short — one line unless the *why* genuinely needs more.
- Comments explain why, never what. No restating the code, no section banners in PHP.
- **Never comment the obvious.** If the line already says it, the comment is noise — a `// stdClass so it encodes as {}` beside `new \stdClass()` tells the reader nothing they cannot see. Write only what the code cannot say: the consequence, the constraint, the thing that breaks otherwise.
- **Say it once, where the decision lives.** The same reason repeated at every call site and test that touches it is worse than no comment — it goes stale in five places instead of one.

## UI
- Design tokens from `assets/shared/tokens.css` (mapped to `--wpds-*` with fallbacks). No raw hex in admin/OAuth CSS, never hardcode WooCommerce purple `#7f54b3`.
- WooCommerce settings-screen rules: 720px measure, white cards, one idea per card. Chat is deliberately wider — it is not a settings screen.
- No JS build step; hand-written ES5-flavoured IIFEs. No remote assets (WordPress.org rule).
- Escape-then-format for any model output rendered as HTML (`chat.js formatAnswer`).

## Verification
- `composer run lint` (phpcs), `composer run analyse` (phpstan, needs `--memory-limit=1G`), `composer run test` (phpunit) — all green before claiming done.
