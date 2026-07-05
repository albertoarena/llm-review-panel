# Plan: Homebrew / PHAR distribution

Status: draft for review | Target: v0.2.0 or later | Date: 2026-07-05

Ship `llm-review-panel` as a single-file PHAR, released on each tag via GitHub
Actions, and installable through a Homebrew tap:

```bash
brew install albertoarena/tap/llm-review-panel
```

PHP stays the only runtime dependency; the PHAR bundles the rest.

## Timing gate

The roadmap says to hold off until the tool has been used for real for a couple
of weeks, so `0.0.x`/`0.1.x` decisions do not get locked into a package manager.
v0.1.0 shipped 2026-07-04. Do not start Phase C or D (anything that publishes an
installable artifact) before roughly mid-July 2026, and only once the config
schema and CLI feel stable. Phases A and B can be built and merged earlier
behind no user-facing promise.

## Constraints carried from CLAUDE.md

- No API calls, no HTTP client to model APIs, no new runtime deps beyond PHP.
- Config-driven; do not hardcode reviewer lists or model names.
- TDD default: the `init` command (Phase B) ships with failing-test-first
  coverage. The PHAR build itself is not unit-testable in Pest; it is smoke
  tested in CI instead (see Testing).
- A user-facing behavior change updates `/website` in the same PR.
- No em dashes, no marketing language.
- Keep the manual release decision from `docs/RELEASING.md`: releases are cut by
  hand with `gh release create`. The PHAR build hangs off the published release,
  it does not replace the manual step (see Phase C).

## The load-bearing problem: config bootstrap

Today the tool runs from a clone, so `config.example.json`, `config/rubric.md`,
and `config/synthesis.md` are all present, and config paths resolve relative to
the config file's directory. A global `brew` install has none of these. A bare
PHAR on `PATH` can print `--version` but cannot run a review, because there is no
`config.json`, no rubric, and no synthesis prompt on disk.

So distribution is not just packaging. It needs a way to get the default config
and asset files out of the PHAR and onto the user's disk. This is Phase B and is
a hard prerequisite for Phase D being useful.

Resolved (Decision 1): add an `init` command that scaffolds `config.json` +
`config/rubric.md` + `config/synthesis.md` into the current directory from copies
bundled inside the PHAR. Static default config (no PATH detection in v1); scaffold
into CWD (not an XDG dir), which fits the per-project rubric model.

## Phase A: Box setup and local PHAR build

- Use a **pinned Box Phar**, not a Composer dev-dependency (Decision 2). Box's
  own docs recommend against installing it through Composer because its
  dependency tree conflicts with host projects; this project keeps a deliberately
  tiny dependency surface. Pin Box locally via Phive (`.phive/phars.xml`) and
  download the same pinned version in CI. Box is the standard PHAR compiler for
  PHP.
- Add `box.json`:
  - `main`: `bin/llm-review-panel`
  - `output`: `build/llm-review-panel.phar`
  - `directories`: `src`
  - `files`: bundle `config/rubric.md`, `config/synthesis.md`,
    `config.example.json` so `init` can read them from `phar://`.
  - `finder`: include `vendor/` with dev packages excluded (`require-dev`
    stripped via `composer install --no-dev` before the box run).
  - `compression`: `GZ` (Decision 4). zlib is needed to decompress at runtime;
    it is near-universal in PHP builds, so the size win is worth the negligible
    risk. Trivial to flip to none if it ever bites.
  - `banner`: short, no marketing.
- Confirm the entrypoint works inside a PHAR. `bin/llm-review-panel` already
  probes two autoloader paths; verify the `phar://.../vendor/autoload.php` path
  resolves when wrapped. Adjust the probe if box relocates the autoloader.
- The version is `LlmReviewPanel\Application::VERSION`, already bumped by the
  release process. Box does not need to stamp it; do not add a second source of
  truth.

Done when: `composer install --no-dev && phive install && tools/box compile`
locally produces `build/llm-review-panel.phar`, and
`php build/llm-review-panel.phar --version` prints the current version and
`--dry-run` validates a config without spawning anything.

## Phase B: `init` command (scaffold config from the PHAR)

- New `init` command: `llm-review-panel init [--force] [target-dir]`.
  - Writes `config.json` (from the bundled `config.example.json`),
    `config/rubric.md`, and `config/synthesis.md` into the target dir
    (default: CWD).
  - Refuses to overwrite existing files unless `--force`; reports what it wrote.
  - Reads the templates via `__DIR__` so it works both from a clone and from
    inside `phar://`.
  - Prints next steps: edit `config.json` to enable installed reviewers, then
    `llm-review-panel review <plan.md> --dry-run`.
- The bundled `config.json` template keeps the current default of common
  reviewers enabled and the rest present-but-disabled. No PATH detection of
  installed reviewers in v1 (Decision 1); it emits the static default. Detecting
  and auto-enabling installed CLIs is a possible later enhancement.

