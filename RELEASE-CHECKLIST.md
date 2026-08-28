# Release checklist

The plugin is free and GPL, distributed through the wordpress.org plugin
directory and mirrored on GitHub. Nothing here is automatable from the repo
except the build and the SVN deploy.

## 1. Before tagging

- [ ] Bump the version in **all** places `bin/build-release.sh` checks: the
      plugin header, `COUNTERHAND_VERSION`, `readme.txt` `Stable tag`, the
      topmost `== Changelog ==` entry, and the `.pot` header (regenerate with
      `wp i18n make-pot`, then `update-po` + `make-mo` for `de_DE`).
- [ ] `Tested up to` in `readme.txt` is the **current released** WordPress
      major.minor — a future version is rejected, a stale one lowers search
      ranking and shows the "untested" warning.
- [ ] `composer run lint`, `composer run analyse -- --memory-limit=1G`,
      `composer run test`, `composer audit` — all clean.
- [ ] Verify against the real store (see `CLAUDE.md` → "Verifying against a
      real store"): every profile name is a declared route arg, every tool is
      visible to an administrator, a sample of each group dispatches.
- [ ] `./bin/build-release.sh` builds `build/counterhand-mcp-for-woocommerce-<version>.zip`.
- [ ] Run **Plugin Check** on the zip
      (`wp plugin install plugin-check --activate` on the test store, then
      Tools → Plugin Check, "Plugin repo" category). Any *error* blocks a
      wordpress.org release.
- [ ] Fresh install of the zip on a clean test store: activates without a
      notice, `/mcp` answers, the chat tab works, and `wp plugin uninstall`
      drops both `counterhand_*` tables and the options (`uninstall.php`).

## 2. Release

- [ ] Commit, then `git tag v<version>` and `git push --tags`.
- [ ] The `deploy.yml` workflow (10up/action-wordpress-plugin-deploy) commits
      the tag to wordpress.org SVN and updates `assets/` from `.wordpress-org/`.
      It needs the `SVN_USERNAME` / `SVN_PASSWORD` repository secrets.
- [ ] wordpress.org holds every release for **24 hours** before it reaches
      updaters (policy since June 2026) — do not announce until it is live.
- [ ] `gh release create v<version> build/*.zip --notes-from-tag` so the GitHub
      release carries the same zip.

## 3. After release

- [ ] Reply to every open support thread on wordpress.org — the resolution
      rate feeds search ranking.
- [ ] Readme/asset-only changes (screenshots, `Tested up to`) go through
      `asset-update.yml` without a version bump.

## Why the zip is built from `git ls-files`

The file list comes from `git ls-files` with `.distignore` applied on top, so an
untracked file cannot be packaged even if `.distignore` never names it.
`wp dist-archive` and Delicious Brains' `plugin-build` both walk the filesystem
and only consult the denylist, so a stray file nobody thought to name still
ships — either would have packaged the subscriber CSV that sat in this
directory until 2026-08-01. dist-archive's own folder exclusion was also
silently broken in main by PR #61
(<https://github.com/wp-cli/dist-archive-command/issues/67>).

The build refuses to run when tracked files have uncommitted changes or when the
version surfaces disagree, and it asserts the files the plugin cannot boot
without.
