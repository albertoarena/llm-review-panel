# llm-review-panel

A PHP CLI that runs the same review across multiple LLM command-line tools
(Claude Code, OpenCode, Gemini CLI, Codex, Aider, Qwen) in parallel, then
synthesizes their independent reviews into one consolidated result.

It exists to automate a manual workflow: ask several different LLMs to review
the same markdown plan, then compare viewpoints instead of trusting one model.

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

- PHP 8.2+
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

- `CLAUDE.md` — constraints and flow (for agents working on this repo)
- `docs/CONFIG.md` — config schema and supported reviewers
- `docs/SCHEMA.md` — reviewer JSON output contract
- `docs/IMPLEMENTATION.md` — phased build plan

## Status

In progress. Spec is settled; implementation has not started.
