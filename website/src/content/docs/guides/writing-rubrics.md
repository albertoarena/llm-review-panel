---
title: Writing a good rubric
description: How to shape your rubric so reviewers stop giving generic feedback.
---

The rubric (`config/rubric.md`) determines what every reviewer looks for.
A weak rubric produces a fancier version of the same vague advice you got
from one model. A good rubric forces reviewers into specific, actionable
findings.

## Symptoms of a weak rubric

- Findings reference "best practices" without naming the practice.
- "Consider adding tests" without specifying what to test.
- "This could be more robust" — robust against what?
- Every reviewer hits the same generic dimensions (logging, error handling, documentation).
- The synthesis is mostly `consensus` findings that are all generic.

## What helps

**Name dimensions, not virtues.** "Correctness" is a dimension. "Quality" is a
virtue. Reviewers can evaluate a plan against named dimensions; they can't
evaluate it against "be good."

**Tell reviewers what NOT to do.** The default rubric explicitly forbids
generic best-practice advice, style nits, praise padding, and counter-plans.
The forbiddings often improve output more than the encouragements.

**Calibrate severity.** Reviewers default to high severity when uncertain.
If everything is `blocker`, nothing is. The default rubric defines `blocker`
narrowly so reviewers have to actually justify it.

**Demand concreteness.** Findings should reference a specific part of the
plan. The default rubric tells reviewers to quote what they're reacting to.
Vague findings are noise even when they're technically correct.

**Match the rubric to the kind of plan.** A plan to add a feature wants
different scrutiny than a plan to audit existing code. You can keep
multiple rubrics and point `rubric_file` at different ones via separate
`config.json` files passed with `--config`.

## How to iterate

1. Run the panel against a plan you already understand well.
2. Read the synthesis. Where is it wrong? Where is it vague?
3. Edit the rubric to either prohibit the noise or demand the specificity.
4. Re-run the **same** plan and compare. Iteration on the same input is
   how you tune the rubric; iteration across different plans confuses
   signal with input variance.
5. Repeat until findings feel like things a sharp colleague would say.

A good rubric is short. The default is intentionally trimmed: every line
exists because removing it made output worse. Resist the urge to keep
adding dimensions.
