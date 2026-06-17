<?php

declare(strict_types=1);

namespace LlmReviewPanel\Pipeline;

use LlmReviewPanel\Config\Config;

final readonly class PreparedRun
{
    public function __construct(
        public Config $config,
        public string $reviewPrompt,
        public string $synthesisInstructions,
    ) {
    }
}
