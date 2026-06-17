<?php

declare(strict_types=1);

namespace LlmReviewPanel\Prompt;

use InvalidArgumentException;
use LlmReviewPanel\Config\ReviewerConfig;

final class CommandBuilder
{
    private const PROMPT_TOKEN = '{prompt}';

    private const MODEL_TOKEN = '{model}';

    /**
     * @return list<string>
     */
    public function build(ReviewerConfig $reviewer, string $prompt): array
    {
        $hasPromptToken = in_array(self::PROMPT_TOKEN, $reviewer->args, true);
        $hasModelToken = in_array(self::MODEL_TOKEN, $reviewer->args, true);

        if ($reviewer->promptVia === ReviewerConfig::PROMPT_VIA_ARG && ! $hasPromptToken) {
            throw new InvalidArgumentException(
                "reviewer '{$reviewer->id}': prompt_via=arg but no {prompt} token in args"
            );
        }

        if ($reviewer->promptVia === ReviewerConfig::PROMPT_VIA_STDIN && $hasPromptToken) {
            throw new InvalidArgumentException(
                "reviewer '{$reviewer->id}': prompt_via=stdin but {prompt} token appears in args; remove it"
            );
        }

        if ($hasModelToken && $reviewer->model === null) {
            throw new InvalidArgumentException(
                "reviewer '{$reviewer->id}': {model} token in args but no model configured"
            );
        }

        $args = [];
        foreach ($reviewer->args as $arg) {
            if ($arg === self::PROMPT_TOKEN) {
                if ($reviewer->promptVia === ReviewerConfig::PROMPT_VIA_ARG) {
                    $args[] = $prompt;
                }

                continue;
            }
            if ($arg === self::MODEL_TOKEN) {
                $args[] = (string) $reviewer->model;

                continue;
            }
            $args[] = $arg;
        }

        return $args;
    }
}
