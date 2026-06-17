<?php

declare(strict_types=1);

namespace LlmReviewPanel\Prompt;

final readonly class ReviewerOutput
{
    public function __construct(
        public string $reviewerId,
        public string $content,
        public bool $unstructured,
    ) {
    }
}
