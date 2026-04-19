#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MANIFEST="${1:-$ROOT/manifest.json}"

if [[ ! -f "$MANIFEST" ]]; then
  echo "Usage: $0 [path/to/manifest.json]" >&2
  echo "Default: $ROOT/manifest.json" >&2
  exit 1
fi

exec php "$ROOT/tools/run-import-manifest.php" "$MANIFEST"
