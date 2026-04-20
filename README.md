# Analysis test project

Small PHP fixture used to generate **PHPStan**, **PHP_CodeSniffer**, **PHPUnit**, **Phauthentic cognitive-code-analysis**, and **PHPUnit Cobertura** coverage for Domain Atlas **Code Analysis** and **Code Coverage** time-series and UI testing.

## Report file names

Reports use the **full 40-character Git commit SHA** (never short SHAs) so filenames stay unique:

`reports/<commitSha>-<tool>-report.<ext>`

| Tool       | Extension | Domain Atlas `format` value   | Notes                                      |
|-----------|-----------|-------------------------------|--------------------------------------------|
| PHPStan   | `.json`   | `github-actions`              | Converted from `phpstan --error-format=json` |
| PHPCS     | `.json`   | `github-actions`              | From `phpcs --report=json`; import `toolName`: `phpcs` |
| PHPUnit   | `.json`   | `github-actions`              | JUnit XML converted to annotations array   |
| Cognitive | `.json` | `gitlab-code-quality`         | phpcca JSON converted to GitLab schema       |
| Coverage (PHPUnit) | `.xml` | `cobertura`              | Native Cobertura from `phpunit --coverage-cobertura` |

## Generate reports for every commit

Requires a clean git history in this repository.

```bash
composer install
chmod +x scripts/replay-and-generate.sh
./scripts/replay-and-generate.sh
```

Optional: clear previous report files first:

```bash
CLEAN=1 ./scripts/replay-and-generate.sh --clean
```

The script snapshots **tools**, **phpunit.xml** (from `main`, absolute bootstrap), and **phpcs.xml.dist** (from `main`) before checkouts so historical commits use consistent rules. Your working tree ends on the **last** commit; use `git checkout main` (or your branch) to return.

Cognitive reports include methods with **phpcca score ≥ 3** (see `scripts/replay-and-generate.sh` and `tools/convert-phpcca-json-to-gitlab-codequality.php`).

## Import into Domain Atlas

1. Push this repository (or a fork) and register it as a **Source repository** in Atlas so the same commit SHAs exist in the index.
2. Generate reports locally with `./scripts/replay-and-generate.sh`.
3. Build `manifest.json` (see `manifest.example.json`): list each `commitSha`, `toolName`, `format`, and `file` path relative to the manifest file.
4. Configure credentials: copy `.env.example` to **`.env.local`** (gitignored) and set `DOMAIN_ATLAS_BASE_URL`, `DOMAIN_ATLAS_TOKEN`, and `SOURCE_REPOSITORY_ID`. The import scripts load `.env.local` automatically; exported shell variables override it.
5. **`DOMAIN_ATLAS_BASE_URL`** must be the API **origin only** (e.g. `http://backend.atlas.local`), not a path like `/api/code-analysis`. Otherwise coverage requests can be routed to the wrong API.
6. **Two different endpoints:** static analysis / test metrics use **`/api/code-analysis/...`** (JSON reports: phpstan, phpcs, phpunit JUnit, cognitive). **Cobertura line coverage** uses **`/api/code-coverage/...`** only. `scripts/import-to-domain-atlas.sh` and `run-import-manifest.php` refuse Cobertura rows; use `import-coverage-to-domain-atlas.sh` for `*-coverage-report.xml`.

Run **one or both** imports:

```bash
php tools/generate-import-manifest.php
php tools/generate-coverage-manifest.php

./scripts/import-to-domain-atlas.sh manifest.full.json
./scripts/import-coverage-to-domain-atlas.sh coverage-manifest.full.json
```

Or run both in order (regenerates manifests, then analysis, then coverage):

```bash
chmod +x scripts/import-all-to-domain-atlas.sh
./scripts/import-all-to-domain-atlas.sh
```

Use `DRY_RUN=1` to print what would be uploaded without calling the API.

### Code coverage (Cobertura)

The replay script runs PHPUnit with `--coverage-cobertura` and writes:

`reports/<commitSha>-coverage-report.xml`

Requires **Xdebug** or **PCOV** (`XDEBUG_MODE=coverage` is set in the script for Xdebug 3).

(Ensure `.env.local` exists; Cobertura imports use `import-coverage-to-domain-atlas.sh` as above.)

## Commits (deliberate issues)

| SHA (short) | Summary |
|-------------|---------|
| `373b34c` | Baseline: clean PHPStan (level 8), passing PHPUnit, low cognitive scores. |
| `34e2196` | Adds `PhpStanProblem.php` with a return-type violation. |
| `91b889e` | Adds `CognitiveHotspot.php` with a high-complexity method. |
| `044bb64` | Makes a unit test fail. |
| `aa6bebc` | Fixes the test, removes `PhpStanProblem`, adds `AnotherPhpStanIssue`. |
| `878d04b` | Lowers cognitive→GitLab threshold (score ≥ 3). |
| `a912582` | Fixes phpcca FQCN leading backslash in GitLab export. |
| `7d755b0` | Replay script: snapshot `tools/` before checkout so converters stay current. |
| `fe49bc4` | Adds PHPCS (PSR-12), JSON converter, replay snapshot of `phpcs.xml.dist` from `main`. |
| `99eba26` | Adds `Legacy/Scratchpad` (missing method visibility for PHPCS). |
| `7363056` | Adds `Utilities/LineCounter`. |
| `8e944bb` | Adds `Http/RequestStub`. |
| `181e3d5` | Adds `ScratchpadTest` and `LegacySmokeTest`. |
| `af49f0c` | Adds `Domain/MetricId`. |
| `86a865c` | Tightens `LegacySmokeTest` (strict types, `final`). |
| `137241f` | Adds `generate-import-manifest.php`; extends `manifest.example.json` with PHPCS. |
| `c5d2d8c` | PHPCS converter: missing/empty JSON → `[]`; ERROR annotations use `failure`. |
| `68c8716` | Replay: absolute PHPUnit paths for tests, `src` (coverage), and schema. |
| `450db65` | Replay: absolute PHPCS `<file>` paths when the ruleset lives under `reports/.tmp/`. |

Use `git log --oneline` for the full list.

## Commands reference

- PHPStan: `vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress --error-format=json`
- PHPCS: `vendor/bin/phpcs --standard=phpcs.xml.dist --report=json --report-file=reports/.tmp/phpcs.json`
- PHPUnit: `vendor/bin/phpunit --log-junit reports/.tmp/junit.xml --coverage-cobertura reports/<sha>-coverage-report.xml`
- Cognitive: `vendor/bin/phpcca analyse src -r json -f reports/.tmp/cognitive.json`
