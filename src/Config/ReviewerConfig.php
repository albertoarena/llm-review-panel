<?php

declare(strict_types=1);

namespace LlmReviewPanel\Config;

final readonly class ReviewerConfig
{
    public const PROMPT_VIA_ARG = 'arg';

    public const PROMPT_VIA_STDIN = 'stdin';

    /**
     * @param  list<string>  $args
     */
    public function __construct(
        public string $id,
        public bool $enabled,
        public string $command,
        public array $args,
        public string $promptVia,
        public ?string $model,
        public ?string $resultPath,
        public int $timeout,
    ) {
    }
}
