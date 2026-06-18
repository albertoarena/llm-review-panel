---
title: Running your first review
description: A worked example of reviewing a real implementation plan end-to-end.
---

This walks through reviewing a small implementation plan with a two-reviewer
panel (Claude Code + OpenCode against Ollama). Adapt as needed for whatever
panel you've configured.

## 1. Write the plan

Save the following as `plan.md`:

````markdown
# Plan: rate-limit the `/login` endpoint

We see brute-force attempts on `/login`. Add per-IP rate limiting at the
controller level: 5 attempts per IP per minute, return 429 after that.

Steps:

1. Install `symfony/rate-limiter` (already in vendor).
2. Configure a rate limiter named `login` in `framework.yaml` with the policy
   `fixed_window`, limit 5, interval `1 minute`.
3. In `LoginController::login`, fetch the limiter for the request IP, consume
   one token, return 429 if blocked.
4. Add a feature test that hits `/login` six times and asserts the 6th is 429.

No frontend changes. No new database tables.
````

## 2. Dry run first

```bash
bin/llm-review-panel review plan.md --dry-run
```

Output should list each enabled reviewer with `found on PATH`. Fix any
missing binaries before continuing.

## 3. Run interactively

```bash
bin/llm-review-panel review plan.md
```

At each checkpoint, take time to read the output before answering. The
default `[Y/n]` is biased toward continuing — slow down if something
deserves attention.

### What you'll see

**Checkpoint 1** lists enabled reviewers and warns if any are paid. The
prompt preview is elided to 80 chars; the full assembled prompt is in
`prompt.md` in the run directory once you continue.

**Checkpoint 2** shows each raw review with its status. You can re-run a
single reviewer (`rerun:opencode-ollama`) if its output looked bad without
re-running the others. Useful when a local model has a bad day.

**Checkpoint 3** prints the synthesizer's output. Read it as a real review;
the synthesizer is allowed to disagree with individual reviewers.

## 4. Inspect the run directory

On accept:

```
.aireview/runs/2026-06-17T14-32-08/
├── prompt.md                     # the exact prompt every reviewer saw
├── reviews/
│   ├── claude.json               # parsed JSON from Claude
│   ├── claude.stderr.log         # whatever Claude wrote to stderr
│   ├── opencode-ollama.txt       # OpenCode's prose output (unstructured)
│   └── opencode-ollama.stderr.log
├── synthesis.md                  # the consolidated review
└── manifest.json                 # config snapshot, statuses, durations
```

`manifest.json` is worth reading. It records every reviewer's status,
duration in ms, and any failure reason. It also snapshots the config that
produced this run, so if you change `config.json` later you can still
reproduce what happened.

## 5. Iterate

If the synthesis looked generic, the rubric is the first thing to edit, not
the reviewer list. See [Writing a good rubric](/llm-review-panel/guides/writing-rubrics/).
