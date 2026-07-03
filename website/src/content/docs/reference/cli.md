---
title: CLI options
description: Every flag the review command accepts.
---

import { Aside } from '@astrojs/starlight/components';

## Synopsis

```bash
bin/llm-review-panel review <plan.md> [--config <path>] [--yes] [--dry-run] [--poll-interval <ms>]
```

## Arguments

| Argument | Description                                          |
|----------|------------------------------------------------------|
| `plan`   | Path to the markdown plan to review (required).     |

## Options

| Option         | Default        | Description                                                                 |
|----------------|----------------|-----------------------------------------------------------------------------|
| `--config, -c` | `config.json`  | Path to the `config.json` to load.                                          |
| `--yes, -y`    | off            | Skip all three checkpoints.                                                 |
| `--dry-run`    | off            | Resolve every enabled reviewer's command, check each binary against `PATH`, and exit. No processes spawned. |
| `--poll-interval` | `poll_interval_ms` (25) | Process poll interval in milliseconds. Overrides `poll_interval_ms` from config for this run. Must be a positive integer. |

## Exit codes

| Code | Meaning                                                                                       |
|------|-----------------------------------------------------------------------------------------------|
| `0`  | Run completed successfully (synthesis written, or user accepted at checkpoint 3, or aborted at a checkpoint). |
| `1`  | `--dry-run` found a missing binary, **or** no reviewer produced usable output (manifest written; synthesis skipped). |

<Aside type="note">
Aborting at a checkpoint is treated as a successful run with no synthesis,
not an error. `--dry-run` failures and total reviewer failure both return
non-zero so they're catchable from scripts.
</Aside>

## Examples

```bash
# Validate config and PATH without spawning anything
bin/llm-review-panel review plan.md --dry-run

# Interactive run with checkpoints
bin/llm-review-panel review plan.md

# Scripted run, no prompts
bin/llm-review-panel review plan.md --yes

# Use an alternate config file
bin/llm-review-panel review plan.md -c configs/audit.json
```
