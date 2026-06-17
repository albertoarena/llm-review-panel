<?php

declare(strict_types=1);

namespace LlmReviewPanel\Prompt;

use InvalidArgumentException;

final class PromptAssembler
{
    private const SCHEMA_BLOCK = <<<'TXT'
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
TXT;

    public function assembleReview(string $rubric, string $plan): string
    {
        $rubric = $this->normalize($rubric);
        $plan = $this->normalize($plan);

        $schema = self::SCHEMA_BLOCK;

        return <<<TXT
{$rubric}

---
PLAN TO REVIEW
---

{$plan}

---
OUTPUT CONTRACT
---

Return a single JSON object matching this shape:

{$schema}

Wrap nothing around the JSON. Markdown fences are tolerated but not required.
TXT;
    }

    /**
     * @param  list<ReviewerOutput>  $outputs
     */
    public function assembleSynthesis(string $instructions, array $outputs): string
    {
        if ($outputs === []) {
            throw new InvalidArgumentException('synthesis requires at least one reviewer output');
        }

        $instructions = $this->normalize($instructions);

        $blocks = array_map(function (ReviewerOutput $o): string {
            $format = $o->unstructured ? 'unstructured' : 'json';

            return "[reviewer: {$o->reviewerId}, format: {$format}]\n".$this->normalize($o->content);
        }, $outputs);

        $body = implode("\n\n", $blocks);

        return <<<TXT
{$instructions}

---
REVIEWS TO SYNTHESIZE
---

{$body}
TXT;
    }

    private function normalize(string $value): string
    {
        return rtrim($value, "\r\n");
    }
}
