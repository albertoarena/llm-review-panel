---
title: OpenCode + Ollama
description: Run OpenCode pointed at a local Ollama model. Free, offline, often the most independent voice on the panel.
---

**Auth:** none (local).
**Cost:** free.
**Output:** plain text.

## Install

```bash
# 1. install Ollama and pull a model
curl https://ollama.ai/install.sh | sh
ollama pull qwen2.5-coder:14b

# 2. install OpenCode (see https://opencode.ai for current instructions)
```

Confirm with:

```bash
opencode run -m ollama/qwen2.5-coder:14b "hello"
```

## Config entry

```json
{
  "id": "opencode-ollama",
  "enabled": true,
  "command": "opencode",
  "args": ["run", "-m", "{model}", "{prompt}"],
  "prompt_via": "arg",
  "model": "ollama/qwen2.5-coder:14b",
  "result_path": null,
  "timeout": 600,
  "paid": false
}
```

## Notes

- `result_path: null` because OpenCode prints the answer as plain text.
- Longer `timeout` because local inference is slower than hosted.
- Pick whatever Ollama-served model you prefer. Larger code-tuned models give better reviews; 14B is a reasonable starting point.
- This is often the most useful reviewer to keep on the panel even when you have hosted ones, because it brings a genuinely different model family.
