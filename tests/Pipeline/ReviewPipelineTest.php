<?php

declare(strict_types=1);

use LlmReviewPanel\Pipeline\ReviewPipeline;
use LlmReviewPanel\Process\FakeProcessRunner;

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

it('extracts synthesis content via the synthesizer result_path', function (): void {
    $env = setupRun();
    $inner = '{"summary":"merged","verdict":"ship"}';
    $envelope = json_encode(['result' => "```json\n{$inner}\n```"]);

    $runner = new FakeProcessRunner();
    $runner->script('claude', stdout: '{"result":"first review"}');
    $runner->script('opencode', stdout: 'prose');
    $runner->script('gemini', stdout: 'gem');
    $runner->script('synthesizer', stdout: $envelope);

    $result = (new ReviewPipeline($runner))->run($env['configPath'], $env['planPath']);

    expect($result->synthesis)->toBe($inner);
});

it('records a timeout for a reviewer without aborting the others', function (): void {
    $env = setupRun();
    $runner = new FakeProcessRunner();
    $runner->script('claude', stdout: '{"result":"good"}');
    $runner->script('opencode', stdout: '', timedOut: true);
    $runner->script('gemini', stdout: 'prose review');
    $runner->script('synthesizer', stdout: 'merged');

    $result = (new ReviewPipeline($runner))->run($env['configPath'], $env['planPath']);

    expect($result->reviews)->toHaveCount(3)
        ->and($result->reviews[0]->status)->toBe(LlmReviewPanel\Prompt\ReviewerStatus::Ok)
        ->and($result->reviews[1]->status)->toBe(LlmReviewPanel\Prompt\ReviewerStatus::Timeout)
        ->and($result->reviews[2]->status)->toBe(LlmReviewPanel\Prompt\ReviewerStatus::Unstructured)
        ->and($result->synthesis)->toBe('merged');
});

it('records a nonzero exit and excludes it from synthesis', function (): void {
    $env = setupRun();
    $runner = new FakeProcessRunner();
    $runner->script('claude', stdout: '{"result":"good"}');
    $runner->script('opencode', stdout: '', stderr: 'boom', exitCode: 1);
    $runner->script('gemini', stdout: 'prose');
    $runner->script('synthesizer', stdout: 'merged');

    (new ReviewPipeline($runner))->run($env['configPath'], $env['planPath']);

    $synthPrompt = $runner->invocations()[3]->args[1];
    expect($synthPrompt)
        ->toContain('[reviewer: claude, format: json]')
        ->toContain('[reviewer: gemini, format: unstructured]')
        ->not->toContain('[reviewer: opencode');
});

it('records empty stdout as a failure', function (): void {
    $env = setupRun();
    $runner = new FakeProcessRunner();
    $runner->script('claude', stdout: '{"result":"x"}');
    $runner->script('opencode', stdout: '   ', stderr: 'nothing useful');
    $runner->script('gemini', stdout: 'prose');
    $runner->script('synthesizer', stdout: 'merged');

    $result = (new ReviewPipeline($runner))->run($env['configPath'], $env['planPath']);

    expect($result->reviews[1]->status)->toBe(LlmReviewPanel\Prompt\ReviewerStatus::EmptyStdout);
});

it('skips synthesis entirely when no reviewer produced usable output', function (): void {
    $env = setupRun();
    $runner = new FakeProcessRunner();
    $runner->script('claude', stdout: '', timedOut: true);
    $runner->script('opencode', stdout: '', exitCode: 1);
    $runner->script('gemini', stdout: '');

    $result = (new ReviewPipeline($runner))->run($env['configPath'], $env['planPath']);

    expect($result->synthesis)->toBeNull()
        ->and($result->reviews)->toHaveCount(3)
        ->and(array_filter($result->reviews, fn ($o) => $o->status->isUsableForSynthesis()))->toBe([]);
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
