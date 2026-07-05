# RELEASING.md

How versions are numbered and how a release is cut. The `CHANGELOG.md` records
what changed; this file records the policy and the steps.

## Versioning

This project follows [Semantic Versioning](https://semver.org). The version is a
statement about the tool's public contract, which is the CLI and the config
schema, not the internal PHP classes.

The compatibility surface:

- The `review` command and its flags (`--config`, `--yes`, `--dry-run`, and any
  others).
- The `config.json` schema (see `docs/CONFIG.md`).
- The persisted run layout under `<output_dir>/<timestamp>/`.
- The reviewer and synthesis JSON output contract (see `docs/SCHEMA.md`).

Bump rules once the surface above changes:

- **MAJOR** (`X.0.0`): a flag or command is removed or changes meaning; a
  breaking change to the config schema, the persisted layout, or the JSON
  contract.
- **MINOR** (`0.X.0`): additive and backward compatible: new flags, new opt-in
  behavior, additive config keys, new reviewer support.
- **PATCH** (`0.0.X`): bug fixes, docs, internal refactors, dependency bumps
  with no user-visible change.

### Pre-1.0

While the version is `0.x`, the config schema and CLI are still settling. Bump
**MINOR** for breaking changes and **PATCH** for everything else. Cut `1.0.0`
once the config schema and CLI are stable enough to promise compatibility.

## Source of truth

`LlmReviewPanel\Application::VERSION` is the canonical version string. The git
tag for a release is `v` + that constant (for example `v0.1.0`). `composer.json`
carries no `version` field on purpose: this is a `type: project` installed by
clone, not a published Packagist library, so the git tag is the version.

## Cutting a release

1. Confirm `main` is green: `vendor/bin/pint --test` and `vendor/bin/pest`.
2. Pick the new version per the bump rules above.
3. Set `Application::VERSION` to `X.Y.Z` (no `-dev` suffix).
4. In `CHANGELOG.md`, rename `## [Unreleased]` to `## [X.Y.Z] - YYYY-MM-DD`,
   leave a fresh empty `## [Unreleased]` above it, and update the compare links
   at the bottom.
5. If the release changes user-facing behavior, update `/website` in the same
   commit (the same-PR rule from `website/CLAUDE.md`).
6. Commit: `chore: release vX.Y.Z`.
7. Tag and push:

   ```bash
   git tag -a vX.Y.Z -m "vX.Y.Z"
   git push origin main --follow-tags
   ```

8. Publish the GitHub Release from the changelog section:

   ```bash
   gh release create vX.Y.Z \
     --title "vX.Y.Z" \
     --notes-file <(sed -n '/## \[X.Y.Z\]/,/## \[/p' CHANGELOG.md | sed '$d')
   ```

   Or, more simply, copy the changelog section into
   `gh release create vX.Y.Z --title "vX.Y.Z" --notes-file -`.

9. After the release, set `Application::VERSION` back to the next planned
   version with a `-dev` suffix (for example `0.2.0-dev`) in a follow-up
   `chore:` commit.

## Building the PHAR locally

The tool compiles to a single-file PHAR with Box. Box is pinned and fetched by
the `tools/box` script (it downloads a specific version and verifies its sha256;
no Composer dev-dependency, no Phive):

```bash
composer install --no-dev        # ship runtime deps only
tools/box compile                # writes build/llm-review-panel.phar
php build/llm-review-panel.phar --version
composer install                 # restore dev deps for tests
```

Box needs `phar.readonly=Off`; the `tools/box` wrapper sets it. `box.json` uses
`force-autodiscovery` so Box pulls in `src` and the runtime `vendor`, plus an
explicit `files` list for the config assets (`config/rubric.md`,
`config/synthesis.md`, `config.example.json`) that `init` reads from inside the
PHAR. A non-blocking `phar-smoke.yml` workflow compiles and exercises the PHAR
on every PR. Building a release asset from the PHAR is a later step (see the
Homebrew/PHAR distribution plan).

## Notes

- Releases are cut by hand; there is no tag-triggered workflow. CI still gates
  `main`, so a tag off green `main` is already tested.
- No secrets are needed to release. If a release step ever asks for one, that is
  a signal something drifted from the "no API calls" constraint.
