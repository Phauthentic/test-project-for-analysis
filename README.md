# Analysis test project

Small PHP fixture used to generate **PHPStan**, **PHPUnit**, and **Phauthentic cognitive-code-analysis** reports for Domain Atlas **Code Analysis** time-series and UI testing.

## Report file names

Reports use the **full 40-character Git commit SHA** (never short SHAs) so filenames stay unique:

`reports/<commitSha>-<tool>-report.<ext>`

| Tool       | Extension | Domain Atlas `format` value   | Notes                                      |
|-----------|-----------|-------------------------------|--------------------------------------------|
| PHPStan   | `.json`   | `github-actions`              | Converted from `phpstan --error-format=json` |
| PHPUnit   | `.json`   | `github-actions`              | JUnit XML converted to annotations array   |
| Cognitive | `.json` | `gitlab-code-quality`         | phpcca JSON converted to GitLab schema       |

## Generate reports for every commit

Requires a clean git history in this repository (see commits below).

```bash
composer install
chmod +x scripts/replay-and-generate.sh
./scripts/replay-and-generate.sh
```

Optional: clear previous report files first:

```bash
CLEAN=1 ./scripts/replay-and-generate.sh --clean
```

The script checks out each commit, runs the three tools, and writes files under `reports/`. Your working tree ends on the **last** commit; use `git checkout main` (or your branch) to return.

## Import into Domain Atlas

1. Push this repository (or a fork) and register it as a **Source repository** in Atlas so the same commit SHAs exist in the index.
2. Generate reports locally with `./scripts/replay-and-generate.sh`.
3. Build `manifest.json` (see `manifest.example.json`): list each `commitSha`, `toolName`, `format`, and `file` path relative to the manifest.
4. Run:

```bash
export DOMAIN_ATLAS_BASE_URL="https://your-atlas-host"
export DOMAIN_ATLAS_TOKEN="your-jwt"
export SOURCE_REPOSITORY_ID="uuid-of-connected-repo"
./scripts/import-to-domain-atlas.sh manifest.json
```

Use `DRY_RUN=1` to print what would be uploaded without calling the API.

## Commits (deliberate issues)

1. Baseline: clean PHPStan (level 8), passing PHPUnit, low cognitive scores.
2. Adds `PhpStanProblem.php` with a return-type violation.
3. Adds `CognitiveHotspot.php` with a high-complexity method.
4. Makes a unit test fail.
5. Fixes the PHPStan file and the test, adds `AnotherPhpStanIssue.php`, keeps cognitive hotspot for mixed signals.

## Commands reference

- PHPStan: `vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress --error-format=json`
- PHPUnit: `vendor/bin/phpunit --log-junit reports/.tmp/junit.xml`
- Cognitive: `vendor/bin/phpcca analyse src -r json -f reports/.tmp/cognitive.json`
