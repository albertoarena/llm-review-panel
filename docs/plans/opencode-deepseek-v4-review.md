# Review: llm-review-panel

Reviewer: OpenCode (DeepSeek V4 Free) | Date: 2026-07-03

---

## Verdict: ship with recommendations

This is a well-architected, thoroughly tested PHP CLI that does exactly what it sets out to do. The design philosophy is consistent, the code is clean, and the documentation is comprehensive. There are no blockers, but several concrete improvements would harden it for production use.

---

## What is solid

### Architecture
- Clean separation of concerns across 7 namespaces, no god classes.
- `ProcessRunner` interface with `FakeProcessRunner` for testing and `RealProcessRunner` for production — textbook dependency inversion.
- Config-driven reviewer model: adding a reviewer is a config edit, never a code change. The constraint is honored fully.
- Symfony Process used for async spawning with polling; no HTTP calls, no API keys.
- Human-in-the-loop checkpoints with a `QuestionProvider` interface that makes the full flow testable.

### Code quality
- PHP 8.3+ features used well: `readonly` classes, enums, typed class constants, `match` expressions.
- Strict types, PSR-12 via Pint, no unused imports.
- No magic strings leaking between layers; domain concepts (status, prompt modes) are enums and constants.

### Test coverage
- 30+ Pest tests covering config validation, prompt assembly, process running (both fake and real), result parsing, full pipeline orchestration, CLI checkpoints, and persistence.
- Edge cases well covered: timeouts, nonzero exits, empty stdout, unstructured output, disabled reviewers, re-runs, aborts.
- Real process tests use `printf`, `false`, `sleep` — no real LLM dependencies.
- TDD approach is evident from the test structure.

### Documentation
- `docs/DESIGN.md` — thorough architecture and flow, including stdin limits and read-only safety.
- `docs/CONFIG.md` — complete schema with per-reviewer recipes, cost notes, placeholder rules, verified tool flags (dated June 2026).
- `docs/SCHEMA.md` — exact JSON contracts with parser tolerance strategy.
- `docs/IMPLEMENTATION.md` — phased build plan with clear "done when" criteria.
- `docs/CI.md` — workflow detail, badge setup, token gotchas.
- `CLAUDE.md` — excellent agent guidance with load-bearing constraints.
- Astro Starlight website with proper sidebar groups, Starlight components, and a staleness rule.

### Resilience
- One failing reviewer never aborts the run. Failures are classified (timeout, nonzero exit, empty stdout) and recorded.
- Synthesizer gracefully returns `null` when no reviewer produced usable output.
- Persistence writes stderr logs, content files, and a manifest regardless of partial failures.

---

## Where it fails / issues

### 1. Stdin size limit not implemented (design debt)
`docs/DESIGN.md` explicitly acknowledges Claude Code's 10MB stdin cap and describes a fallback strategy ("write to temp file and reference path"), but no implementation exists. For large plans, the prompt is always inlined. If a plan + rubric + schema exceeds ~10MB, the synthesizer (which also receives all reviews inline) will silently fail.

**Fix:** Implement a `max_inline_bytes` threshold; when exceeded, write the prompt to a temp file and pass the file path instead.

### 2. Polling interval is wasteful for long-running reviewers
`RealProcessRunner` polls every 25ms (40 Hz). LLM CLI calls take 60-600 seconds. This burns CPU checking processes that are almost certainly still running. At 3 reviewers running for 5 minutes, that is ~36,000 syscalls per run.

**Fix:** Use adaptive polling: start at 100ms, double to 1s after the first check, settle at 500ms-1s. Or use `Process::wait()` with a timeout loop.

### 3. Synthesizer reviewer_id is not validated as enabled
`ConfigLoader::parseSynthesizer` validates that `synthesizer.reviewer_id` matches a reviewer *entry*, but does not check it is enabled. If a user points the synthesizer at a disabled reviewer, the pipeline will fail at synthesis time with a confusing error.

