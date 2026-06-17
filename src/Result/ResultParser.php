<?php

declare(strict_types=1);

namespace LlmReviewPanel\Result;

use InvalidArgumentException;
use LlmReviewPanel\Config\ReviewerConfig;
use LlmReviewPanel\Prompt\ReviewerOutput;

final class ResultParser
{
    public function parse(ReviewerConfig $reviewer, string $stdout): ReviewerOutput
    {
        if ($reviewer->resultPath !== null) {
            $this->assertNoArrayIndexing($reviewer->resultPath);
        }

        $normalized = $this->stripFences(trim($stdout));
        $decoded = json_decode($normalized, true);
        $isJson = json_last_error() === JSON_ERROR_NONE && is_array($decoded);

        if ($reviewer->resultPath !== null) {
            if (! $isJson) {
                return new ReviewerOutput($reviewer->id, $stdout, unstructured: true);
            }

            $extracted = $this->dotPath($decoded, $reviewer->resultPath);
            if ($extracted === null) {
                return new ReviewerOutput($reviewer->id, $stdout, unstructured: true);
            }

            return new ReviewerOutput($reviewer->id, $extracted, unstructured: false);
        }

        if ($isJson) {
            return new ReviewerOutput($reviewer->id, $normalized, unstructured: false);
        }

        return new ReviewerOutput($reviewer->id, $stdout, unstructured: true);
    }

    private function stripFences(string $value): string
    {
        if (preg_match('/^```(?:[a-zA-Z0-9_+-]*)\n(.*)\n```$/s', $value, $matches) === 1) {
            return trim($matches[1]);
        }

        return $value;
    }

    private function assertNoArrayIndexing(string $path): void
    {
        if (str_contains($path, '[') || str_contains($path, ']')) {
            throw new InvalidArgumentException(
                "result_path '{$path}' uses array indexing, which is not supported. ".
                'Use a non-streaming output mode or set result_path: null.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dotPath(array $data, string $path): ?string
    {
        $segments = explode('.', $path);
        $cursor = $data;
        foreach ($segments as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }

        return is_string($cursor) ? $cursor : null;
    }
}
