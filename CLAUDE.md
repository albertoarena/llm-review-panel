# CLAUDE.md

Guidance for Claude Code (and other agents) working on this repository.

## What this is

`llm-review-panel` is a standalone PHP CLI that orchestrates code/plan reviews
across multiple LLM command-line tools (Claude Code, OpenCode, Gemini CLI, etc.),
then synthesizes their independent reviews into one consolidated, balanced result.

It exists to automate a currently-manual workflow: ask several different LLMs to
review the same markdown plan, then compare their viewpoints to get a balanced
review instead of trusting a single model.

The human stays in the loop at three checkpoints (see Flow).

## Non-negotiable constraints

- **Standalone CLI, not a framework app.** Symfony Console + Symfony Process only.
  Do NOT pull in Laravel. This is a single-purpose orchestration tool.
- **No API keys, no paid API calls in the tool itself.** Reviewers are local CLI
  binaries the user already has installed and authenticated (e.g. Claude Code runs
  under the user's Pro/Max subscription auth; OpenCode points at a local Ollama
  model). The tool spawns processes; it never makes HTTP calls to model APIs.
- **Config-driven reviewers.** Adding a new reviewer (Gemini, etc.) must be a
  `config.json` edit, NOT a code change. There is ONE generic CLI-reviewer class
  driven entirely by config. Do not create one class per provider.
- **Reviewers run in parallel** via async `Process::start()` + polling, capped at
  `max_parallel`.

## Flow

```
llm-review-panel review <plan.md> [--config path] [--yes] [--dry-run]
  1. Load config.json + rubric file. Assemble review prompt (rubric + plan).
  2. CHECKPOINT 1: print assembled prompt + list of enabled reviewers.
     Prompt user: continue / abort. (--yes skips all checkpoints.)
     If --dry-run: print each enabled reviewer's fully resolved command line
     (placeholders substituted, prompt elided) and exit. No processes spawned.
  3. Fan out: spawn each enabled reviewer in parallel (respect max_parallel).
     Each reviewer's stdout is captured, result text extracted per result_path.
  4. CHECKPOINT 2: render side-by-side summary of raw reviews.
     Prompt user: continue / re-run a specific reviewer / abort.
  5. Synthesis: spawn the configured synthesizer reviewer with all N reviews as
     input + the synthesis prompt. Tagging of findings as
     consensus/contested/singleton is the synthesizer model's job (done via the
     synthesis prompt), NOT orchestrator code. Do not write a PHP differ.
  6. CHECKPOINT 3: print consolidated review.
     Prompt user: accept (write to output) / iterate / abort.
  7. Persist run to <output_dir>/<timestamp>/.
```

`--dry-run` is the supported way to validate config and CLI availability. It
must resolve every placeholder and report any reviewer whose `command` is not
on `PATH`.

## Config model

Single `config.json` (a `config.example.json` is committed; real `config.json` is
gitignored). See `docs/CONFIG.md` for the full schema. Key ideas the implementation
must honor:

- Each reviewer entry is a command template. `args` is an array containing
  placeholder tokens `{prompt}` and optionally `{model}`, substituted at spawn time.
- `prompt_via`: `"arg"` (prompt substituted into `{prompt}` in args) or `"stdin"`
  (prompt piped to the process; `{prompt}` absent from args).
- `model`: optional. Only injected when the entry's `args` actually contain
  `{model}`. **Do not force a model onto every reviewer** — Claude Code under a
  subscription does not take an arbitrary model the way OpenCode/Gemini do.
