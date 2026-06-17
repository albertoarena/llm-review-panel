<?php

declare(strict_types=1);

use LlmReviewPanel\Process\ProcessSpec;
use LlmReviewPanel\Process\RealProcessRunner;

beforeEach(function (): void {
    $this->runner = new RealProcessRunner();
});

it('captures stdout from a successful command', function (): void {
    $results = $this->runner->runBatch([
        new ProcessSpec('p', 'printf', ['hello'], null, 5),
    ], maxParallel: 1);

    expect($results['p']->exitCode)->toBe(0)
        ->and($results['p']->stdout)->toBe('hello')
        ->and($results['p']->timedOut)->toBeFalse();
});

it('captures non-zero exit codes', function (): void {
    $results = $this->runner->runBatch([
        new ProcessSpec('p', 'false', [], null, 5),
    ], maxParallel: 1);

    expect($results['p']->exitCode)->not->toBe(0)
        ->and($results['p']->succeeded())->toBeFalse();
});

it('captures stderr output', function (): void {
    $results = $this->runner->runBatch([
        new ProcessSpec('p', PHP_BINARY, ['-r', 'fwrite(STDERR, "oh no"); exit(2);'], null, 5),
    ], maxParallel: 1);

    expect($results['p']->stderr)->toContain('oh no')
        ->and($results['p']->exitCode)->toBe(2);
});

it('pipes stdin when provided', function (): void {
    $results = $this->runner->runBatch([
        new ProcessSpec('p', PHP_BINARY, ['-r', 'echo strtoupper(file_get_contents("php://stdin"));'], 'hello', 5),
    ], maxParallel: 1);

    expect($results['p']->stdout)->toBe('HELLO');
});

it('records a timeout when the process exceeds its timeout', function (): void {
    $results = $this->runner->runBatch([
        new ProcessSpec('p', 'sleep', ['5'], null, 1),
    ], maxParallel: 1);

    expect($results['p']->timedOut)->toBeTrue()
        ->and($results['p']->succeeded())->toBeFalse();
});

it('records a spawn failure when the binary does not exist', function (): void {
    $results = $this->runner->runBatch([
        new ProcessSpec('p', 'this-binary-does-not-exist-xyz', [], null, 5),
    ], maxParallel: 1);

    expect($results['p']->exitCode)->not->toBe(0)
        ->and($results['p']->stderr)->not->toBe('');
});

it('runs independent specs in parallel under max_parallel', function (): void {
    $specs = [
        new ProcessSpec('a', 'sleep', ['0.5'], null, 5),
        new ProcessSpec('b', 'sleep', ['0.5'], null, 5),
        new ProcessSpec('c', 'sleep', ['0.5'], null, 5),
    ];

    $start = microtime(true);
    $this->runner->runBatch($specs, maxParallel: 3);
    $elapsed = microtime(true) - $start;

    expect($elapsed)->toBeLessThan(1.2);
});

it('respects max_parallel by serializing when cap is 1', function (): void {
    $specs = [
        new ProcessSpec('a', 'sleep', ['0.4'], null, 5),
        new ProcessSpec('b', 'sleep', ['0.4'], null, 5),
    ];

    $start = microtime(true);
    $this->runner->runBatch($specs, maxParallel: 1);
    $elapsed = microtime(true) - $start;

    expect($elapsed)->toBeGreaterThanOrEqual(0.75);
});

it('returns results for all specs even when some fail', function (): void {
    $results = $this->runner->runBatch([
        new ProcessSpec('ok', 'printf', ['hi'], null, 5),
        new ProcessSpec('boom', 'false', [], null, 5),
        new ProcessSpec('ghost', 'nonexistent-binary-xyz', [], null, 5),
    ], maxParallel: 3);

    expect($results)->toHaveKeys(['ok', 'boom', 'ghost'])
        ->and($results['ok']->exitCode)->toBe(0)
        ->and($results['boom']->exitCode)->not->toBe(0)
        ->and($results['ghost']->exitCode)->not->toBe(0);
});