Done when: Pest tests (written first) cover: writes all three files into an
empty dir; refuses to clobber without `--force`; `--force` overwrites; templates
resolve from a simulated non-CWD location. `--dry-run` on the freshly-scaffolded
config passes.

## Phase C: Build and attach the PHAR on release

- New workflow `.github/workflows/release-phar.yml`, triggered on
  `release: { types: [published] }` so it composes with the manual
  `gh release create` step rather than replacing it.
- Steps: checkout at the release tag, `setup-php` (8.3, with `phar.readonly=Off`
  and the zlib extension), `composer install --no-dev --optimize-autoloader`,
  install the pinned Box Phar (Phive or a direct pinned download, matching the
  local pin from Phase A), `box compile`, compute `sha256`, then
  `gh release upload <tag> build/llm-review-panel.phar` plus a `.sha256` sidecar.
- Guard: fail the job if the PHAR's reported `--version` does not equal the tag
  minus the `v` prefix. This catches a tag that does not match
  `Application::VERSION`.
- No secrets: uses the default `GITHUB_TOKEN` (it can upload release assets).
  PHAR signing is optional and skipped; the Homebrew formula's sha256 is the
  integrity check.

Done when: publishing a test release produces a downloadable
`llm-review-panel.phar` asset that runs `--version` on a machine with only PHP
installed.

## Phase D: Homebrew tap and formula

- Create a separate public repo `albertoarena/homebrew-tap` (Homebrew's naming
  convention makes `brew install albertoarena/tap/<formula>` resolve to it).
- Add `Formula/llm-review-panel.rb`:
  - `depends_on "php"` (Homebrew's latest, currently 8.x). The tool supports
    8.3+, so tracking newest is fine and avoids a formula bump when a pinned
    `php@8.3` hits EOL.
  - `url` points at the release asset PHAR; `sha256` from the `.sha256` sidecar.
  - `install`: place the PHAR into `libexec`, then write a thin `bin` wrapper
    that runs `php <libexec>/llm-review-panel.phar "$@"`, so `--version` and all
    flags pass through.
  - `test do`: assert `#{bin}/llm-review-panel --version` contains the version.
- Bump the formula **by hand** for the first few releases (Decision 3): after the
  PHAR asset and its `.sha256` are published, update `url` + `sha256` in the tap
  repo and commit. This avoids managing a cross-repo PAT secret until the release
  flow is proven. Automating the bump (a PR opened by `release-phar.yml` using a
  tap-scoped PAT) is a later enhancement; note it in `docs/RELEASING.md` as a
  future step, not a current one.

Done when: `brew install albertoarena/tap/llm-review-panel` installs a working
binary on a clean machine, `llm-review-panel init` scaffolds a config, and a
review runs against a locally installed reviewer CLI.

## Phase E: Documentation

- `/website` Installation page (`getting-started/installation`): add a Homebrew
  tab next to the from-source instructions, and document the
  `llm-review-panel init` bootstrap step for global installs. Use Starlight
  `<Tabs>` for the install variants.
- README: add the `brew install` one-liner under Quick start, keep the
  from-source path.
- `docs/RELEASING.md`: add a line noting that publishing a release triggers the
  PHAR build and the tap bump, and what to check if either fails.
- `CHANGELOG.md`: `Added` entries for the PHAR artifact and the `init` command.

Done when: pushing to `main` publishes the updated site with working install
instructions for both paths.

## Testing

- Unit (Pest): the `init` command, per Phase B. No real processes, per the
  existing fake-runner rule.
- CI smoke (in `release-phar.yml` and optionally a PR-time build job): compile
  the PHAR, then run `--version`, `--help`, `init` into a temp dir, and
  `review <sample-plan> --dry-run` against the scaffolded config. All of these
  run without spawning a real reviewer, so the "no real CLI in CI" rule holds.
- Do not add the PHAR build to the required merge gate initially; run it on
  release only, plus a non-blocking PR build to catch box-config breakage early.

## Decisions (approved 2026-07-05)

1. **`init` command**: yes. Static default config (no PATH detection in v1),
   scaffolded into CWD (not an XDG dir).
2. **Box**: pinned Box Phar via Phive locally + a matching pinned download in
   CI. Not a Composer dev-dependency.
3. **Tap bump**: manual for the first few releases; automate later once proven.
4. **Compression**: `GZ`.
5. **PHAR signing**: sha256 only (formula sha256 + `.sha256` sidecar). No
   GPG/openssl signing for now.

Related, non-blocking: the Homebrew formula depends on `php` (latest), not a
pinned `php@8.3`.

## Out of scope

- Publishing to Packagist as a library (this stays a `type: project` app).
- A self-update command inside the tool (Homebrew handles upgrades).
- Non-PHP packaging (Docker image, static binary via FrankenPHP, etc.).
- Windows/Scoop/apt packaging. Revisit only on demand.
