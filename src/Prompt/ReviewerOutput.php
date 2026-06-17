<?php

declare(strict_types=1);

namespace LlmReviewPanel\Prompt;

final readonly class ReviewerOutput
{
    public function __construct(
        public string $reviewerId,
        public string $content,
        public bool $unstructured,
        public ReviewerStatus $status = ReviewerStatus::Ok,
        public ?string $failureReason = null,
        public string $stderr = '',
        public int $durationMs = 0,
    ) {
    }

    public static function timeout(string $reviewerId, string $stderr, int $durationMs = 0): self
    {
        return new self($reviewerId, '', false, ReviewerStatus::Timeout, $stderr, $stderr, $durationMs);
    }

    public static function nonzeroExit(string $reviewerId, int $exitCode, string $stderr, int $durationMs = 0): self
    {
        return new self(
            $reviewerId,
            '',
            false,
            ReviewerStatus::NonzeroExit,
            "exit {$exitCode}: ".$stderr,
            $stderr,
            $durationMs,
        );
    }

    public static function emptyStdout(string $reviewerId, string $stderr, int $durationMs = 0): self
    {
        return new self($reviewerId, '', false, ReviewerStatus::EmptyStdout, $stderr, $stderr, $durationMs);
    }
}
