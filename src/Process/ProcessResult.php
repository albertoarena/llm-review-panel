<?php

declare(strict_types=1);

namespace LlmReviewPanel\Process;

final readonly class ProcessResult
{
    public function __construct(
        public string $id,
        public int $exitCode,
        public string $stdout,
        public string $stderr,
        public bool $timedOut = false,
        public int $durationMs = 0,
    ) {
    }

    public function succeeded(): bool
    {
        return ! $this->timedOut && $this->exitCode === 0;
    }
}
