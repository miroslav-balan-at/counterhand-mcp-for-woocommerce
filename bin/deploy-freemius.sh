#!/usr/bin/env bash
#
# Upload a built zip to Freemius as a new version.
#
# Replaces the dashboard's Deployment -> Add New Version form. Freemius parses
# the upload and generates the free and premium zips from it, so this only has
# to hand over the file.
#
# The token is read from the environment and never written anywhere: it is
# product-scoped (/products/36351/ only) and can be regenerated from
# Settings -> API & Keys, so it is a far smaller blast radius than the account
# secret key — but it can still issue licences and read customers for this
# product, so it does not belong in a file.
#
#   export FREEMIUS_API_TOKEN='...'      # Settings -> API & Keys
#   ./bin/deploy-freemius.sh             # uploads, stays 'pending'
#
# A new deployment lands as 'pending' and is invisible to customers. Releasing
# it is deliberately a separate step, since it cannot be undone quietly:
#
#   ./bin/deploy-freemius.sh --release <tag_id>

set -o errexit
set -o nounset
set -o pipefail

readonly SLUG='counterhand-mcp-for-woocommerce'
readonly PRODUCT_ID='36351'
readonly API='https://api.freemius.com/v1'
readonly ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"

cd "$ROOT"

command -v curl >/dev/null || { echo 'deploy: curl is required' >&2; exit 1; }

if [ -z "${FREEMIUS_API_TOKEN:-}" ]; then
	cat >&2 <<-'EOF'
		deploy: FREEMIUS_API_TOKEN is not set.

		Freemius dashboard -> your product -> Settings -> API & Keys ->
		"API Bearer Authorization Token", then:

		    export FREEMIUS_API_TOKEN='...'

		Use the Bearer token, not the sk_ secret key: the token is limited to
		this product and can be regenerated on its own.
	EOF
	exit 1
fi

if [ "${1:-}" = '--release' ]; then
	tag_id="${2:-}"
	[ -n "$tag_id" ] || { echo 'deploy: --release needs a tag id' >&2; exit 1; }

	echo "Releasing tag $tag_id to all licence holders."
	printf 'Type the tag id again to confirm: '
	read -r confirm
	[ "$confirm" = "$tag_id" ] || { echo 'deploy: not confirmed' >&2; exit 1; }

	curl -sS -X PUT "$API/products/$PRODUCT_ID/tags/$tag_id.json" \
		-H "Authorization: Bearer $FREEMIUS_API_TOKEN" \
		-H 'Content-Type: application/json' \
		-d '{"release_mode":"released"}'
	echo
	exit 0
fi

version="$( grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' "$SLUG.php" | tr -d '[:space:]' | cut -d: -f2 )"
readonly ZIP="build/$SLUG-$version.zip"

if [ ! -f "$ZIP" ]; then
	echo "deploy: $ZIP not found — run ./bin/build-release.sh first" >&2
	exit 1
fi

# Freemius reads the version from the plugin header inside the zip, so a zip
# built from a different commit would deploy under the wrong version silently.
if [ -n "$( git status --porcelain --untracked-files=no )" ]; then
	echo 'deploy: uncommitted changes — the zip would not match HEAD' >&2
	exit 1
fi

echo "Uploading $ZIP to product $PRODUCT_ID."

response="$(
	curl -sS -X POST "$API/products/$PRODUCT_ID/tags.json" \
		-H "Authorization: Bearer $FREEMIUS_API_TOKEN" \
		-F "file=@$ZIP"
)"

echo "$response"

# Freemius answers 200 with an error object rather than an HTTP error code.
if printf '%s' "$response" | grep -q '"error"'; then
	echo 'deploy: upload rejected' >&2
	exit 1
fi

cat <<-EOF

	Uploaded as a pending deployment — customers cannot see it yet.
	Check it in the dashboard, then release it with:

	    ./bin/deploy-freemius.sh --release <tag_id>
EOF
