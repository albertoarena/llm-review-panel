<?php

declare(strict_types=1);

use LlmReviewPanel\Config\ReviewerConfig;
use LlmReviewPanel\Prompt\CommandBuilder;

beforeEach(function (): void {
    $this->builder = new CommandBuilder();
});

function reviewer(array $overrides = []): ReviewerConfig
{
    return new ReviewerConfig(
        id: $overrides['id'] ?? 'r',
        enabled: $overrides['enabled'] ?? true,
        command: $overrides['command'] ?? 'cli',
        args: $overrides['args'] ?? ['-p', '{prompt}'],
        promptVia: $overrides['prompt_via'] ?? ReviewerConfig::PROMPT_VIA_ARG,
        model: $overrides['model'] ?? null,
        resultPath: $overrides['result_path'] ?? null,
        timeout: $overrides['timeout'] ?? 300,
    );
}

it('substitutes the prompt token when prompt_via is arg', function (): void {
    $reviewer = reviewer(['args' => ['-p', '{prompt}', '--json']]);

    $args = $this->builder->build($reviewer, 'hello world');

    expect($args)->toBe(['-p', 'hello world', '--json']);
});

it('omits the prompt token when prompt_via is stdin', function (): void {
    $reviewer = reviewer([
        'args' => ['review', '--json'],
        'prompt_via' => ReviewerConfig::PROMPT_VIA_STDIN,
    ]);

    $args = $this->builder->build($reviewer, 'hello world');

    expect($args)->toBe(['review', '--json']);
});

it('substitutes the model token when set', function (): void {
    $reviewer = reviewer([
        'args' => ['-m', '{model}', '-p', '{prompt}'],
        'model' => 'ollama/qwen:14b',
    ]);

    $args = $this->builder->build($reviewer, 'hi');

    expect($args)->toBe(['-m', 'ollama/qwen:14b', '-p', 'hi']);
});

it('leaves args alone when no model is configured and no token appears', function (): void {
    $reviewer = reviewer(['args' => ['-p', '{prompt}']]);

    expect($this->builder->build($reviewer, 'hi'))->toBe(['-p', 'hi']);
});

it('throws when the model token is present but no model is configured', function (): void {
    $reviewer = reviewer(['args' => ['-m', '{model}', '-p', '{prompt}'], 'model' => null]);

    $this->builder->build($reviewer, 'hi');
})->throws(InvalidArgumentException::class, 'model');

it('throws when prompt_via is arg but no prompt token appears', function (): void {
    $reviewer = reviewer(['args' => ['--json']]);

    $this->builder->build($reviewer, 'hi');
})->throws(InvalidArgumentException::class, 'prompt');

it('throws when prompt_via is stdin but a prompt token appears in args', function (): void {
    $reviewer = reviewer([
        'args' => ['-p', '{prompt}'],
        'prompt_via' => ReviewerConfig::PROMPT_VIA_STDIN,
    ]);

    $this->builder->build($reviewer, 'hi');
})->throws(InvalidArgumentException::class, 'stdin');
