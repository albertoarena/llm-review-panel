# DESIGN.md

Architecture and runtime behavior of the orchestrator. CLAUDE.md carries the
one-line invariants; this file has the detail you need when working on the
pipeline itself.

## Separation of concerns

Config loading, prompt assembly, process running, result parsing, synthesis, and
CLI/IO are distinct concerns with no god classes. Current layout:

- `src/Config/` — load and validate `config.json`, resolve paths.
- `src/Prompt/` — assemble review and synthesis prompts, build command lines
  (`{prompt}`/`{model}` substitution, `prompt_via` arg vs stdin).
- `src/Process/` — `ProcessRunner` interface with `FakeProcessRunner` (tests) and
  `RealProcessRunner` (async spawn + poll).
- `src/Result/` — tolerant parser and `result_path` extraction.
- `src/Pipeline/` — end-to-end orchestration.
- `src/Console/` — CLI command, checkpoints behind a `QuestionProvider`.
- `src/Persistence/` — write the run directory.

Process spawning is isolated behind one class so it can be faked in tests. Do NOT
spawn real LLM processes in the test suite; inject the fake runner.

## Flow

```
llm-review-panel review <plan.md> [--config path] [--yes] [--dry-run]
  1. Load config.json + rubric file. Assemble review prompt (rubric + plan).
  2. CHECKPOINT 1: print assembled prompt + list of enabled reviewers.
     Prompt user: continue / abort. (--yes skips all checkpoints.)
     If --dry-run: print each enabled reviewer's fully resolved command line
     (placeholders substituted, prompt elided) and exit. No processes spawned.
  3. Fan out: spawn each enabled reviewer in parallel (respect max_parallel).
     Each reviewer's stdout is captured, result text extracted per result_path.
  4. CHECKPOINT 2: render side-by-side summary of raw reviews.
     Prompt user: continue / re-run a specific reviewer / abort.
  5. Synthesis: spawn the configured synthesizer reviewer with all N reviews as
     input + the synthesis prompt. Tagging of findings as
     consensus/contested/singleton is the synthesizer model's job (done via the
     synthesis prompt), NOT orchestrator code. Do not write a PHP differ.
  6. CHECKPOINT 3: print consolidated review.
     Prompt user: accept (write to output) / iterate / abort.
  7. Persist run to <output_dir>/<timestamp>/.
```

Checkpoints are interactive by default; only `--yes` skips them. Checkpoint 2's
"re-run reviewer <id>" replaces that one result and leaves the others intact; it
does not re-run everything.

`--dry-run` is the supported way to validate config and CLI availability. It must
resolve every placeholder and report any reviewer whose `command` is not on
`PATH`. No processes are spawned.

## Path resolution

Paths in `config.json` (`rubric_file`, `synthesizer.prompt_file`, `output_dir`)
are resolved **relative to the config file's directory**, not CWD. This keeps
runs reproducible regardless of where the CLI is invoked from.

## Stdin size limit

Claude Code caps piped stdin at 10MB. For large plans, write the plan to a temp
file and reference its path in the prompt rather than inlining the whole thing.
Prefer inlining for normal-sized plans; fall back to file-reference above a
configurable threshold.

## Read-only safety

Review runs must not let a reviewer modify the repo. For Claude Code, pass
`--allowedTools Read` (already in the example config). Keep this in mind when
adding flags for other agentic CLIs; each tool has its own read-only switch (see
`docs/CONFIG.md` for the per-tool notes).

## Run persistence layout

Each run writes `<output_dir>/<timestamp>/`:

- `prompt.md` — assembled review prompt.
- `reviews/<reviewer_id>.json` (or `.txt` when unstructured).
- `reviews/<reviewer_id>.stderr.log`.
- `synthesis.md`.
- `manifest.json` — config snapshot, per-reviewer status, durations.

stderr is captured to the run directory but not shown to the human unless a
reviewer fails. Per-reviewer failure taxonomy: `ok`, `unstructured`, `timeout`,
`nonzero_exit`, `empty_stdout`, `parse_error`.
