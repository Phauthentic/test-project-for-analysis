#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MANIFEST="${1:-$ROOT/coverage-manifest.json}"

if [[ ! -f "$MANIFEST" ]]; then
  echo "Usage: $0 [path/to/coverage-manifest.json]" >&2
  echo "Default: $ROOT/coverage-manifest.json" >&2
  exit 1
fi

exec php "$ROOT/tools/run-coverage-import-manifest.php" "$MANIFEST"
