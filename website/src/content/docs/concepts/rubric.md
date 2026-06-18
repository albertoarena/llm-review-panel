---
title: The rubric
description: What the rubric is, what it shapes, and why its quality dominates the tool's value.
---

The **rubric** is the markdown file every reviewer reads before reviewing
your plan. It tells the reviewer what to look for, what tone to use, what
to ignore, and what the output should look like.

It lives at the path `rubric_file` points to (the shipped default is
`config/rubric.md`).

## What the default rubric covers

Eight evaluation dimensions:

1. **Correctness** — does the approach actually solve the stated problem?
2. **Simplicity** — anti-overengineering. A three-line fix shouldn't become a framework.
3. **Scope discipline** — does the plan sneak in unrelated cleanups?
4. **Hidden assumptions** — most plans fail at the assumption layer, not the code layer.
5. **Missing failure modes** — empty, malformed, concurrent, malicious inputs.
6. **Testability** — can we tell if it worked? Concrete tests, not "add tests."
7. **Reversibility and blast radius** — revert-the-PR vs. data-migration vs. legal-call.
8. **Actionability** — every finding should be concrete enough to act on.

Plus tone rules (direct, specific, calibrated, economical), explicit
anti-patterns (no style nits, no generic best-practice advice, no praise
padding, no counter-proposals), a severity calibration guide, and the
verdict criteria.

## Why the rubric matters more than the code

The PHP orchestrator is straightforward: spawn processes, capture output,
merge results. The quality of what you get out of `llm-review-panel` lives
almost entirely in the rubric and synthesis prompts. A weak rubric
produces a fancier version of single-model noise.

If you find your runs returning generic feedback ("consider improving
error handling", "this could use more tests"), the rubric is the first
thing to edit, not the code.

See [Writing a good rubric](/llm-review-panel/guides/writing-rubrics/) for
how to iterate on it.