**Fix:** In `parseSynthesizer`, when checking `in_array($reviewerId, $known, true)`, also check the matched reviewer is enabled, or at minimum emit a warning.

### 4. Checkpoint 2 re-run UX is tedious for multiple failures
If 3 of 5 reviewers fail, the user must re-run them one at a time — there is no "re-run all failed" option. Each re-run cycles through the full checkpoint loop.

**Fix:** Add a `rerun:failed` choice, or allow selecting multiple reviewer IDs.

### 5. Dot-path parser rejects array indexing but numeric keys still break silently
`assertNoArrayIndexing` checks for `[` / `]`, but a numeric segment like `data.0.text` traverses `$cursor[0]` via `array_key_exists`, which also happens to work — but the doc says "no array indexing." The rejection is incomplete: `choices.0.message` passes the check but silently works if the PHP array key exists.

**Fix:** Either implement proper array indexing (parse `choices[0].text`) or reject numeric segments in `dotPath` consistently.

### 6. `RunPersister::synthesisFilename` parses JSON content to decide the extension
The method calls `json_decode(trim($content), true)` on the synthesis output, then picks `.json` or `.md`. This means the persister must parse what the synthesizer produced just to pick a filename. If the synthesis is large, this is wasted work.

**Fix:** Have the pipeline pass a flag indicating whether the synthesis is JSON or prose, rather than re-parsing it.

### 7. `commandExists` uses `shell_exec` with `command -v`
This is a shell-syntax dependency. It works on POSIX but is fragile: `shell_exec` is often disabled in hardened PHP installs (`disable_functions`). The `--dry-run` check would silently report all commands as missing.

**Fix:** Use `symfony/process` itself to run `command -v <binary>`, which is consistent with the project's own process runner. Or check `is_executable` on resolved paths from `$PATH`.

### 8. Config schema diverges between code and website docs (minor)
The `SCHEMA_BLOCK` hardcoded in `PromptAssembler` and the synthesis instructions in `config/synthesis.md` define the actual contracts. The website mirrors `docs/SCHEMA.md`. These three sources must stay synchronized, but there is no automated check. If one is updated but others are not, the prompt instructs one shape while docs document another.

**Fix:** Consider generating `SCHEMA_BLOCK` from a single source, or add a CI check that the reviewer schema in `PromptAssembler` matches `docs/SCHEMA.md`.

### 9. `Rubric_file` and `synthesizer.prompt_file` existence is not checked at `prepare` time
`ReviewPipeline::prepare()` reads them via `readFile()`, which will throw if they don't exist. This is fine for the error message, but there is no validation that the files contain valid markdown or non-empty content.

**Fix:** Acceptable as-is (fail-fast is appropriate), but a more helpful error message could suggest running `--dry-run`.

### 10. No support for prompt caching or continuation
If a reviewer's output is truncated (e.g., hits a token limit), the result is stored as-is. There is no mechanism to recognize truncation or prompt for continuation. This is noted as out-of-scope, but worth mentioning as a limitation.

---

## What I would improve (by priority)

| Priority | Change | Effort | Impact |
|----------|--------|--------|--------|
| High | Adaptive polling interval in `RealProcessRunner` | 1 hour | Reduces CPU waste during long runs |
| High | Validate synthesizer reviewer is enabled | 15 min | Prevents confusing runtime errors |
| Medium | `commandExists` via Symfony Process | 30 min | Works in hardened PHP environments |
| Medium | Batch "re-run all failed" at checkpoint 2 | 1 hour | Better UX for multi-reviewer panels |
| Medium | Stdin size limit fallback | 2 hours | Prevents silent failures on large plans |
| Low | Config schema synchronization CI check | 1 hour | Prevents doc drift |
| Low | Numeric segment handling in dot-path | 30 min | Consistency with documented constraint |
| Low | Synthesis filename detection from pipeline, not parser | 30 min | Avoids unnecessary JSON parse |

---

## Summary

