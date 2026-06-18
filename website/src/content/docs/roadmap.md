---
title: Roadmap
description: Things we'd like to try. Open ideas, not commitments.
---

A short list of things on the maybe-pile. Nothing here is promised. Open
an issue if any of these matter to you.

## Likely

- **Homebrew distribution.** Ship a single-file PHAR built with
  [box-project/box](https://github.com/box-project/box), released on
  every tag via GitHub Actions, then install via a tap:
  `brew install albertoarena/tap/llm-review-panel`. The PHAR keeps PHP as
  the only system dep; the tap formula declares it. Hold off until the
  tool has been used for real for a couple of weeks so we don't lock 0.0.x
  decisions into a package manager.
- **Re-run synthesis only.** When you want to iterate on the synthesis
  prompt without re-paying for reviewer calls, replay an existing run
  directory's reviewer outputs through a new synthesis prompt.
- **Per-finding diff in `manifest.json`.** Track which findings appear in
  multiple reviews vs. only one, alongside the synthesizer's tagging.
  Useful for noticing when the synthesizer disagrees with what the
  evidence suggests.
- **JSONL-aware result_path.** A way to extract the meaningful event from
  a streaming JSONL stdout without setting `result_path: null` and getting
  a noisy raw stream.
- **Cost estimation at checkpoint 1.** Estimate token cost per enabled
  reviewer based on prompt size, before spawning. Right now we only warn
  that paid reviewers are enabled.

## Maybe

- **A "judge" reviewer.** A separate panel pass that scores each reviewer's
  output against the rubric, so consistently-weak reviewers can be
  identified and dropped.
- **Per-plan rubrics.** Inline `<!-- rubric: path -->` in the plan to
  override the configured rubric for that run.
- **Output formats other than JSON.** YAML for human inspection,
  structured for tooling. Currently JSON is the only contract.

## No

- **Calling model APIs directly.** The tool will not become an HTTP client
  to OpenAI, Anthropic, Google, etc. Reviewers are local CLIs. This is
  load-bearing for the project's identity.
- **A web UI.** It's a CLI. Run it from CI if you want automation.
- **Editing the plan automatically based on findings.** That's a different
  product.

## Ideas welcome

The tool is small enough that anyone can read the code in an afternoon and
hack on it. Reasonable PRs welcome; reasonable issues welcome too.
