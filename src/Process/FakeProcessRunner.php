<?php

declare(strict_types=1);

namespace LlmReviewPanel\Process;

final class FakeProcessRunner implements ProcessRunner
{
    /** @var array<string, ProcessResult> */
    private array $scripted = [];

    /** @var list<ProcessSpec> */
    private array $invocations = [];

    public function script(
        string $id,
        string $stdout = '',
        string $stderr = '',
        int $exitCode = 0,
        bool $timedOut = false,
    ): void {
        $this->scripted[$id] = new ProcessResult(
            id: $id,
            exitCode: $exitCode,
            stdout: $stdout,
            stderr: $stderr,
            timedOut: $timedOut,
        );
    }

    public function runBatch(array $specs, int $maxParallel): array
    {
        $results = [];
        foreach ($specs as $spec) {
            $this->invocations[] = $spec;
            if (! isset($this->scripted[$spec->id])) {
                throw new ProcessException("FakeProcessRunner: no scripted response for '{$spec->id}'");
            }
            $results[$spec->id] = $this->scripted[$spec->id];
        }

        return $results;
    }

    /**
     * @return list<ProcessSpec>
     */
    public function invocations(): array
    {
        return $this->invocations;
    }
}
