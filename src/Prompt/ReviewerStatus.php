<?php

declare(strict_types=1);

namespace LlmReviewPanel\Prompt;

enum ReviewerStatus: string
{
    case Ok = 'ok';
    case Unstructured = 'unstructured';
    case Timeout = 'timeout';
    case NonzeroExit = 'nonzero_exit';
    case EmptyStdout = 'empty_stdout';

    public function isUsableForSynthesis(): bool
    {
        return $this === self::Ok || $this === self::Unstructured;
    }
}
