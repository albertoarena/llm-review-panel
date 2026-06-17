# IMPLEMENTATION.md

Phased build plan. Build and test each phase before moving on. The first three
phases produce a fully testable pipeline without spawning any real LLM CLI.

## Phase 0: Project skeleton

- `composer.json` with `symfony/console`, `symfony/process`, `pestphp/pest`
  (dev). PHP 8.2+, strict types, PSR-12.
- `bin/llm-review-panel` console entrypoint.
- Pest bootstrap.

**Done when**: `composer install` succeeds and `bin/llm-review-panel list`
prints an empty command list.

## Phase 1: Config loader

- Load and validate `config.json` against the shape in `docs/CONFIG.md`.
- Resolve `rubric_file`, `synthesizer.prompt_file`, `output_dir` **relative to
  the config file's directory**, not CWD.
- Fail fast on: unknown fields, missing required fields, `synthesizer.reviewer_id`
  not matching any reviewer, zero enabled reviewers.

**Done when**: Pest tests cover valid configs, each failure mode, and path
resolution from a non-CWD config location.

## Phase 2: Prompt assembly

- Build the review prompt: rubric + plan + JSON schema instructions (referencing
  `docs/SCHEMA.md`).
- Build the synthesis prompt: synthesis instructions + all reviewer outputs
  concatenated with clear delimiters.
- Substitute `{prompt}` and `{model}` placeholders in each reviewer's `args`.

**Done when**: golden-file Pest tests assert exact prompt bytes for both review
and synthesis assembly.

## Phase 3: Fake process runner

- One `ProcessRunner` interface. One `FakeProcessRunner` for tests that returns
  scripted stdout/stderr/exit-code per `(command, args)` pair.
- One `RealProcessRunner` (stub for now) that delegates to `Symfony\Process`
  asynchronously.
- The entire pipeline (config -> assemble -> run -> parse -> synthesize)
  must work end-to-end against the fake runner.

**Done when**: a Pest test runs the full pipeline with three fake reviewers
(two JSON, one unstructured) and asserts the synthesizer is invoked with the
expected inputs.

## Phase 4: Result parsing

- Tolerant parser per `docs/SCHEMA.md` parser tolerance section.
- Dot-path extraction for `result_path` (no array indexing; document the limit).
- `result_path` assumes a single JSON object on stdout. JSONL is the reviewer's
  problem to flatten via its own flags.
- stderr captured to the run directory but not shown unless a reviewer fails.

**Done when**: parser tests cover plain text, fenced JSON, raw JSON, prose-wrapped
JSON (demoted to unstructured), and missing `result_path` keys.

## Phase 5: Real process runner + parallelism

- `RealProcessRunner` spawns via `Process::start()`, polls for completion,
  respects `max_parallel` via a simple in-process semaphore.
- Per-reviewer `timeout` enforced; on timeout, kill the process and record as
  failed with reason `timeout`. Other reviewers continue.
- Failure taxonomy recorded per reviewer: `ok`, `unstructured`, `timeout`,
  `nonzero_exit`, `empty_stdout`, `parse_error`.

**Done when**: a manual run against `claude` and `opencode` produces a complete
run directory and one deliberately-broken reviewer (bad command) is recorded as
failed without affecting the others.

## Phase 6: CLI surface, checkpoints, dry-run

- `review <plan.md> [--config path] [--yes] [--dry-run]`.
- Checkpoint I/O extracted behind a `QuestionProvider` interface so it can be
  driven by a canned Q&A array in tests.
- `--dry-run`: print each enabled reviewer's resolved command line (placeholders
  substituted, prompt elided to first 80 chars), check `command` is on `PATH`,
  exit. No spawns.
- `--yes`: skip all three checkpoints.
- Checkpoint 1 prints a warning line when any enabled reviewer is paid.
- Checkpoint 2 supports "re-run reviewer <id>": replace that one result, leave
  the others intact, re-render. Do not re-run everything.

**Done when**: Pest tests drive the full flow with a canned `QuestionProvider`
through each checkpoint branch (accept, re-run, abort).

## Phase 7: Run persistence

- Write to `<output_dir>/<timestamp>/` (timestamp resolved at run start):
  - `prompt.md` (assembled review prompt)
  - `reviews/<reviewer_id>.json` (or `.txt` if unstructured)
  - `reviews/<reviewer_id>.stderr.log`
  - `synthesis.md`
  - `manifest.json` (config snapshot, per-reviewer status, durations)

**Done when**: a successful run produces all files; a partially-failed run
records the failures in `manifest.json`.

## Phase 8: Documentation site

Done only after Phase 7 is green and the CLI runs end-to-end against at least
two real reviewers.

- Scaffold an Astro site under `/website` (standard starter layout:
  `astro.config.mjs`, `package.json`, `src/pages`, `src/content`).
- Pages: install/quick start, walkthrough of a real review with all three
  checkpoints, one reviewer-setup recipe per supported tool, rubric/synthesis
  tips, ideas/roadmap.
- GitHub Actions workflow `.github/workflows/deploy-docs.yml` builds with
  `npm ci && npm run build` in `/website` and deploys `dist/` to GitHub Pages
  via `actions/deploy-pages`. Runs on push to `main` only; PRs build but do
  not deploy.
- Enable GitHub Pages with source = GitHub Actions in repo settings.

**Done when**: pushing to `main` publishes the updated site, and the rule
"behavior change requires a `/website` update in the same PR" is enforceable
(noted in PR template or CLAUDE.md).

## Out of scope (do not build)

- PHP-side consensus/contested/singleton tagging. That is the synthesizer
  model's job via `config/synthesis.md`.
- Any retry-with-model-help on parse failure.
- HTTP clients, databases, model APIs.
- Per-provider reviewer subclasses.
