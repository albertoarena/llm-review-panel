# llm-review-panel

[![CI](https://github.com/albertoarena/llm-review-panel/actions/workflows/ci.yml/badge.svg)](https://github.com/albertoarena/llm-review-panel/actions/workflows/ci.yml)
[![Repo views](https://raw.githubusercontent.com/albertoarena/llm-review-panel/traffic-data/badge-views.svg)](https://github.com/albertoarena/llm-review-panel)
[![Repo clones](https://raw.githubusercontent.com/albertoarena/llm-review-panel/traffic-data/badge-clones.svg)](https://github.com/albertoarena/llm-review-panel)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777bb4)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

A PHP CLI that runs the same review across multiple LLM command-line tools
(Claude Code, OpenCode, Gemini CLI, Codex, Aider, Qwen) in parallel, then
synthesizes their independent reviews into one consolidated result.

It exists to automate a manual workflow: ask several different LLMs to review
the same markdown plan, then compare viewpoints instead of trusting one model.

**Documentation: https://albertoarena.github.io/llm-review-panel/**

## How it works

```
plan.md ──> [rubric + plan] ──> N reviewers in parallel ──> synthesizer ──> consolidated review
                                       │                         │
                                  checkpoint 2              checkpoint 3
```

Three human checkpoints: confirm the prompt, review raw outputs, accept the
synthesis. `--yes` skips them; `--dry-run` prints the resolved commands without
spawning anything.

## What it is not

- Not a framework app. Symfony Console + Symfony Process only.
- Not an API client. Reviewers are local CLI binaries you already have
  installed and authenticated. The tool spawns processes; it never makes HTTP
  calls to model APIs.
- Not per-provider code. One generic reviewer class, driven entirely by
  `config.json`. Adding a new reviewer is a config edit.

## Requirements

- PHP 8.3+
- Composer
- At least one reviewer CLI on `PATH` (Claude Code, OpenCode, etc.). See
  `docs/CONFIG.md` for the supported list.

## Quick start

```bash
composer install
cp config.example.json config.json
# edit config.json: enable the reviewers you have installed
bin/llm-review-panel review path/to/plan.md --dry-run   # verify config
bin/llm-review-panel review path/to/plan.md
```

## Docs

- `CLAUDE.md` — constraints and conventions (for agents working on this repo)
- `docs/DESIGN.md` — architecture, flow, checkpoints, persistence
- `docs/CONFIG.md` — config schema and supported reviewers
- `docs/SCHEMA.md` — reviewer JSON output contract
- `docs/IMPLEMENTATION.md` — phased build plan
- `docs/CI.md` — CI and release automation

## Status

Working. All build phases are implemented and tested (CI runs Pint plus Pest on
PHP 8.3/8.4/8.5). The tool runs end-to-end against real reviewer CLIs. Early
days: expect rough edges and pre-1.0 changes.
