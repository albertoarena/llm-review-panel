<?php

declare(strict_types=1);

namespace LlmReviewPanel\Console;

interface QuestionProvider
{
    public function confirm(string $question, bool $default = true): bool;

    /**
     * @param  list<string>  $choices
     */
    public function choice(string $question, array $choices, string $default): string;
}
