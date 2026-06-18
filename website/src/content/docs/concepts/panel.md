---
title: The review panel
description: What a panel is, why composition matters more than count.
---

A **panel** is the set of enabled reviewers in your `config.json`. Each
reviewer reads the same plan independently, produces its own review, and
those reviews are merged by the synthesizer into a single consolidated
output.

## Why more than one reviewer

One LLM reviewing its own family's output is not a review. It tends to
agree, miss the same blind spots, and pattern-match to the same training
biases. A single Claude reviewer giving a `ship` verdict tells you Claude
thinks the plan is shippable; it doesn't tell you whether the plan is
actually sound.

The value of a panel comes from **genuine disagreement** between models that
were trained differently. When three independent reviewers from different
families agree on a finding, that finding is real. When they disagree, the
synthesizer flags it as `contested` and you (the human) decide.

## Composition advice

- **Mix model families**, not wrappers. Two Claude-based tools and one
  Gemini gives you one Claude review plus one Gemini review, not three
  reviews.
- **At least three reviewers** to get usable consensus signal. Two reviewers
  can only agree or disagree, with no tiebreaker.
- **Include a local model** (OpenCode + Ollama, Aider + Ollama) if you can.
  It's free, runs offline, and produces genuinely different reviews than
  hosted models.
- **Avoid stacking paid reviewers** for routine runs. Reserve the full
  panel for plans that warrant the cost.

## Failure isolation

One reviewer crashing, timing out, or returning unparseable output never
aborts the run. The failed reviewer is recorded in `manifest.json` and
excluded from synthesis; the others continue. See
[How synthesis works](/llm-review-panel/concepts/synthesis/) for what
counts as "usable."
