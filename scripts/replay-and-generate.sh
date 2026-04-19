#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

CLEAN="${CLEAN:-0}"
if [[ "${1:-}" == "--clean" ]]; then
  CLEAN=1
fi

if [[ "$CLEAN" == "1" ]]; then
  rm -f reports/*.json reports/*.xml reports/*.sarif reports/.tmp/* 2>/dev/null || true
fi

mkdir -p reports/.tmp

# Converters must run from the current tree (latest tools/). Historical commits may not
# include converter fixes, so snapshot tools/ before any checkout.
TOOLKIT="$(mktemp -d)"
cp "$ROOT/tools"/*.php "$TOOLKIT/"

if [[ ! -f vendor/bin/phpstan ]]; then
  echo "Run composer install first." >&2
  exit 1
fi

mapfile -t COMMITS < <(git rev-list --reverse HEAD)

if [[ ${#COMMITS[@]} -eq 0 ]]; then
  echo "No commits in repository." >&2
  exit 1
fi

for sha in "${COMMITS[@]}"; do
  echo "==> Checkout $sha"
  git checkout -q "$sha"

  # PHPStan -> GitHub annotations JSON (github-actions)
  phpstan_out="reports/.tmp/phpstan-${sha}.json"
  set +e
  vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress --error-format=json >"$phpstan_out" 2>/dev/null
  set -e
  php "$TOOLKIT/convert-phpstan-json-to-github-annotations.php" "$phpstan_out" "$ROOT" \
    >"reports/${sha}-phpstan-report.json"

  # phpcca -> GitLab Code Quality JSON
  vendor/bin/phpcca analyse "$ROOT/src" -r json -f "reports/.tmp/cognitive-${sha}.json" >/dev/null 2>&1
  php "$TOOLKIT/convert-phpcca-json-to-gitlab-codequality.php" \
    "reports/.tmp/cognitive-${sha}.json" "$ROOT" 3 \
    >"reports/${sha}-cognitive-report.json"

  # PHPUnit -> JUnit (analysis) + Cobertura XML (coverage) in one run
  junit_out="reports/.tmp/junit-${sha}.xml"
  cobertura_out="reports/${sha}-coverage-report.xml"
  set +e
  export XDEBUG_MODE=coverage
  vendor/bin/phpunit \
    --log-junit "$junit_out" \
    --coverage-cobertura "$cobertura_out" \
    >/dev/null 2>&1
  set -e
  php "$TOOLKIT/convert-junit-to-github-annotations.php" "$junit_out" "$ROOT" \
    >"reports/${sha}-phpunit-report.json"
done

rm -rf "$TOOLKIT"

echo "Done. Reports in reports/ (filenames use full 40-char commit SHA)."
echo "Restore branch: git checkout -"
