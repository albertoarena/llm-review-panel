<?php

declare(strict_types=1);

namespace LlmReviewPanel\Process;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

final class RealProcessRunner implements ProcessRunner
{
    private const POLL_INTERVAL_US = 25_000;

    public function runBatch(array $specs, int $maxParallel): array
    {
        $maxParallel = max(1, $maxParallel);
        $queue = $specs;
        /** @var array<string, array{spec: ProcessSpec, process: Process}> $running */
        $running = [];
        /** @var array<string, ProcessResult> $results */
        $results = [];

        while ($queue !== [] || $running !== []) {
            while (count($running) < $maxParallel && $queue !== []) {
                $spec = array_shift($queue);
                $process = $this->start($spec, $results);
                if ($process !== null) {
                    $running[$spec->id] = ['spec' => $spec, 'process' => $process];
                }
            }

            if ($running === []) {
                continue;
            }

            usleep(self::POLL_INTERVAL_US);

            foreach ($running as $id => $entry) {
                $process = $entry['process'];
                try {
                    $process->checkTimeout();
                } catch (ProcessTimedOutException) {
                    $results[$id] = new ProcessResult(
                        id: $id,
                        exitCode: $process->getExitCode() ?? -1,
                        stdout: $process->getOutput(),
                        stderr: $process->getErrorOutput(),
                        timedOut: true,
                    );
                    unset($running[$id]);

                    continue;
                }

                if (! $process->isRunning()) {
                    $results[$id] = new ProcessResult(
                        id: $id,
                        exitCode: $process->getExitCode() ?? -1,
                        stdout: $process->getOutput(),
                        stderr: $process->getErrorOutput(),
                    );
                    unset($running[$id]);
                }
            }
        }

        return $results;
    }

    /**
     * @param  array<string, ProcessResult>  $results
     */
    private function start(ProcessSpec $spec, array &$results): ?Process
    {
        $process = new Process(
            command: [$spec->command, ...$spec->args],
            input: $spec->stdin,
            timeout: (float) $spec->timeout,
        );

        try {
            $process->start();
        } catch (Throwable $e) {
            $results[$spec->id] = new ProcessResult(
                id: $spec->id,
                exitCode: -1,
                stdout: '',
                stderr: $e->getMessage(),
            );

            return null;
        }

        return $process;
    }
}