- `result_path`: dot-path into the process's JSON stdout to extract the review text
  (e.g. `"result"` for Claude Code's JSON envelope). If `null`, treat entire stdout
  as the result text (plain-text CLIs like OpenCode/Gemini). **`result_path`
  assumes stdout is a single JSON object.** For CLIs that emit JSONL/streaming
  events (e.g. Codex `exec --json`), either configure the tool's non-streaming
  flag, or set `result_path: null` and accept the raw stream as the review.
  The orchestrator will NOT collapse JSONL into text; that belongs in the
  reviewer's own flags.
- `enabled`: boolean. Ship defaults enabled only for the most common tools; others
  present but disabled so the repo works for people who lack a given CLI.

`config.example.json` ships entries for claude, opencode (Ollama), codex, gemini,
aider (Ollama), and qwen. Only claude and opencode-ollama are enabled by default;
the rest are present-but-disabled examples. See `docs/CONFIG.md` for the verified
flags and cost notes per tool. The implementation does NOT special-case any of these;
they all flow through the single generic CLI reviewer. They exist as ready-to-toggle
examples, nothing more.

## Reviewer output contract

Each reviewer is prompted to return JSON matching the schema in
`docs/SCHEMA.md`. Parsing MUST be tolerant: models sometimes wrap JSON in
markdown fences or emit prose. Strategy:
1. Strip ```json / ``` fences.
2. Attempt JSON parse.
3. On failure, store the raw text as an `unstructured` review (still shown to the
   human at checkpoint 2) rather than crashing the whole run. One bad reviewer
   must never abort the others.

## Stdin size limit

Claude Code caps piped stdin at 10MB. For large plans, write the plan to a temp
file and reference its path in the prompt rather than inlining the whole thing.
Prefer inlining for normal-sized plans; fall back to file-reference above a
configurable threshold.

## Read-only safety

Review runs must not let a reviewer modify the repo. For Claude Code, pass
`--allowedTools Read` (already in the example config). Keep this in mind if adding
flags for other agentic CLIs.

## Conventions

- PHP 8.2+, strict types, PSR-12.
- Pest for tests. **TDD is the default**: write the failing test first, then the
  code to pass it, then refactor. New behavior without a preceding failing test
  is a smell; review-time question is "where's the red commit?"
- Laravel Pint for formatting (works fine outside Laravel; pulled in as a dev
  dependency). Run `vendor/bin/pint` before committing. CI should fail on
  unformatted code.
- KISS and clean separation: config loading, prompt assembly, process running,
  result parsing, synthesis, and CLI/IO are distinct concerns. No god classes.
- Process spawning isolated behind one class so it can be faked in tests (do NOT
  spawn real LLM processes in the test suite — inject a fake process runner).
- No em dashes in code comments, docs, or output. Direct, plain prose.
- No marketing language in README or help text.

## What NOT to do

- Do not add Laravel, an HTTP client to model APIs, or a database.
- Do not create per-provider reviewer subclasses.
- Do not hardcode model names, paths, or reviewer lists in PHP. Config only.
- Do not make checkpoints non-interactive by default (only `--yes` skips them).
- Do not let one failing reviewer crash the run.

## Prerequisite files (must exist before implementation starts)

CLAUDE.md and CONFIG.md reference four files that do not yet exist. Create them
before writing any PHP, in this order:

1. **`docs/SCHEMA.md`** — the JSON schema each reviewer is prompted to emit.
   Defines the shape the tolerant parser targets.
2. **`docs/IMPLEMENTATION.md`** — the phased build plan referenced from "Build
   order" below. One phase per concern (config loader, prompt assembly, fake
   process runner, real process runner, synthesis, CLI/IO, checkpoints).
3. **`config/rubric.md`** — the rubric injected into every review prompt.
   Quality of this file is where most of the tool's value lives; do not treat
   it as a stub.
4. **`config/synthesis.md`** — the synthesis prompt. Must instruct the
   synthesizer to tag findings as consensus/contested/singleton (since the
   orchestrator does not do this in code).

Paths in `config.json` (`rubric_file`, `synthesizer.prompt_file`, `output_dir`)
are resolved **relative to the config file's directory**, not CWD. This keeps
runs reproducible regardless of where the user invokes the CLI from.

## Build order

See `docs/IMPLEMENTATION.md` for the phased plan. Build and test each phase before
moving on. Start with config + prompt assembly + a faked process runner so the
whole pipeline is testable before any real CLI is invoked.

## Git Commit Conventions

### Format
- type: short subject line (max 50 chars)
- Detailed body paragraph explaining what and why (not how).

### Rules
- No Claude attribution - NEVER include "Generated with Claude Code" or "Co-Authored-By: Claude"
- Keep first line under 50 characters
- Use heredoc for multi-line commit messages
