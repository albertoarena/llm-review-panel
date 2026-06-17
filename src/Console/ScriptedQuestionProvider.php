<?php

declare(strict_types=1);

namespace LlmReviewPanel\Console;

use RuntimeException;

final class ScriptedQuestionProvider implements QuestionProvider
{
    /** @var list<string|bool> */
    private array $queue;

    /**
     * @param  list<string|bool>  $answers
     */
    public function __construct(array $answers)
    {
        $this->queue = $answers;
    }

    public function confirm(string $question, bool $default = true): bool
    {
        return (bool) $this->next($question);
    }

    public function choice(string $question, array $choices, string $default): string
    {
        $value = (string) $this->next($question);
        if (! in_array($value, $choices, true)) {
            throw new RuntimeException("ScriptedQuestionProvider: '{$value}' is not a valid choice for '{$question}'");
        }

        return $value;
    }

    private function next(string $question): string|bool
    {
        if ($this->queue === []) {
            throw new RuntimeException("ScriptedQuestionProvider: no scripted answer for '{$question}'");
        }

        return array_shift($this->queue);
    }
}