**Solid project.** The architecture is clean, tests are excellent, documentation is thorough, and the design constraints (no API calls, config-driven, no per-provider code) are honored without exception. The issues identified are refinements, not fundamental problems. With the high-priority improvements (adaptive polling, enabled-synthesizer check, `commandExists` hardening), this would be production-grade.

---

# Counter-review (Claude Opus 4.8, 2026-07-03)

Every code-level claim below was verified against the source. Overall the review
is competent and its architecture read is accurate, but its prioritization is
inverted: both items it ranked **High** are its weakest (one factually wrong, one
trivial), while the genuinely useful findings sit at Medium/Low. Two entries are
self-admitted non-issues.

## Verdict per issue

| # | Claim | Verdict | Decision |
|---|-------|---------|----------|
| 1 | Stdin limit documented, not implemented | Valid but overstated | Skip (known limitation) |
| 2 | 25ms polling burns CPU (High) | Real, severity inflated | Possibly (trivial tweak) |
| 3 | Synthesizer not validated as enabled (High) | **Incorrect premise** | To be done (Option A) |
| 4 | No "re-run all failed" at checkpoint 2 | Valid enhancement | To be done |
| 5 | Dot-path rejects `[]` but `foo.0.bar` works | Valid, minor | To be done |
| 6 | `synthesisFilename` re-parses JSON | Valid, trivial | Skip |
| 7 | `commandExists` uses `shell_exec` | Valid, low-risk | To be done |
| 8 | Schema defined in 3 unsynced places | Valid, low | Skip (over-engineering) |
| 9 | rubric/prompt existence unvalidated | Non-issue (self-admitted) | Skip |
| 10 | No truncation/continuation detection | Non-issue (out of scope) | Skip |

## Notes on the incorrect / inflated items

- **#3 is wrong on the runtime facts.** It claims a disabled synthesizer makes
  the pipeline "fail at synthesis time with a confusing error." It does not.
  `ReviewPipeline::runSynthesis` resolves the synthesizer via `findReviewer`
  (`src/Pipeline/ReviewPipeline.php:159`), which scans **all** reviewers, not
  just enabled ones. A disabled synthesizer therefore runs fine. The codebase
  deliberately keeps two lookups: `findReviewer` (all) and `findEnabledReviewer`
  (enabled only). The real question is design intent, not a bug. See discussion
  below.

- **#2 severity is inflated.** `POLL_INTERVAL_US = 25_000`
  (`src/Process/RealProcessRunner.php:13`) is confirmed, but `usleep` yields the
  CPU; a handful of cheap syscalls at 40 Hz over minutes is negligible, not
  "High". A bump to ~100-250ms is a one-line tweak, worth doing only for tidiness.

- **#1 "silently fail" is wrong.** An oversized spawn is recorded as
  `nonzero_exit`, not silent. And for `prompt_via: arg` the binding limit is
  `ARG_MAX`, not the 10MB stdin cap the review cites.

## What the review missed

- The `findReviewer` vs `findEnabledReviewer` distinction (the actual behavior
  behind #3). It invented a failure instead of tracing the code.
- Test count: it says "30+ Pest tests"; there are **84**. It eyeballed the tree
  rather than running the suite, which is also why it got #3's behavior wrong.

## Action list (agreed)

- **To be done:**
  - **#3 (Option A):** a disabled synthesizer is allowed and intended (it is the
    "neutral arbiter" pattern: synthesize without being a panel voice). Document
    it in `CONFIG.md`, and make `--dry-run` / checkpoint 1 PATH-check the
    synthesizer's command even when it is disabled (closes the only real gap:
    today a disabled synthesizer's binary is never validated by dry-run).
  - **#7:** harden `commandExists` via a PATH / `is_executable` scan, drop
    `shell_exec`.
  - **#4:** "re-run all failed" at checkpoint 2.
  - **#5:** document or reject numeric dot-path segments.
- **Possibly:** #2 (raise the poll interval).
- **Skip:** #1, #6, #8, #9, #10.

Any behavior change (notably #4's new checkpoint option) must ship with a matching
`/website` update in the same commit, per `website/CLAUDE.md`.
