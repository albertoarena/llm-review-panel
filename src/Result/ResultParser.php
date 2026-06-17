<?php

declare(strict_types=1);

namespace LlmReviewPanel\Result;

use LlmReviewPanel\Config\ReviewerConfig;
use LlmReviewPanel\Prompt\ReviewerOutput;

final class ResultParser
{
    public function parse(ReviewerConfig $reviewer, string $stdout): ReviewerOutput
    {
        $trimmed = trim($stdout);
        $decoded = json_decode($trimmed, true);
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

        return new ReviewerOutput($reviewer->id, $stdout, unstructured: ! $isJson);
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
