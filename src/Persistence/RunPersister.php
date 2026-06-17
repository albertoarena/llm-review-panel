<?php

declare(strict_types=1);

namespace LlmReviewPanel\Persistence;

use LlmReviewPanel\Config\Config;
use LlmReviewPanel\Config\ReviewerConfig;
use LlmReviewPanel\Pipeline\PreparedRun;
use LlmReviewPanel\Prompt\ReviewerOutput;
use LlmReviewPanel\Prompt\ReviewerStatus;
use RuntimeException;

final class RunPersister
{
    /**
     * @param  list<ReviewerOutput>  $outputs
     */
    public function persist(PreparedRun $prepared, array $outputs, ?string $synthesis, string $timestamp): string
    {
        $runDir = $prepared->config->outputDir.'/'.$timestamp;
        $reviewsDir = $runDir.'/reviews';
        $this->mkdir($runDir);
        $this->mkdir($reviewsDir);

        $this->write($runDir.'/prompt.md', $prepared->reviewPrompt);

        foreach ($outputs as $output) {
            $this->writeReviewer($reviewsDir, $output);
        }

        if ($synthesis !== null) {
            $this->write($runDir.'/synthesis.md', $synthesis);
        }

        $manifest = $this->buildManifest($prepared->config, $outputs, $synthesis, $timestamp);
        $this->write($runDir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $runDir;
    }

    public static function timestamp(): string
    {
        return date('Y-m-d\TH-i-s');
    }

    private function writeReviewer(string $reviewsDir, ReviewerOutput $output): void
    {
        if ($output->stderr !== '') {
            $this->write($reviewsDir.'/'.$output->reviewerId.'.stderr.log', $output->stderr);
        }

        $extension = match ($output->status) {
            ReviewerStatus::Ok => 'json',
            ReviewerStatus::Unstructured => 'txt',
            default => null,
        };

        if ($extension !== null && $output->content !== '') {
            $this->write($reviewsDir.'/'.$output->reviewerId.'.'.$extension, $output->content);
        }
    }

    /**
     * @param  list<ReviewerOutput>  $outputs
     * @return array<string, mixed>
     */
    private function buildManifest(Config $config, array $outputs, ?string $synthesis, string $timestamp): array
    {
        return [
            'timestamp' => $timestamp,
            'synthesis_written' => $synthesis !== null,
            'reviewers' => array_map(
                static fn (ReviewerOutput $o): array => [
                    'id' => $o->reviewerId,
                    'status' => $o->status->value,
                    'duration_ms' => $o->durationMs,
                    'failure_reason' => $o->failureReason,
                ],
                $outputs,
            ),
            'config' => self::configSnapshot($config),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function configSnapshot(Config $config): array
    {
        return [
            'rubric_file' => $config->rubricFile,
            'output_dir' => $config->outputDir,
            'max_parallel' => $config->maxParallel,
            'synthesizer' => [
                'reviewer_id' => $config->synthesizer->reviewerId,
                'prompt_file' => $config->synthesizer->promptFile,
            ],
            'reviewers' => array_map(
                static fn (ReviewerConfig $r): array => [
                    'id' => $r->id,
                    'enabled' => $r->enabled,
                    'command' => $r->command,
                    'args' => $r->args,
                    'prompt_via' => $r->promptVia,
                    'model' => $r->model,
                    'result_path' => $r->resultPath,
                    'timeout' => $r->timeout,
                    'paid' => $r->paid,
                ],
                $config->reviewers,
            ),
        ];
    }

    private function mkdir(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0o777, true) && ! is_dir($path)) {
            throw new RuntimeException("Could not create directory: {$path}");
        }
    }

    private function write(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Could not write file: {$path}");
        }
    }
}
