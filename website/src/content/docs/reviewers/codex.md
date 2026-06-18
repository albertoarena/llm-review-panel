---
title: Codex CLI
description: Run OpenAI's Codex CLI in non-interactive exec mode.
---

**Auth:** ChatGPT subscription or per-token API key.
**Cost:** per-token unless covered by your ChatGPT plan.
**Output:** plain text (with `--json` for streamed events).

## Install

See https://github.com/openai/codex. After install, run `codex login` or set
the `OPENAI_API_KEY` env var per the project's instructions.

## Config entry

```json
{
  "id": "codex",
  "enabled": false,
  "command": "codex",
  "args": ["exec", "--json", "--sandbox", "read-only", "{prompt}"],
  "prompt_via": "arg",
  "model": null,
  "result_path": null,
  "timeout": 300,
  "paid": true
}
```

## Notes

- `codex exec` is the non-interactive subcommand. `--sandbox read-only` is the default for exec and keeps the reviewer from modifying the repo.
- `--json` emits a JSONL event stream rather than a single JSON object. `result_path: null` uses the raw stream as the review text; the synthesizer can still extract useful content from it. If that proves noisy, switch to a non-streaming flag.
- `paid: true` triggers the checkpoint-1 warning. Disable for routine runs and re-enable when you want the full panel.
