#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="azuracast-native-suite"
PLUGIN_FILE="$ROOT/azuracast-native-suite.php"
README_FILE="$ROOT/readme.txt"
OUTPUT_DIR="${1:-$ROOT/dist}"

if [[ ! -f "$PLUGIN_FILE" || ! -f "$README_FILE" ]]; then
    echo "Plugin entry file or readme.txt is missing." >&2
    exit 1
fi

VERSION="$(sed -nE 's/^[[:space:]]*\* Version: ([0-9]+\.[0-9]+\.[0-9]+)$/\1/p' "$PLUGIN_FILE" | head -n 1)"
STABLE_TAG="$(sed -nE 's/^Stable tag: ([0-9]+\.[0-9]+\.[0-9]+)$/\1/p' "$README_FILE" | head -n 1)"

if [[ -z "$VERSION" || "$VERSION" != "$STABLE_TAG" ]]; then
    echo "Plugin version and readme stable tag do not match." >&2
    echo "Plugin version: ${VERSION:-missing}; readme stable tag: ${STABLE_TAG:-missing}" >&2
    exit 1
fi

for required in "includes/class-azns-api.php" "includes/class-azns-admin.php" "includes/class-azns-logger.php" "includes/class-azns-shortcodes.php" "includes/class-azns-stations.php" "assets/js/front.js" "assets/css/front.css"; do
    if [[ ! -f "$ROOT/$required" ]]; then
        echo "Missing required plugin file: $required" >&2
        exit 1
    fi
done

mkdir -p "$OUTPUT_DIR"
OUTPUT_DIR="$(cd "$OUTPUT_DIR" && pwd)"
WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT
STAGE="$WORK_DIR/$PLUGIN_SLUG"
mkdir -p "$STAGE"

cp "$PLUGIN_FILE" "$README_FILE" "$STAGE/"
cp -R "$ROOT/includes" "$ROOT/assets" "$STAGE/"

while IFS= read -r -d '' php_file; do
    php -l "$php_file"
done < <(find "$STAGE" -type f -name '*.php' -print0)

ZIP_FILE="$OUTPUT_DIR/$PLUGIN_SLUG-$VERSION.zip"
rm -f "$ZIP_FILE"
(
    cd "$WORK_DIR"
    zip -qr "$ZIP_FILE" "$PLUGIN_SLUG"
)

unzip -tq "$ZIP_FILE"
ZIP_LIST="$(unzip -Z1 "$ZIP_FILE")"
for required in "$PLUGIN_SLUG/azuracast-native-suite.php" "$PLUGIN_SLUG/readme.txt" "$PLUGIN_SLUG/includes/class-azns-api.php" "$PLUGIN_SLUG/assets/js/front.js" "$PLUGIN_SLUG/assets/css/front.css"; do
    if ! grep -Fxq "$required" <<< "$ZIP_LIST"; then
        echo "Release ZIP is missing: $required" >&2
        exit 1
    fi
done

if grep -Eq '(^|/)(\.git|\.github|tests|scripts|dist|node_modules|vendor)/' <<< "$ZIP_LIST"; then
    echo "Release ZIP contains development-only files." >&2
    exit 1
fi

echo "Created and validated: $ZIP_FILE"
du -h "$ZIP_FILE"
