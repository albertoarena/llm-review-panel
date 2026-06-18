---
title: Claude Code
description: Run Claude Code as a reviewer under your Pro or Max subscription.
---

**Auth:** Pro/Max subscription via `claude login` (no API key in this repo).
**Cost:** counts against your subscription plan limits.
**Output:** JSON envelope.

## Install

See the official docs at https://docs.claude.com/en/docs/agents-and-tools/claude-code/. After install:

```bash
claude login
```

Confirm with `claude -p "hello" --output-format json` and check you get a
`result` field back.

## Config entry

```json
{
  "id": "claude",
  "enabled": true,
  "command": "claude",
  "args": ["-p", "{prompt}", "--output-format", "json", "--allowedTools", "Read"],
  "prompt_via": "arg",
  "model": null,
  "result_path": "result",
  "timeout": 300,
  "paid": false
}
```

## Notes

- `result_path: "result"` matches Claude Code's JSON envelope.
- `model` is `null` because the subscription should not be forced to a specific model. Don't add `{model}` to args here.
- `--allowedTools Read` keeps the reviewer from modifying your repo.
- `paid: false` reflects that this is covered by your subscription; runs still count against plan limits.
