---
title: Aider
description: Aider in one-shot --message mode, pointed at Ollama or a cloud model.
---

**Auth:** depends on the backing model. Ollama is free and local; cloud models need their respective keys.
**Cost:** free with Ollama; per-token with cloud models.
**Output:** plain text.

## Install

```bash
pip install aider-chat
```

See https://aider.chat for full instructions and supported model providers.

## Config entry (Ollama)

```json
{
  "id": "aider-ollama",
  "enabled": false,
  "command": "aider",
  "args": [
    "--model", "{model}",
    "--message", "{prompt}",
    "--no-auto-commit",
    "--yes",
    "--no-stream"
  ],
  "prompt_via": "arg",
  "model": "ollama/deepseek-coder-v2",
  "result_path": null,
  "timeout": 600,
  "paid": false
}
```

## Notes

- `--message` runs Aider one-shot non-interactively.
- `--no-auto-commit --yes --no-stream` keep it from touching git, prompting, or streaming partial output that mangles capture.
- `ollama/` (or `ollama_chat/`) prefix routes through your local Ollama.
- Switch the `model` value and flip `paid` to point at any cloud provider Aider supports.
