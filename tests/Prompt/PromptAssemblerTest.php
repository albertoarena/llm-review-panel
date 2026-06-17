<?php

declare(strict_types=1);

use LlmReviewPanel\Prompt\PromptAssembler;
use LlmReviewPanel\Prompt\ReviewerOutput;

beforeEach(function (): void {
    $this->assembler = new PromptAssembler();
});

it('assembles a review prompt from rubric and plan', function (): void {
    $rubric = "# Rubric\n\nLook for bugs.";
    $plan = "# Plan\n\nDo the thing.";

    $prompt = $this->assembler->assembleReview($rubric, $plan);

    $expected = <<<'TXT'
# Rubric

Look for bugs.

---
PLAN TO REVIEW
---

# Plan

Do the thing.

---
OUTPUT CONTRACT
---

Return a single JSON object matching this shape:

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

Wrap nothing around the JSON. Markdown fences are tolerated but not required.
TXT;

    expect($prompt)->toBe($expected);
});

it('assembles a synthesis prompt from instructions and reviewer outputs', function (): void {
    $instructions = "# Synthesis\n\nTag each finding as consensus, contested, or singleton.";
    $outputs = [
        new ReviewerOutput('claude', '{"summary":"all good","verdict":"ship"}', false),
        new ReviewerOutput('opencode', 'this reviewer returned prose, not JSON', true),
    ];

    $prompt = $this->assembler->assembleSynthesis($instructions, $outputs);

    $expected = <<<'TXT'
# Synthesis

Tag each finding as consensus, contested, or singleton.

---
REVIEWS TO SYNTHESIZE
---

[reviewer: claude, format: json]
{"summary":"all good","verdict":"ship"}

[reviewer: opencode, format: unstructured]
this reviewer returned prose, not JSON
TXT;

    expect($prompt)->toBe($expected);
});

it('throws when synthesis receives no reviewer outputs', function (): void {
    $this->assembler->assembleSynthesis('# anything', []);
})->throws(InvalidArgumentException::class, 'at least one');
