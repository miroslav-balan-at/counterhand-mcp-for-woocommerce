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

## 3. Activation test — the last untested path

Verified so far on a real store, unlicensed:

- The plugin activates with no fatal; the SDK accepts product `36351`.
- `has_paid_plan()` is true, so plan `60358` is visible to the plugin.
- `can_use_premium_code()` is false, the MCP route is never registered, and
  `/wp-json/counterhand/v1/mcp` answers `404 rest_no_route`.
- WordPress and the SDK both report version 1.0.0.

Not yet verified, and all of it waits on one licence:

- **Updates.** While `is_registered()` is false the SDK attaches no updater, so
  an update check contacts only `api.wordpress.org` and the plugin appears in
  neither `response` nor `no_update`. Nothing serves its updates yet. That is
  expected before opt-in, but it means the update path has never once run —
  and this slug is not on wordpress.org, so if Freemius ever fails to attach,
  the failure is silent rather than loud.
- The licensed side of the gate: nobody has seen the endpoint answer.

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

Done — build with `./bin/build-release.sh`, which writes
`build/counterhand-mcp-for-woocommerce-<version>.zip` (372 files, 1.3 MB).

The file list comes from `git ls-files` with `.distignore` applied on top, so an
untracked file cannot be packaged even if `.distignore` never names it. Verified
by dropping a stray `.csv` in the plugin root and confirming it stayed out.

The build refuses to run when tracked files have uncommitted changes, or when
the plugin header and `readme.txt` stable tag disagree, and it asserts the five
files the plugin cannot boot without.

### Why not `wp dist-archive`

It is the closest thing to an official tool, and the `.distignore` here is kept
accurate so it stays usable. But it walks the filesystem with
`RecursiveDirectoryIterator` and only consults `.distignore`, so an untracked
file that nobody thought to name still ships. Delicious Brains' `plugin-build`
has the same shape (`rsync --filter`). Either would have packaged the subscriber
CSV that sat in this directory until 2026-08-01.

The denylist also has to be right at the moment it runs: dist-archive's own
folder exclusion was silently broken in main by PR #61, reported as a security
risk in <https://github.com/wp-cli/dist-archive-command/issues/67> and not
covered by its tests.

Both tools remain fine for plugins whose working directory only ever contains
committed files. This one demonstrably does not.

- [ ] Upload the zip to Freemius (Deployment → Add version).

## Known-good state

`0f82abd` — 767 tests, lint, PHPStan level 6, `composer audit` all clean.
Pushed to <https://github.com/miroslav-balan-at/counterhand-mcp-for-woocommerce> (private).
