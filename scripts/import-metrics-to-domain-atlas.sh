#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MANIFEST="${1:-$ROOT/metrics-manifest.full.json}"

if [[ ! -f "$MANIFEST" ]]; then
  echo "Usage: $0 [path/to/metrics-manifest.json]" >&2
  echo "Default: $ROOT/metrics-manifest.full.json" >&2
  exit 1
fi

exec php "$ROOT/tools/run-metrics-import-manifest.php" "$MANIFEST"
