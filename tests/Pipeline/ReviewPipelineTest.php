<?php

declare(strict_types=1);

use LlmReviewPanel\Pipeline\ReviewPipeline;
use LlmReviewPanel\Process\FakeProcessRunner;

function setupRun(): array
{
    $dir = sys_get_temp_dir().'/llm-review-panel-pipeline-'.bin2hex(random_bytes(6));
    mkdir($dir, 0o777, true);

    file_put_contents($dir.'/rubric.md', "# Rubric\n\nLook carefully.");
    file_put_contents($dir.'/synthesis.md', "# Synthesis\n\nMerge the reviews.");
    file_put_contents($dir.'/plan.md', "# Plan\n\nBuild the thing.");

    $config = [
        'reviewers' => [
            [
                'id' => 'claude',
                'enabled' => true,
                'command' => 'claude',
                'args' => ['-p', '{prompt}'],
                'prompt_via' => 'arg',
                'model' => null,
                'result_path' => 'result',
                'timeout' => 300,
            ],
            [
                'id' => 'opencode',
                'enabled' => true,
                'command' => 'opencode',
                'args' => ['run', '{prompt}'],
                'prompt_via' => 'arg',
                'model' => null,
                'result_path' => null,
                'timeout' => 300,
            ],
            [
                'id' => 'gemini',
                'enabled' => true,
                'command' => 'gemini',
                'args' => ['-p', '{prompt}'],
                'prompt_via' => 'arg',
                'model' => null,
                'result_path' => null,
                'timeout' => 300,
            ],
        ],
        'synthesizer' => ['reviewer_id' => 'claude', 'prompt_file' => 'synthesis.md'],
        'rubric_file' => 'rubric.md',
        'output_dir' => 'runs',
        'max_parallel' => 3,
    ];
    file_put_contents($dir.'/config.json', json_encode($config, JSON_PRETTY_PRINT));

    return ['dir' => $dir, 'configPath' => $dir.'/config.json', 'planPath' => $dir.'/plan.md'];
}

it('runs the full pipeline against a fake process runner', function (): void {
    $env = setupRun();
    $runner = new FakeProcessRunner();
    $runner->script('claude', stdout: '{"result":"{\"summary\":\"all good\",\"findings\":[],\"open_questions\":[],\"verdict\":\"ship\"}"}');
    $runner->script('opencode', stdout: '{"summary":"some concerns","findings":[],"open_questions":[],"verdict":"iterate"}');
    $runner->script('gemini', stdout: 'I have feelings about this plan and they are mixed.');
    $runner->script('synthesizer', stdout: '{"summary":"consolidated","verdict":"iterate"}');

    $pipeline = new ReviewPipeline($runner);
    $result = $pipeline->run($env['configPath'], $env['planPath']);

    expect($result->reviews)->toHaveCount(3)
        ->and($result->reviews[0]->reviewerId)->toBe('claude')
        ->and($result->reviews[0]->unstructured)->toBeFalse()
        ->and($result->reviews[1]->reviewerId)->toBe('opencode')
        ->and($result->reviews[1]->unstructured)->toBeFalse()
        ->and($result->reviews[2]->reviewerId)->toBe('gemini')
        ->and($result->reviews[2]->unstructured)->toBeTrue()
        ->and($result->synthesis)->toBe('{"summary":"consolidated","verdict":"iterate"}');
});

it('invokes the synthesizer with all reviewer outputs in the prompt', function (): void {
    $env = setupRun();
    $runner = new FakeProcessRunner();
    $runner->script('claude', stdout: '{"result":"CLAUDE_REVIEW_TEXT"}');
    $runner->script('opencode', stdout: 'OPENCODE_PROSE_REVIEW');
    $runner->script('gemini', stdout: 'GEMINI_PROSE_REVIEW');
    $runner->script('synthesizer', stdout: 'final');

    (new ReviewPipeline($runner))->run($env['configPath'], $env['planPath']);

    $invocations = $runner->invocations();
    $synthCall = $invocations[3];
    expect($synthCall->id)->toBe('synthesizer')
        ->and($synthCall->command)->toBe('claude');

    $synthPrompt = $synthCall->args[1] ?? null;
    expect($synthPrompt)
        ->toContain('CLAUDE_REVIEW_TEXT')
        ->toContain('OPENCODE_PROSE_REVIEW')
        ->toContain('GEMINI_PROSE_REVIEW')
        ->toContain('Merge the reviews')
        ->toContain('[reviewer: claude, format: json]')
        ->toContain('[reviewer: opencode, format: unstructured]')
        ->toContain('[reviewer: gemini, format: unstructured]');
});

it('passes the assembled review prompt to each reviewer', function (): void {
    $env = setupRun();
    $runner = new FakeProcessRunner();
    $runner->script('claude', stdout: '{"result":"x"}');
    $runner->script('opencode', stdout: 'y');
    $runner->script('gemini', stdout: 'z');
    $runner->script('synthesizer', stdout: 'final');

    (new ReviewPipeline($runner))->run($env['configPath'], $env['planPath']);

    $reviewPrompt = $runner->invocations()[0]->args[1];
    expect($reviewPrompt)
        ->toContain('Look carefully')
        ->toContain('Build the thing')
        ->toContain('PLAN TO REVIEW')
        ->toContain('OUTPUT CONTRACT');
});

it('skips reviewers that are disabled', function (): void {
    $env = setupRun();
    $data = json_decode(file_get_contents($env['configPath']), true);
    $data['reviewers'][1]['enabled'] = false;
    file_put_contents($env['configPath'], json_encode($data));

    $runner = new FakeProcessRunner();
    $runner->script('claude', stdout: '{"result":"x"}');
    $runner->script('gemini', stdout: 'z');
    $runner->script('synthesizer', stdout: 'final');

    $result = (new ReviewPipeline($runner))->run($env['configPath'], $env['planPath']);

    expect($result->reviews)->toHaveCount(2)
        ->and($result->reviews[0]->reviewerId)->toBe('claude')
        ->and($result->reviews[1]->reviewerId)->toBe('gemini');
});
