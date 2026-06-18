---
title: JSON output schema
description: The JSON shape each reviewer and the synthesizer should emit. Tolerant of fences and surrounding whitespace.
---

Both the rubric and synthesis prompts instruct the model to return JSON
matching the shapes below. The parser is tolerant of fenced JSON
(```` ```json ... ``` ````) and surrounding whitespace; prose-wrapped JSON
is **not** repaired and falls through as unstructured.

## Review object (per reviewer)

```json
{
  "summary": "string, 1-3 sentences",
  "findings": [
    {
      "id": "string, stable slug within this review",
      "severity": "blocker | major | minor | nit",
      "category": "correctness | design | security | performance | clarity | scope | other",
      "location": "string, optional",
      "title": "string, one line",
      "body": "string, markdown allowed",
      "suggestion": "string, optional"
    }
  ],
  "open_questions": ["string"],
  "verdict": "ship | iterate | reject"
}
```

Rules:

- `summary`, `findings`, `verdict` are required. `open_questions` may be an empty array but must be present.
- `findings` may be empty. Reviewers should not invent findings to fill space.
- `severity` is one of the four enums; no custom values.
- `id` must be unique within a single review.

## Synthesis object

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
      "disagreement": "string, required when tag = contested"
    }
  ],
  "verdict": "ship | iterate | reject",
  "verdict_rationale": "string"
}
```

Tagging rules are enforced by the synthesis **prompt**, not by code:

- `consensus` — raised by 2+ reviewers with substantively the same point.
- `contested` — raised by one and disputed by another; `disagreement` must summarize the split.
- `singleton` — raised by exactly one reviewer, not contradicted; kept.

## Parser tolerance

Order of attempts when reading reviewer stdout:

1. Strip leading/trailing whitespace and ` ```json ` / ` ``` ` fences.
2. `json_decode` strict.
3. On failure, the output is stored as **unstructured** (raw text) and
   still shown to the human at checkpoint 2.

The parser does NOT attempt to repair JSON, ask a model to fix it, or
heuristically extract fields. Fix the prompt instead.
