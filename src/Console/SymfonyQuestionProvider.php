<?php

declare(strict_types=1);

namespace LlmReviewPanel\Console;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class SymfonyQuestionProvider implements QuestionProvider
{
    public function __construct(
        private readonly QuestionHelper $helper,
        private readonly InputInterface $input,
        private readonly OutputInterface $output,
    ) {
    }

    public function confirm(string $question, bool $default = true): bool
    {
        $suffix = $default ? ' [Y/n] ' : ' [y/N] ';

        return (bool) $this->helper->ask(
            $this->input,
            $this->output,
            new ConfirmationQuestion($question.$suffix, $default),
        );
    }

    public function choice(string $question, array $choices, string $default): string
    {
        return (string) $this->helper->ask(
            $this->input,
            $this->output,
            new ChoiceQuestion($question, $choices, $default),
        );
    }
}
