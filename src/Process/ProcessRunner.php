<?php

declare(strict_types=1);

namespace LlmReviewPanel\Process;

interface ProcessRunner
{
    /**
     * @param  list<ProcessSpec>  $specs
     * @return array<string, ProcessResult>
     */
    public function runBatch(array $specs, int $maxParallel, int $pollIntervalUs = 25_000): array;
}
