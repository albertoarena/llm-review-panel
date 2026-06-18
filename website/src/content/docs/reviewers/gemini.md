---
title: Gemini CLI
description: Run Google's Gemini CLI. Generous free tier, paid above it.
---

**Auth:** Google account; free tier generous (around 1,000 requests/day at time of writing).
**Cost:** free under the daily quota, per-token above.
**Output:** plain text.

## Install

See https://github.com/google-gemini/gemini-cli. Authenticate via the
`gemini` command's standard flow.

## Config entry

```json
{
  "id": "gemini",
  "enabled": false,
  "command": "gemini",
  "args": ["-m", "{model}", "-p", "{prompt}"],
  "prompt_via": "arg",
  "model": "gemini-2.5-pro",
  "result_path": null,
  "timeout": 300,
  "paid": true
}
```

## Notes

- `result_path: null` because Gemini CLI prints plain text by default.
- `paid: true` is the safe default since heavy use exceeds the free tier. Flip to `false` if you're confident your runs stay under quota.
- Pick whichever Gemini model variant suits your latency vs. quality trade-off.
