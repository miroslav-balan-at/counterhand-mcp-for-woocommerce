# AgentGate MCP for WooCommerce — coding rules

## Architecture
- **DDD feature modules**: each feature under `src/Features/<Name>/` owns its views, admin glue and services. Domain objects live in `<Feature>/Domain/`. Cross-feature dependencies go through interfaces (e.g. `TokenRepositoryInterface`), never concrete classes of another feature.
- **Placement rule — the owning feature publishes its contract.** `Shared/` is only for things with no natural owner (`FeatureInterface`, `JsonRpc/`, `Exception/`, `Tool/`). An interface belongs next to the feature that defines it, even when six others import it: `ApiScope` and `TokenRepositoryInterface` live in `Features/Tokens/Domain/`, `ProviderInterface` in `Features/Playground/Provider/`, `RestGatewayInterface` in `Features/WooCommerceTools/Infrastructure/`. Before adding to `Shared/`, count the consumers — **one consumer means it belongs in the slice**. `src/Shared/Tool/` is three contract files and must stay that way; nothing WooCommerce-specific goes in `src/Shared/`.
- **SOLID**: no `instanceof` branching on concrete types in feature/application code — put the varying behaviour on the interface. One responsibility per class; extract a service when a feature class grows a second workflow.
- **Value objects over arrays**: structured data crossing a boundary (return values, transients, JSON payloads, view data) is a `final readonly` VO or a backed enum, not an `array{...}` shape. `to_array()` only at the serialization edge (`wp_send_json_*`, transients).
- **DRY**: compute once, pass down — views receive data, they don't re-fetch it.

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

## PHP
- **PHP 8.2+**, not 8.1: `final readonly class` is an 8.2 feature (readonly *properties* are 8.1) and 86 files use it. `composer.json`, `phpcs.xml.dist` (`testVersion 8.2-`) and `readme.txt` all agree; keep them that way, because WordPress.org enforces the readme value and a mismatch blocks installs on stores that would otherwise be fine.
- Use: `final readonly` classes, constructor promotion, backed enums, `match`, named arguments, `never` return type where applicable.
- **Avoid `else` — happy path last.** Guard clauses return/throw/redirect early; the main flow reads top-to-bottom unindented. (`if/else` in templates for markup branching is fine.)
- Zero runtime Composer dependencies. Never vendor a library that could collide with core or other plugins (`src/Autoloader.php` explains why). WordPress-bundled libraries (e.g. the WP 7.0 AI Client) are used behind a capability check and a stub file for PHPStan.
- WPCS via `phpcs.xml.dist` (WordPress-Extra, Yoda conditions, i18n text domain `agentgate-mcp-for-woocommerce`). PHPStan level 6 with stubs in `tests/phpstan-*.php`.

## Comments
- Only when needed, and short — one line unless the *why* genuinely needs more.
- Comments explain why, never what. No restating the code, no section banners in PHP.

## UI
- Design tokens from `assets/shared/tokens.css` (mapped to `--wpds-*` with fallbacks). No raw hex in admin/OAuth CSS, never hardcode WooCommerce purple `#7f54b3`.
- WooCommerce settings-screen rules: 720px measure, white cards, one idea per card. Chat is deliberately wider — it is not a settings screen.
- No JS build step; hand-written ES5-flavoured IIFEs. No remote assets (WordPress.org rule).
- Escape-then-format for any model output rendered as HTML (`chat.js formatAnswer`).

## Verification
- `composer run lint` (phpcs), `composer run analyse` (phpstan, needs `--memory-limit=1G`), `composer run test` (phpunit) — all green before claiming done.
