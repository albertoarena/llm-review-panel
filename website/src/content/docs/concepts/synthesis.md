---
title: How synthesis works
description: From N independent reviews to one consolidated output, tagged by agreement.
---

After every enabled reviewer has produced an output, the synthesizer reads
them all and emits a single consolidated review. The synthesizer is one of
your configured reviewers (pointed at by `synthesizer.reviewer_id`),
re-invoked with a different prompt. It can be a disabled reviewer, in which
case it acts as a neutral arbiter that consolidates the panel without being
one of its voices (see the [config reference](/reference/config/)).

## What the synthesizer does

The synthesis prompt (in `config/synthesis.md`) instructs the model to:

1. **Collect distinct findings** across all reviewers. Two reviewers
   saying the same thing in different words is one finding, not two.
2. **Tag each finding** as:
   - `consensus` — raised by 2+ reviewers with substantively the same point.
   - `contested` — raised by one and explicitly disputed by another.
     The synthesis includes a `disagreement` field summarizing the split.
   - `singleton` — raised by exactly one reviewer, not contradicted.
     Kept because lone reviewers are often right.
3. **Pick a verdict** (`ship` / `iterate` / `reject`) and explain it.
4. **Write a short summary** of the plan's overall shape.

## What the synthesizer does NOT do

- It does not silently pick a winner when reviewers disagree. Contested
  findings are surfaced as contested.
- It does not re-review the plan. If a finding wasn't in any source
  review, the synthesizer is instructed not to add it.
- It does not invent agreement. If only one reviewer raised something, it
  stays a `singleton`.
- The orchestrator (the PHP code) does not do diffing or tagging itself.
  That's the synthesizer model's job, driven by the synthesis prompt.

## What gets fed to the synthesizer

Only reviewers with status `ok` (parsed JSON) or `unstructured` (returned
prose, used as-is) are passed to synthesis. Reviewers that timed out,
exited non-zero, or produced empty stdout are recorded in `manifest.json`
but excluded from the synthesis prompt so they don't pollute it.

If **no** reviewer produced usable output, synthesis is skipped entirely;
the run still persists the manifest so the failures are recorded.

## Why this works

The synthesizer being another LLM rather than a hand-written diff
algorithm means it can read prose, understand near-duplicates, and
distinguish "X is risky because of A" from "X is risky because of B" even
when both reviewers used the word "risky." No structured-matching
algorithm in PHP would catch that.

The flip side is that the synthesizer inherits the bias of whichever
reviewer plays that role. Using Claude as the synthesizer of a Claude +
OpenCode + Gemini panel tilts the consensus toward Claude's framing. Worth
keeping in mind when reading the output.
