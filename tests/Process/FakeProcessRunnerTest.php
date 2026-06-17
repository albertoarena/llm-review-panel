<?php

declare(strict_types=1);

use LlmReviewPanel\Process\FakeProcessRunner;
use LlmReviewPanel\Process\ProcessException;
use LlmReviewPanel\Process\ProcessSpec;

it('returns scripted results for known specs', function (): void {
    $runner = new FakeProcessRunner();
    $runner->script('a', stdout: 'hello', exitCode: 0);
    $runner->script('b', stdout: 'world', exitCode: 0);

    $results = $runner->runBatch([
        new ProcessSpec('a', 'claude', ['-p', 'p1'], null, 300),
        new ProcessSpec('b', 'opencode', ['run', 'p2'], null, 300),
    ], maxParallel: 2);

    expect($results)->toHaveKeys(['a', 'b'])
        ->and($results['a']->stdout)->toBe('hello')
        ->and($results['a']->exitCode)->toBe(0)
        ->and($results['b']->stdout)->toBe('world');
});

it('records invocations in order', function (): void {
    $runner = new FakeProcessRunner();
    $runner->script('one', stdout: 'x');
    $runner->script('two', stdout: 'y');

    $runner->runBatch([
        new ProcessSpec('one', 'claude', ['-p', 'a'], 'piped', 300),
        new ProcessSpec('two', 'opencode', ['run', 'b'], null, 300),
    ], maxParallel: 2);

    $invocations = $runner->invocations();
    expect($invocations)->toHaveCount(2)
        ->and($invocations[0]->id)->toBe('one')
        ->and($invocations[0]->command)->toBe('claude')
        ->and($invocations[0]->stdin)->toBe('piped')
        ->and($invocations[1]->id)->toBe('two');
});

it('throws when an unscripted spec is run', function (): void {
    $runner = new FakeProcessRunner();

    $runner->runBatch([new ProcessSpec('ghost', 'claude', [], null, 300)], maxParallel: 1);
})->throws(ProcessException::class, 'ghost');

it('supports scripting non-zero exit codes and stderr', function (): void {
    $runner = new FakeProcessRunner();
    $runner->script('boom', stdout: '', stderr: 'oh no', exitCode: 1);

    $results = $runner->runBatch([
        new ProcessSpec('boom', 'cli', [], null, 300),
    ], maxParallel: 1);

    expect($results['boom']->exitCode)->toBe(1)
        ->and($results['boom']->stderr)->toBe('oh no');
});
