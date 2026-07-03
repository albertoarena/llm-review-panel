# CLAUDE.md

Guidance for Claude Code (and other agents) working on this repository.

## What this is

`llm-review-panel` is a standalone PHP CLI that runs the same code/plan review
across multiple LLM command-line tools (Claude Code, OpenCode, Gemini, etc.) in
parallel, then synthesizes their independent reviews into one balanced result. It
automates a manual workflow: ask several different LLMs to review the same
markdown plan, then compare viewpoints instead of trusting one model. The human
stays in the loop at three checkpoints.

## Stack

- PHP **8.3+** (hard floor; matrix 8.3/8.4/8.5). Strict types, PSR-12.
- Symfony Console + Symfony Process **only**. No Laravel, no framework.
- Pest for tests, Laravel Pint for formatting.

## Commands

```bash
vendor/bin/pest                          # run tests
vendor/bin/pint                          # format
vendor/bin/pint --test                   # check formatting (CI gate)
bin/llm-review-panel review <plan.md>    # run a review
bin/llm-review-panel review <plan.md> --dry-run   # validate config + PATH, no spawn
```

## Hard constraints (every change must honor)

- **No API calls.** Reviewers are local CLI binaries the user already installed
  and authenticated (Claude Code under subscription auth, OpenCode against local
  Ollama, etc.). The tool spawns processes; it never makes HTTP calls to model
  APIs. No API keys, no HTTP client to model APIs, no database.
- **Config-driven, single generic reviewer.** Adding a reviewer is a
  `config.json` edit, never a code change. ONE generic CLI-reviewer path driven
  by config; no per-provider subclasses. Do not hardcode model names, paths, or
  reviewer lists in PHP.
- **Reviewers run in parallel** via async `Process::start()` + polling, capped at
  `max_parallel`.
- **One failing reviewer never aborts the run.** Bad output is stored as
  `unstructured` and shown at checkpoint 2; other reviewers continue.
- **Synthesizer tags findings**, not code. consensus/contested/singleton tagging
  is done by the synthesizer model via `config/synthesis.md`. Do not write a PHP
  differ.
- **Read-only reviews.** Pass each agentic CLI its read-only switch (Claude Code:
  `--allowedTools Read`). See `docs/DESIGN.md`.
- **Checkpoints stay interactive** by default; only `--yes` skips them.

## Flow (summary)

`review <plan.md> [--config] [--yes] [--dry-run]`: load config + rubric → assemble
prompt → **checkpoint 1** (confirm) → fan out reviewers in parallel → **checkpoint
2** (raw reviews; re-run one / abort) → synthesize → **checkpoint 3** (accept /
iterate / abort) → persist to `<output_dir>/<timestamp>/`. Full step-by-step,
persistence layout, and failure taxonomy in `docs/DESIGN.md`.

Config paths (`rubric_file`, `synthesizer.prompt_file`, `output_dir`) resolve
**relative to the config file's directory**, not CWD.

## Conventions

- Use 8.3+ features freely: typed class constants, readonly classes.
- **TDD is the default**: write the failing test first, then the code, then
  refactor. New behavior without a preceding failing test is a smell; the
  review-time question is "where's the red commit?"
- Process spawning is faked in tests. Do NOT spawn real LLM processes in the
  suite; inject the fake runner.
- KISS and clean separation of concerns; no god classes. See `docs/DESIGN.md`.
- No em dashes in code comments, docs, or output. No marketing language in README
  or help text.

## Git commit conventions

- Format: `type: short subject` (max 50 chars), then a body paragraph explaining
  what and why (not how). Use a heredoc for multi-line messages.
- **No Claude attribution.** Never include "Generated with Claude Code" or
  "Co-Authored-By: Claude".

## Where the detail lives

- `docs/DESIGN.md` — architecture, full flow, checkpoints, path resolution, stdin
  limit, read-only safety, persistence layout, failure taxonomy.
- `docs/CONFIG.md` — full `config.json` schema, placeholder rules, per-reviewer
  recipes and cost notes.
- `docs/SCHEMA.md` — reviewer + synthesis JSON output contract and parser
  tolerance strategy.
- `docs/IMPLEMENTATION.md` — phased build plan and out-of-scope list.
- `docs/CI.md` — CI jobs/matrix, deploy workflow, README badge requirements.
- `website/CLAUDE.md` — documentation-site rules (Astro Starlight). **Rule: a
  user-facing behavior change must update `/website` in the same PR.**
