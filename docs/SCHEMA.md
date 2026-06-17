# SCHEMA.md

Each reviewer is prompted to emit a single JSON object matching the shape below.
The orchestrator's parser targets this shape; reviewers that emit prose or
malformed JSON are stored as `unstructured` and shown to the human at
checkpoint 2 (one bad reviewer never aborts the run).

## Review object

```json
{
  "summary": "string, 1-3 sentences",
  "findings": [
    {
      "id": "string, stable slug within this review (e.g. \"missing-error-path\")",
      "severity": "blocker | major | minor | nit",
      "category": "correctness | design | security | performance | clarity | scope | other",
      "location": "string, optional. File/section reference from the plan.",
      "title": "string, one line",
      "body": "string, markdown allowed",
      "suggestion": "string, optional. Concrete change."
    }
  ],
  "open_questions": ["string", "..."],
  "verdict": "ship | iterate | reject"
}
```

### Rules

- `summary`, `findings`, `verdict` are required. `open_questions` may be empty
  but must be present.
- `findings` may be an empty array. Reviewers must not invent findings to fill
  space.
- `severity` is one of the four enums above. No custom values.
- `id` must be unique within a single review. It is used to cross-reference
  findings in the synthesizer's output.
- The reviewer MUST return exactly one JSON object as stdout. Markdown fences
  around it are tolerated; surrounding prose is not (it will demote the review
  to `unstructured`).

## Synthesis object

The synthesizer reads all N review objects (plus any `unstructured` raw text)
and emits:

```json
{
  "summary": "string, 2-4 sentences",
  "findings": [
    {
      "title": "string",
      "body": "string, markdown allowed",
      "severity": "blocker | major | minor | nit",
      "tag": "consensus | contested | singleton",
      "sources": ["reviewer_id", "..."],
      "disagreement": "string, optional. Required when tag = contested."
    }
  ],
  "verdict": "ship | iterate | reject",
  "verdict_rationale": "string"
}
```

### Tagging rules (enforced by the synthesis prompt, not by code)

- `consensus`: raised by 2+ reviewers with substantively the same point.
- `contested`: raised by 1+ reviewer and explicitly disputed (or contradicted)
  by another. `disagreement` must summarize the split.
- `singleton`: raised by exactly one reviewer, not contradicted by others.
  Kept because a lone reviewer is often right; the human decides.

## Parser tolerance

Order of attempts:

1. Strip leading/trailing whitespace and ```json / ``` fences.
2. `json_decode` strict.
3. On failure, store the raw text as `{"unstructured": true, "raw": "..."}`
   and continue.

The parser does NOT attempt to repair JSON, ask a model to fix it, or
heuristically extract fields. Fix the prompt instead.
