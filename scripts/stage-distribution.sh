#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 2 || "$2" != /* ]]; then
	echo "Usage: $0 <exact-source-sha> <empty-absolute-output-directory>" >&2
	exit 2
fi

source_sha=$1
output=$2
repo=$(cd "$(dirname "$0")/.." && pwd -P)

if [[ ! -d "$output" || -n "$(ls -A "$output")" ]]; then
	echo "Output directory must exist and be empty" >&2
	exit 2
fi
output=$(cd "$output" && pwd -P)
case "$output/" in
	"$repo/"*) echo "Output must be outside the source checkout" >&2; exit 2 ;;
esac

if [[ "$(git -C "$repo" rev-parse HEAD)" != "$source_sha" ]] ||
	[[ -n "$(git -C "$repo" status --porcelain --untracked-files=all)" ]]; then
	echo "Source must be a clean checkout at the exact requested SHA" >&2
	exit 2
fi

# Audited distribution allowlist. git archive reads only committed content at source_sha.
git -C "$repo" archive --format=tar --prefix=loyalty-for-woocommerce/ "$source_sha" -- \
	loyalty-for-woocommerce.php readme.txt changelog.txt license.txt \
	css img inc js languages | tar -xf - -C "$output"

echo "Staged loyalty-for-woocommerce/ from $source_sha"
