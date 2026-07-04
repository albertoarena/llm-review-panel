---
title: Changelog and releases
description: How versions are numbered and where to find release notes.
---

Release notes live in two places, both generated from the same source:

- [GitHub Releases](https://github.com/albertoarena/llm-review-panel/releases) —
  the published release for each version.
- [`CHANGELOG.md`](https://github.com/albertoarena/llm-review-panel/blob/main/CHANGELOG.md)
  in the repository, in [Keep a Changelog](https://keepachangelog.com) format.

You can check the version you have installed with:

```bash
bin/llm-review-panel --version
```

## Versioning

The project follows [Semantic Versioning](https://semver.org). The version
describes the tool's public contract, which is the CLI and the config schema,
not the internal PHP classes. A version bump means one of:

- **Major** — a flag or command is removed or changes meaning, or a breaking
  change to the `config.json` schema, the persisted run layout, or the JSON
  output contract.
- **Minor** — additive and backward compatible: new flags, new opt-in behavior,
  additive config keys, new reviewer support.
- **Patch** — bug fixes, docs, and internal changes with no user-visible effect.

While the version is `0.x` the config schema and CLI are still settling, so
breaking changes ship as minor bumps until `1.0.0`.

The full policy and the compatibility surface are in
[`docs/RELEASING.md`](https://github.com/albertoarena/llm-review-panel/blob/main/docs/RELEASING.md).
