<?php

declare(strict_types=1);

namespace LlmReviewPanel\Pipeline;

use LlmReviewPanel\Prompt\ReviewerOutput;

final readonly class RunResult
{
    /**
     * @param  list<ReviewerOutput>  $reviews
     */
    public function __construct(
        public array $reviews,
        public ?string $synthesis,
    ) {
    }
}
