---
title: Iterating on the synthesis prompt
description: Tuning the synthesis prompt so the consolidated review captures real disagreement.
---

The synthesis prompt (`config/synthesis.md`) shapes how reviewer outputs
are merged. Most failure modes are about the synthesizer doing too little
or too much.

## Failure mode 1: silently picking a winner

You see two reviewers disagree on a finding, but the synthesis shows it as
`consensus` for one side, with no mention of the other. The synthesizer
collapsed the disagreement.

**Fix:** strengthen the language in the synthesis prompt around `contested`
findings. The default version tells the model "do not water down
disagreement" and "do not pick a winner silently" — these instructions are
load-bearing.

## Failure mode 2: dropping singletons

A reviewer raised a real issue; no other reviewer mentioned it; the
synthesis omits it entirely. The synthesizer treated absence of agreement
as absence of merit.

**Fix:** explicitly require singletons to be kept. Default rubric: "A lone
reviewer is often right; the human will decide whether to act on them."

## Failure mode 3: inventing findings

The synthesis includes a finding that traces to no source reviewer. The
synthesizer started reviewing the plan itself instead of merging.

**Fix:** require `sources` on every finding and remind the synthesizer it
is merging, not reviewing. The PHP parser does not enforce this; the
prompt has to.

## Failure mode 4: padding

The synthesis has many `minor`/`nit` findings that feel manufactured. The
synthesizer treated empty arrays as a failure mode.

**Fix:** explicitly allow empty `findings`. Permit `ship` with one or zero
findings.

## Testing changes

Use a run directory from an interesting past run (with disagreement
across reviewers) as a fixture. Edit `synthesis.md`. Re-run synthesis only
by:

1. Pointing a one-off config at the same reviewers but disabling them
   (you don't want to re-run the reviewer step).
2. (Future work) The orchestrator does not currently expose a "re-run
   synthesis only" mode. For now, just re-run the full pipeline and
   accept the redundant reviewer calls.

When that feature exists, link it here.
