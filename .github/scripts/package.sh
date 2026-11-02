#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
OUTPUT_DIR="${1:-$ROOT/dist}"
OUTPUT_DIR="$(mkdir -p "$OUTPUT_DIR" && cd "$OUTPUT_DIR" && pwd)"

VERSION="$(sed -nE "s/^[[:space:]]*define\\('PLUGIN_SIGNATURES_VERSION',[[:space:]]*'([^']+)'\\);[[:space:]]*$/\\1/p" "$ROOT/setup.php" | head -n 1)"
if [[ -z "$VERSION" ]]; then
    echo "Unable to read PLUGIN_SIGNATURES_VERSION from setup.php." >&2
    exit 1
fi

STAGING_DIR="$(mktemp -d)"
trap 'rm -rf "$STAGING_DIR"' EXIT
PACKAGE_ROOT="$STAGING_DIR/signatures"
mkdir -p "$PACKAGE_ROOT"

for path in setup.php plugin.xml src templates public locales; do
    if [[ ! -e "$ROOT/$path" ]]; then
        echo "Required installation path is missing: $path" >&2
        exit 1
    fi
    cp -a "$ROOT/$path" "$PACKAGE_ROOT/$path"
done

for path in README.md CHANGELOG.md LICENSE logo.png config.png user.png; do
    if [[ -f "$ROOT/$path" ]]; then
        cp -a "$ROOT/$path" "$PACKAGE_ROOT/$path"
    fi
done

ARCHIVE="$OUTPUT_DIR/signatures-$VERSION.zip"
rm -f "$ARCHIVE"
(
    cd "$STAGING_DIR"
    zip -qr "$ARCHIVE" signatures
)

unzip -t "$ARCHIVE" >/dev/null
printf 'Built and verified %s\n' "$ARCHIVE"
