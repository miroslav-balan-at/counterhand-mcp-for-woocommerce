#!/usr/bin/env bash
#
# Build the distributable plugin zip, the same files the wordpress.org release carries.
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

# A WordPress plugin carries its version in several places and nothing keeps
# them in step. The header is what WordPress reads for update
# checks, so a stale constant or Stable tag is a silent bug rather than a loud
# one — check every surface against the header.
check_version() {
	local label="$1" found="$2"

	if [ -z "$found" ]; then
		echo "build: could not read the version from $label" >&2
		exit 1
	fi

	if [ "$found" != "$version" ]; then
		echo "build: $label says $found but the plugin header says $version" >&2
		exit 1
	fi
}

check_version 'readme.txt Stable tag' \
	"$( grep -m1 -i '^Stable tag:' readme.txt | tr -d '[:space:]' | cut -d: -f2 )"

check_version 'the COUNTERHAND_VERSION constant' \
	"$( grep -m1 -E "define\(\s*'COUNTERHAND_VERSION'" "$SLUG.php" | sed -E "s/.*'COUNTERHAND_VERSION'[^']*'([^']+)'.*/\1/" )"

# Scoped to the Changelog section: readme.txt uses `= ... =` for FAQ and feature
# headings too, and the first one in the file is prose.
check_version 'the readme.txt changelog' \
	"$( sed -n '/^== Changelog ==/,$p' readme.txt | grep -m1 -E '^= [0-9]' | tr -d '[:space:]=' )"

check_version 'the .pot header' \
	"$( grep -m1 'Project-Id-Version:' "languages/$SLUG.pot" | sed -E 's/.* ([0-9][^\\ ]*)\\n.*/\1/' )"

# An uncommitted change cannot appear in `git ls-files`, so it would be silently
# missing from the zip. Refuse rather than ship a build that does not match HEAD.
if [ -n "$( git status --porcelain --untracked-files=no )" ]; then
	echo 'build: uncommitted changes to tracked files — commit or stash first' >&2
	exit 1
fi

# The marketing site reads tool-surface.json, so a stale one advertises a tool
# count the plugin no longer has.
if ! diff -q <( php bin/tool-surface.php ) tool-surface.json >/dev/null 2>&1; then
	echo 'build: tool-surface.json is stale — run `php bin/tool-surface.php > tool-surface.json`' >&2
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
for required in "$SLUG.php" 'readme.txt' 'src/Autoloader.php' 'src/Uninstall.php' 'uninstall.php'; do
	[ -f "$STAGE/$required" ] || { echo "build: $required missing from the package" >&2; exit 1; }
done

readonly ZIP="$BUILD/$SLUG-$version.zip"
( cd "$BUILD" && zip -rq "$ZIP" "$SLUG" -x '*.DS_Store' )

echo "built: $ZIP"
echo "files: $count"
echo "size:  $( du -h "$ZIP" | cut -f1 | tr -d '[:space:]' )"
