---
title: Qwen Code
description: Run Qwen Code in JSON output mode. Now paid after the OAuth free tier was discontinued.
---

import { Aside } from '@astrojs/starlight/components';

**Auth:** Alibaba Cloud plan, OpenRouter, Fireworks, or self-hosted key. The free OAuth tier was discontinued in April 2026.
**Cost:** per-token.
**Output:** JSON envelope (`response` field).

<Aside type="caution">
If you're following old Qwen Code docs from before April 2026, the free
OAuth path no longer works. You will need to point it at a paid provider.
</Aside>

## Install

See https://github.com/QwenLM/qwen-code for current setup. Configure your
provider of choice per their docs.

## Config entry

```json
{
  "id": "qwen",
  "enabled": false,
  "command": "qwen",
  "args": ["-p", "{prompt}", "--output-format", "json"],
  "prompt_via": "arg",
  "model": null,
  "result_path": "response",
  "timeout": 300,
  "paid": true
}
```

## Notes

- `result_path: "response"` extracts the answer text from Qwen's JSON envelope. Adjust if your provider wraps it differently.
- `paid: true` triggers the checkpoint-1 warning.
