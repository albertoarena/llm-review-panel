<?php

declare(strict_types=1);

namespace LlmReviewPanel\Process;

final readonly class ProcessSpec
{
    /**
     * @param  list<string>  $args
     */
    public function __construct(
        public string $id,
        public string $command,
        public array $args,
        public ?string $stdin,
        public int $timeout,
    ) {
    }
}
