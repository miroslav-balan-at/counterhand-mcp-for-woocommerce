#!/usr/bin/env bash
#
# Build the distributable plugin zip for Freemius and the WooCommerce Marketplace.
#
# The file list comes from git, not from the working directory. A denylist can
# only exclude what someone thought to name, so anything new and untracked ships
# by default — that is how a 2,000-row subscriber export came to sit one
# hand-made zip away from every customer. Requiring a file to be committed
# before it can be packaged inverts that default.
#
# .distignore is then applied on top, so tests and tooling stay out of the
# customer's plugins directory and this script agrees with `wp dist-archive`.

set -o errexit
set -o nounset
set -o pipefail

readonly SLUG='counterhand-mcp-for-woocommerce'
readonly ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"

cd "$ROOT"

command -v git >/dev/null || { echo 'build: git is required' >&2; exit 1; }
command -v zip >/dev/null || { echo 'build: zip is required' >&2; exit 1; }

version="$( grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' "$SLUG.php" | tr -d '[:space:]' | cut -d: -f2 )"
[ -n "$version" ] || { echo "build: no Version header in $SLUG.php" >&2; exit 1; }

readme_version="$( grep -m1 -i '^Stable tag:' readme.txt | tr -d '[:space:]' | cut -d: -f2 )"
if [ "$version" != "$readme_version" ]; then
	echo "build: plugin header ($version) and readme.txt Stable tag ($readme_version) disagree" >&2
	exit 1
fi

# An uncommitted change cannot appear in `git ls-files`, so it would be silently
# missing from the zip. Refuse rather than ship a build that does not match HEAD.
if [ -n "$( git status --porcelain --untracked-files=no )" ]; then
	echo 'build: uncommitted changes to tracked files — commit or stash first' >&2
	exit 1
fi

readonly BUILD="$ROOT/build"
readonly STAGE="$BUILD/$SLUG"

rm -rf "$BUILD"
mkdir -p "$STAGE"

# git's own --exclude-from only filters untracked paths, so it ignores
# .distignore for everything committed — the whole tests directory sails
# through it. The patterns here are plain names and top-level paths, so match
# them directly rather than pretending git will.
is_excluded() {
	local file="$1" pattern

	while IFS= read -r pattern || [ -n "$pattern" ]; do
		pattern="${pattern%%#*}"
		pattern="${pattern#"${pattern%%[![:space:]]*}"}"
		pattern="${pattern%"${pattern##*[![:space:]]}"}"
		[ -n "$pattern" ] || continue
		pattern="${pattern%/}"

		case "$file" in
			"$pattern" | "$pattern"/* ) return 0 ;;
		esac
		case "${file##*/}" in
			"$pattern" ) return 0 ;;
		esac
	done < .distignore

	return 1
}

count=0
while IFS= read -r file; do
	is_excluded "$file" && continue

	mkdir -p "$STAGE/$( dirname "$file" )"
	cp "$ROOT/$file" "$STAGE/$file"
	count=$(( count + 1 ))
done < <( git ls-files -c )

[ "$count" -gt 0 ] || { echo 'build: nothing to package' >&2; exit 1; }

# The plugin cannot boot without these, and a silent allowlist miss would only
# show up on a customer's site.
for required in "$SLUG.php" 'readme.txt' 'uninstall.php' 'src/Autoloader.php' 'freemius/start.php'; do
	[ -f "$STAGE/$required" ] || { echo "build: $required missing from the package" >&2; exit 1; }
done

readonly ZIP="$BUILD/$SLUG-$version.zip"
( cd "$BUILD" && zip -rq "$ZIP" "$SLUG" -x '*.DS_Store' )

echo "built: $ZIP"
echo "files: $count"
echo "size:  $( du -h "$ZIP" | cut -f1 | tr -d '[:space:]' )"
