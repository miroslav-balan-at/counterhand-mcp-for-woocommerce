# Release checklist

Everything here needs the Freemius dashboard or a test store, so none of it is
automatable from the repo. Sources are linked where the step is non-obvious.

## 1. Dashboard: plans

The product (`36351`) exists; it has no sellable plan yet.

- [ ] **Delete the default Free plan.** Freemius generates one with every new
      product. For a paid-only product it must be removed, or buyers see a free
      option and can activate against it.
      <https://freemius.com/help/documentation/wordpress/setup-product-pricing-plans-refunds/>
- [ ] **Add a paid plan** — name `pro`, title `Pro`.
- [ ] **Set annual pricing to €79** on the Single Site option. This must match
      `COUNTERHAND_PRICE` on the product site and, once listed, the WooCommerce
      Marketplace listing (Vendor Agreement §1.2.5 requires price parity).
- [ ] **Decide on bulk tiers.** A single plan is the documented shape when the
      only difference between tiers is site count — add 5-site/unlimited rows
      under Bulk Pricing rather than creating separate plans.
- [ ] **Leave localhost activations enabled** so buyers can run staging and dev
      copies without burning activations. This is also what makes step 3 possible.
- [ ] **Decide on a trial.** `can_use_premium_code()` already covers a trial, so
      enabling one needs no code change.

## 2. Local test store: sandbox mode

Sandbox lets the whole purchase flow run with test cards and no real payment.
These go in the test store's `wp-config.php` — **never in the plugin, never
committed**. The secret key is the credential for the entire Freemius account.

```php
define( 'WP_FS__DEV_MODE', true );
define( 'WP_FS__counterhand-mcp-for-woocommerce_SECRET_KEY', 'sk_...' );
define( 'WP_FS__SKIP_EMAIL_ACTIVATION', true );
```

Turn `WP_FS__DEV_MODE` **off** when finished: it switches on SDK logging, which
writes to the database and the console log.
<https://freemius.com/help/documentation/wordpress-sdk/testing/>

## 3. Activation test

The licence gate and updater have unit tests but have never run against
Freemius's servers. This is the last untested part of the product.

- [ ] Install the plugin on a clean test store (not the product site).
- [ ] Generate a licence: dashboard → Licenses → Create License.
- [ ] Activate it and confirm the MCP endpoint starts answering.
- [ ] **Deactivate and confirm the endpoint returns 404, not 401.** An
      unlicensed store never registers the route — see `McpServerFeature`.
- [ ] Confirm the in-admin chat still works while unlicensed. That is
      deliberate: the gate covers the endpoint only.
- [ ] Confirm the update check reaches Freemius and not wordpress.org.
- [ ] Run one sandbox purchase end to end with a test card.

## 4. Release zip

Not solved. `.distignore` is a denylist, so a hand-made zip currently ships
`CLAUDE.md`, `.gitattributes`, `.idea/`, `.claude/`, and anything else new in
the directory. A subscriber CSV sat one zip away from customers until 2026-08-01.

Build from `git ls-files` (allowlist) intersected with `.distignore`, or install
`wp dist-archive` and keep `.distignore` exhaustive. Verify the zip contents
before the first upload.

## Known-good state

`0f82abd` — 767 tests, lint, PHPStan level 6, `composer audit` all clean.
Pushed to <https://github.com/miroslav-balan-at/counterhand-mcp-for-woocommerce> (private).
