# CONFIG.md

`config.json` drives everything. Copy `config.example.json` to `config.json` and
edit. `config.json` is gitignored; `config.example.json` is committed.

## Top-level fields

| Field          | Type   | Description                                                      |
|----------------|--------|------------------------------------------------------------------|
| `reviewers`    | array  | Reviewer entries (see below). At least one must be enabled.      |
| `synthesizer`  | object | Which reviewer synthesizes, and the synthesis prompt file.       |
| `rubric_file`  | string | Path to the markdown rubric injected into every review prompt.   |
| `output_dir`   | string | Where run artifacts are written. Default `.aireview/runs`.       |
| `max_parallel` | int    | Max reviewers running concurrently. Default 3.                   |

## Reviewer entry

| Field         | Type        | Description                                                                                  |
|---------------|-------------|----------------------------------------------------------------------------------------------|
| `id`          | string      | Unique id. Used in output filenames and to reference the synthesizer.                         |
| `enabled`     | bool        | If false, skipped entirely.                                                                   |
| `command`     | string      | Binary to run (must be on PATH).                                                              |
| `args`        | array       | Argument template. May contain `{prompt}` and `{model}` tokens.                               |
| `prompt_via`  | `arg`/`stdin` | How the prompt reaches the process. `arg`: substituted into `{prompt}`. `stdin`: piped in.  |
| `model`       | string/null | Substituted into `{model}`. Only used if `{model}` appears in `args`.                         |
| `result_path` | string/null | Dot-path into JSON stdout to extract review text. `null` = use entire stdout as text.         |
| `timeout`     | int         | Seconds before the process is killed and recorded as failed.                                  |
| `paid`        | bool        | Optional, default `false`. Marks the reviewer as per-token billed. Drives a warning at checkpoint 1 so you know what an enabled panel will cost. |

## Placeholder rules

- `{prompt}` is replaced with the assembled review (or synthesis) prompt when
  `prompt_via` is `arg`. When `prompt_via` is `stdin`, do NOT put `{prompt}` in args;
  the prompt is piped to the process instead.
- `{model}` is replaced with the entry's `model`. If `args` has no `{model}` token,
  `model` is ignored. This is intentional: Claude Code under a subscription should not
  be forced to a model, so its entry simply omits the token.

## Examples

### Claude Code (subscription auth, JSON envelope, read-only)
```json
{
  "id": "claude",
  "enabled": true,
  "command": "claude",
  "args": ["-p", "{prompt}", "--output-format", "json", "--allowedTools", "Read"],
  "prompt_via": "arg",
  "model": null,
  "result_path": "result",
  "timeout": 300
}
```
`result_path` is `result` because Claude Code's JSON output puts the answer text
under the top-level `result` key.

### OpenCode against a local Ollama model (plain text out)
```json
{
  "id": "opencode-ollama",
  "enabled": true,
  "command": "opencode",
  "args": ["run", "-m", "{model}", "{prompt}"],
  "prompt_via": "arg",
  "model": "ollama/qwen2.5-coder:14b",
  "result_path": null,
  "timeout": 600
}
```
`result_path` is `null` because OpenCode prints the answer as plain text.
Higher timeout because local inference can be slow.

### Gemini CLI (disabled by default)
```json
{
  "id": "gemini",
  "enabled": false,
  "command": "gemini",
  "args": ["-m", "{model}", "-p", "{prompt}"],
  "prompt_via": "arg",
  "model": "gemini-2.5-pro",
  "result_path": null,
  "timeout": 300
}
```

### A CLI that prefers stdin
```json
{
  "id": "some-cli",
  "enabled": false,
  "command": "some-cli",
  "args": ["review", "--json"],
  "prompt_via": "stdin",
  "model": null,
  "result_path": "data.output",
  "timeout": 300
}
```

## Synthesizer
```json
{
  "synthesizer": {
    "reviewer_id": "claude",
    "prompt_file": "config/synthesis.md"
  }
}
```
`reviewer_id` must match an existing reviewer id. The same process-running
machinery is reused; only the prompt differs.

The synthesizer reviewer may be **disabled**. A disabled reviewer is not a panel
voice, but is still available to synthesize. This is the "neutral arbiter"
pattern: point `reviewer_id` at a strong model that does not otherwise sit in the
panel, so it consolidates the reviews without also judging its own review or
having its perspective counted twice. If the synthesizer is one of your enabled
reviewers it works too; just be aware it will be arbitrating a set that includes
its own output.

`--dry-run` validates the synthesizer's `command` is on `PATH` even when the
synthesizer is disabled, so a neutral arbiter cannot silently fail at synthesis
time. Checkpoint 1 names the synthesizer and flags it when it is an out-of-panel
arbiter.

## Supported reviewers (verified June 2026)

These are CLI agents known to work as config-driven reviewers. Flags were verified
against each tool's docs in June 2026; they shift between versions, so check the
tool's own `--help` if an invocation fails. `config.example.json` ships these
(common ones enabled, the rest present but disabled so the repo runs for everyone).

| id                | command    | model family | cost note                                                        |
|-------------------|------------|--------------|------------------------------------------------------------------|
| `claude`          | `claude`   | Claude       | Covered by Pro/Max subscription auth. Counts against plan limits. |
| `opencode-ollama` | `opencode` | any (local)  | Free. Points OpenCode at a local Ollama model.                   |
| `codex`           | `codex`    | GPT          | ChatGPT subscription or per-token API.                           |
| `gemini`          | `gemini`   | Gemini       | Generous free tier (1,000 req/day) then paid.                    |
| `aider-ollama`    | `aider`    | any (local)  | Free with a local Ollama model; paid if pointed at a cloud model.|
| `qwen`            | `qwen`     | Qwen         | Paid as of 2026-04-15 (OAuth free tier discontinued).            |

Notes on specific tools:

- **Claude Code**: JSON envelope, so `result_path` is `result`. Omit `{model}` so the
  subscription is not forced to a model. `--allowedTools Read` keeps it read-only.
- **Codex CLI**: `codex exec` is the non-interactive subcommand. `--json` gives
  machine-readable output and `--sandbox read-only` (the default for exec) keeps it
  from editing the repo during review. Prints final output to stdout, so a simple
  `result_path: null` works for most setups; switch to `--json` parsing if you want
  structured fields. Model via `-m` if you want to pin one.
- **Gemini CLI**: plain `-p` prompt, `-m` model. Plain-text stdout, so `result_path`
  is `null`.
- **Aider**: `--message` runs one-shot non-interactive. `--no-auto-commit` and
  `--yes` keep it from touching git or prompting; `--no-stream` gives clean captured
  output. Model uses the `ollama/` (or `ollama_chat/`) prefix for local models, or any
  provider Aider supports. Plain-text stdout, `result_path: null`.
- **Qwen Code**: `-p` prompt, `--output-format json`. Note the free OAuth tier was
  discontinued in April 2026, so this now needs an Alibaba Cloud plan, OpenRouter,
  Fireworks, or your own key. Left disabled by default for that reason.

Picking a panel: prefer mixing model *families* (e.g. Claude + Codex/GPT + Gemini +
a local Ollama model) over several wrappers around the same model. Reviewers from the
same family tend to agree, which gives false confidence. The value of the panel comes
from genuine disagreement between different models.

## Adding a new reviewer

1. Add an entry to `reviewers`. Pick an `id`.
2. Set `command`/`args` to the tool's non-interactive invocation. Put `{prompt}` where
   the prompt goes (or use `prompt_via: stdin`).
3. If the tool emits JSON, set `result_path` to the text field's dot-path. Otherwise
   `null`.
4. Set `enabled: true`. No code changes required.
