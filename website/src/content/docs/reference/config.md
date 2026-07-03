---
title: config.json schema
description: Complete reference for every field in config.json.
---

`config.json` lives at the repository root (or wherever `--config` points).
A `config.example.json` is committed; the real one is gitignored. All path
fields resolve **relative to the config file's directory**.

## Top-level fields

| Field          | Type   | Description                                                      |
|----------------|--------|------------------------------------------------------------------|
| `reviewers`    | array  | Reviewer entries. At least one must be enabled.                  |
| `synthesizer`  | object | Which reviewer synthesizes, and the synthesis prompt path.       |
| `rubric_file`  | string | Path to the markdown rubric injected into every review prompt.   |
| `output_dir`   | string | Where run artifacts are written. Default `.aireview/runs`.       |
| `max_parallel` | int    | Max reviewers running concurrently. Default 3.                   |

## Reviewer entry

| Field         | Type        | Description                                                                                                |
|---------------|-------------|------------------------------------------------------------------------------------------------------------|
| `id`          | string      | Unique id. Used in output filenames and to reference the synthesizer.                                       |
| `enabled`     | bool        | If false, skipped entirely.                                                                                |
| `command`     | string      | Binary to run (must be on `PATH`).                                                                          |
| `args`        | string[]    | Argument template. May contain `{prompt}` and `{model}` tokens.                                             |
| `prompt_via`  | `arg`/`stdin` | How the prompt reaches the process. `arg`: substituted into `{prompt}`. `stdin`: piped in.                |
| `model`       | string/null | Substituted into `{model}`. Only used if `{model}` appears in `args`.                                       |
| `result_path` | string/null | Dot-path into a single-object JSON stdout. `null` = use entire stdout as text. **No array indexing.**       |
| `timeout`     | int         | Seconds before the process is killed and recorded as failed.                                               |
| `paid`        | bool        | Optional, default `false`. Marks the reviewer as per-token billed; drives a warning at checkpoint 1.        |

## Placeholder rules

- `{prompt}` is replaced with the assembled review (or synthesis) prompt when `prompt_via` is `arg`. When `prompt_via` is `stdin`, do NOT put `{prompt}` in `args`; the prompt is piped in.
- `{model}` is replaced with the entry's `model`. If `args` has no `{model}` token, `model` is ignored.

## result_path semantics

Assumes stdout is a **single JSON object**. For CLIs that emit JSONL or
streaming events, either configure the tool's non-streaming flag, or set
`result_path: null` and treat the stream as the review text. The
orchestrator will NOT collapse JSONL into a single value.

Dot-path navigation supports nested keys (`data.text`). It does NOT support
array indexing, in either bracket (`choices[0].text`) or numeric-segment
(`choices.0.text`) form, and will refuse such paths with an error. Keys that
merely contain digits (`data.item2`) are fine; a segment that is entirely
digits is treated as an index and rejected.

## Synthesizer

```json
{
  "synthesizer": {
    "reviewer_id": "claude",
    "prompt_file": "config/synthesis.md"
  }
}
```

`reviewer_id` must match a configured reviewer. The same process-running
machinery is reused; only the prompt differs.

The synthesizer may be a **disabled** reviewer. A disabled reviewer is not a
panel voice but can still synthesize, giving you a "neutral arbiter": a model
that consolidates the reviews without also judging its own review. `--dry-run`
PATH-checks the synthesizer even when it is disabled, and checkpoint 1 names it
(flagging it when it is an out-of-panel arbiter).
