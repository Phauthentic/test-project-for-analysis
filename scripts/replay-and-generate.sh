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

TOOLKIT="$(mktemp -d)"
cp "$ROOT/tools"/*.php "$TOOLKIT/"

# PHPUnit: use main's config with absolute bootstrap (paths resolve correctly from project root).
if git show main:phpunit.xml >/dev/null 2>&1; then
  PHPUNIT_SRC="$(git show main:phpunit.xml)"
else
  PHPUNIT_SRC="$(cat "$ROOT/phpunit.xml")"
fi
# Tests/bootstrap/schema paths must be absolute: PHPUnit resolves them relative to the config file
# (here reports/.tmp/), not the project root.
printf '%s\n' "$PHPUNIT_SRC" | sed \
  -e "s|bootstrap=\"vendor/autoload.php\"|bootstrap=\"${ROOT}/vendor/autoload.php\"|" \
  -e "s|<directory>tests</directory>|<directory>${ROOT}/tests</directory>|" \
  -e "s|<directory suffix=\".php\">src</directory>|<directory suffix=\".php\">${ROOT}/src</directory>|" \
  -e "s|xsi:noNamespaceSchemaLocation=\"vendor/phpunit/phpunit/phpunit.xsd\"|xsi:noNamespaceSchemaLocation=\"${ROOT}/vendor/phpunit/phpunit/phpunit.xsd\"|" \
  >"$ROOT/reports/.tmp/phpunit-for-replay.xml"
PHPUNIT_REPLAY="$ROOT/reports/.tmp/phpunit-for-replay.xml"

# PHPCS: snapshot main ruleset; use absolute paths for <file> entries (config lives in reports/.tmp/).
if git show main:phpcs.xml.dist >/dev/null 2>&1; then
  PHPCS_SRC="$(git show main:phpcs.xml.dist)"
else
  PHPCS_SRC="$(cat "$ROOT/phpcs.xml.dist")"
fi
printf '%s\n' "$PHPCS_SRC" | sed \
  -e "s|<file>src</file>|<file>${ROOT}/src</file>|" \
  -e "s|<file>tests</file>|<file>${ROOT}/tests</file>|" \
  >"$ROOT/reports/.tmp/phpcs-for-replay.xml"
PHPCS_REPLAY="$ROOT/reports/.tmp/phpcs-for-replay.xml"

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

  # PHPCS -> GitHub annotations JSON (older commits may not have phpcs in composer.lock)
  phpcs_out="reports/.tmp/phpcs-${sha}.json"
  if [[ -f vendor/bin/phpcs ]] && [[ -f "$PHPCS_REPLAY" ]]; then
    set +e
    vendor/bin/phpcs --standard="$PHPCS_REPLAY" --report=json --report-file="$phpcs_out" >/dev/null 2>&1
    set -e
    php "$TOOLKIT/convert-phpcs-json-to-github-annotations.php" "$phpcs_out" "$ROOT" \
      >"reports/${sha}-phpcs-report.json"
  else
    echo '[]' >"reports/${sha}-phpcs-report.json"
  fi

  # phpcca -> GitLab Code Quality JSON
  vendor/bin/phpcca analyse "$ROOT/src" -r json -f "reports/.tmp/cognitive-${sha}.json" >/dev/null 2>&1
  php "$TOOLKIT/convert-phpcca-json-to-gitlab-codequality.php" \
    "reports/.tmp/cognitive-${sha}.json" "$ROOT" 3 \
    >"reports/${sha}-cognitive-report.json"

  # PHPUnit -> JUnit (analysis) + Cobertura (coverage)
  junit_out="reports/.tmp/junit-${sha}.xml"
  cobertura_out="reports/${sha}-coverage-report.xml"
  set +e
  export XDEBUG_MODE=coverage
  vendor/bin/phpunit \
    --configuration "$PHPUNIT_REPLAY" \
    --log-junit "$junit_out" \
    --coverage-cobertura "$cobertura_out" \
    >/dev/null 2>&1
  set -e
  php "$TOOLKIT/convert-junit-to-github-annotations.php" "$junit_out" "$ROOT" \
    >"reports/${sha}-phpunit-report.json"

  # phploc-compatible JSON (code-metrics API: toolName phploc, format phploc-json)
  php "$TOOLKIT/generate-phploc-style-metrics.php" "$ROOT" "$ROOT/reports/${sha}-phploc-report.json"
done

rm -rf "$TOOLKIT"

echo "Done. Reports in reports/ (filenames use full 40-char commit SHA)."
echo "Restore branch: git checkout -"
