<?php

declare(strict_types=1);

namespace LlmReviewPanel\Config;

final readonly class SynthesizerConfig
{
    public function __construct(
        public string $reviewerId,
        public string $promptFile,
    ) {
    }
}
