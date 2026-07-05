# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Compatibility surface (what a version number applies to) and the release process
are defined in [`docs/RELEASING.md`](docs/RELEASING.md).

## [Unreleased]

### Added

- `init` command: scaffold `config.json` plus the default `config/rubric.md`
  and `config/synthesis.md` into a directory (defaults to the current one).
  Refuses to overwrite existing files without `--force`.

## [0.1.0] - 2026-07-04

Initial release. Runs one review across multiple local LLM CLI tools in
parallel and synthesizes their outputs, with three human checkpoints.

### Added

- `review <plan.md>` command: assemble prompt from rubric + plan, fan out
  reviewers in parallel, synthesize, and persist the run.
- Config-driven reviewers: adding a reviewer is a `config.json` edit, one
  generic CLI-reviewer path (no per-provider code).
- Parallel execution via async `Process::start()` + polling, capped at
  `max_parallel`; a single failing reviewer never aborts the run.
- Synthesizer that tags findings as consensus / contested / singleton via
  `config/synthesis.md`; optional arbiter step (may be disabled).
- Three interactive checkpoints (confirm prompt, review raw outputs, accept
  synthesis); `--yes` skips them.
- `--dry-run` prints the resolved reviewer commands without spawning anything.
- `--config` to point at an alternate config file; paths resolve relative to
  the config file's directory.
- "Re-run all failed" option at checkpoint 2.
- Configurable process poll interval.
- Read-only reviews: each agentic CLI is passed its read-only switch.
- Run persistence to `<output_dir>/<timestamp>/`.
- Tolerant JSON parser for reviewer and synthesis output; malformed output is
  stored as `unstructured` and surfaced at checkpoint 2.
- Astro Starlight documentation site under `/website`.

[Unreleased]: https://github.com/albertoarena/llm-review-panel/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/albertoarena/llm-review-panel/releases/tag/v0.1.0
