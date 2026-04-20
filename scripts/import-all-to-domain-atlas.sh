#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

php "$ROOT/tools/generate-import-manifest.php"
php "$ROOT/tools/generate-coverage-manifest.php"

MANIFEST="${1:-$ROOT/manifest.full.json}"
COV_MANIFEST="${2:-$ROOT/coverage-manifest.full.json}"

echo "=== Code analysis (phpstan, phpcs, cognitive) → /api/code-analysis/... ==="
"$ROOT/scripts/import-to-domain-atlas.sh" "$MANIFEST"

echo "=== Coverage (Cobertura) → /api/code-coverage/... ==="
"$ROOT/scripts/import-coverage-to-domain-atlas.sh" "$COV_MANIFEST"
