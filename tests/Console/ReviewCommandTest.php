<?php

declare(strict_types=1);

use LlmReviewPanel\Console\ReviewCommand;
use LlmReviewPanel\Console\ScriptedQuestionProvider;
use LlmReviewPanel\Pipeline\ReviewPipeline;
use LlmReviewPanel\Process\FakeProcessRunner;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

function makeTester(FakeProcessRunner $runner, ScriptedQuestionProvider $questions): array
{
    $command = new ReviewCommand(new ReviewPipeline($runner));
    $command->setQuestionProvider($questions);

    $app = new Application();
    $app->addCommand($command);

    $tester = new CommandTester($app->find('review'));

    return ['tester' => $tester, 'env' => setupRun()];
}

it('runs end-to-end with --yes and prints the synthesis', function (): void {
    $runner = new FakeProcessRunner();
    $runner->script('claude', stdout: '{"result":"ok"}');
    $runner->script('opencode', stdout: '{"summary":"hi","findings":[],"open_questions":[],"verdict":"ship"}');
    $runner->script('gemini', stdout: 'prose');
    $runner->script('synthesizer', stdout: 'FINAL_SYNTHESIS_OUTPUT');

    $env = setupRun();
    $command = new ReviewCommand(new ReviewPipeline($runner));
    $tester = new CommandTester($command);
    $exit = $tester->execute([
        'plan' => $env['planPath'],
        '--config' => $env['configPath'],
        '--yes' => true,
    ]);

    expect($exit)->toBe(0)
        ->and($tester->getDisplay())->toContain('FINAL_SYNTHESIS_OUTPUT');
});

it('aborts at checkpoint 1 when user declines', function (): void {
    $runner = new FakeProcessRunner();
    $questions = new ScriptedQuestionProvider([false]);

    $command = new ReviewCommand(new ReviewPipeline($runner));
    $command->setQuestionProvider($questions);
    $tester = new CommandTester($command);
    $env = setupRun();

    $exit = $tester->execute([
        'plan' => $env['planPath'],
        '--config' => $env['configPath'],
    ]);

    expect($exit)->toBe(0)
        ->and($tester->getDisplay())->toContain('Aborted at checkpoint 1')
        ->and($runner->invocations())->toBe([]);
});

it('runs reviewers and continues to synthesis when user confirms each checkpoint', function (): void {
    $runner = new FakeProcessRunner();
    $runner->script('claude', stdout: '{"result":"ok"}');
    $runner->script('opencode', stdout: 'prose');
    $runner->script('gemini', stdout: 'more prose');
    $runner->script('synthesizer', stdout: 'final');

    $questions = new ScriptedQuestionProvider([true, 'continue', true]);
    $command = new ReviewCommand(new ReviewPipeline($runner));
    $command->setQuestionProvider($questions);
    $tester = new CommandTester($command);
    $env = setupRun();

    $exit = $tester->execute([
        'plan' => $env['planPath'],
        '--config' => $env['configPath'],
    ]);

    expect($exit)->toBe(0)
        ->and($tester->getDisplay())->toContain('final')
        ->and($runner->invocations())->toHaveCount(4);
});

it('re-runs a single reviewer at checkpoint 2 and keeps others intact', function (): void {
    $runner = new FakeProcessRunner();
    $runner->script('claude', stdout: '{"result":"first"}');
    $runner->script('opencode', stdout: 'prose');
    $runner->script('gemini', stdout: 'gem-prose');
    $runner->script('synthesizer', stdout: 'final');

    $questions = new ScriptedQuestionProvider([true, 'rerun:claude', 'continue', true]);
    $command = new ReviewCommand(new ReviewPipeline($runner));
    $command->setQuestionProvider($questions);
    $tester = new CommandTester($command);
    $env = setupRun();

    $runner->script('claude', stdout: '{"result":"second"}');
    $tester->execute([
        'plan' => $env['planPath'],
        '--config' => $env['configPath'],
    ]);

    $ids = array_map(fn ($spec) => $spec->id, $runner->invocations());
    expect($ids)->toBe(['claude', 'opencode', 'gemini', 'claude', 'synthesizer']);
});

it('allows re-running a failed reviewer at checkpoint 2', function (): void {
    $runner = new FakeProcessRunner();
    $runner->script('claude', stdout: '{"result":"good"}');
    $runner->script('opencode', stdout: '', stderr: 'boom', exitCode: 1);
    $runner->script('gemini', stdout: 'prose');
    $runner->script('synthesizer', stdout: 'final');

    $questions = new ScriptedQuestionProvider([true, 'rerun:opencode', 'continue', true]);
    $command = new ReviewCommand(new ReviewPipeline($runner));
    $command->setQuestionProvider($questions);
    $tester = new CommandTester($command);
    $env = setupRun();

    $runner->script('opencode', stdout: 'prose now');
    $exit = $tester->execute([
        'plan' => $env['planPath'],
        '--config' => $env['configPath'],
    ]);

    expect($exit)->toBe(0);
    $ids = array_map(fn ($spec) => $spec->id, $runner->invocations());
    expect($ids)->toBe(['claude', 'opencode', 'gemini', 'opencode', 'synthesizer']);
});

it('re-runs all failed reviewers at checkpoint 2 in one step', function (): void {
    $runner = new FakeProcessRunner();
    $runner->script('claude', stdout: '{"result":"good"}');
    $runner->script('opencode', stdout: '', stderr: 'boom', exitCode: 1);
    $runner->script('gemini', stdout: '');
    $runner->script('synthesizer', stdout: 'final');

    $questions = new ScriptedQuestionProvider([true, 'rerun:failed', 'continue', true]);
    $command = new ReviewCommand(new ReviewPipeline($runner));
    $command->setQuestionProvider($questions);
    $tester = new CommandTester($command);
    $env = setupRun();

    $exit = $tester->execute([
        'plan' => $env['planPath'],
        '--config' => $env['configPath'],
    ]);

    expect($exit)->toBe(0);
    $ids = array_map(fn ($spec) => $spec->id, $runner->invocations());
    expect($ids)->toBe(['claude', 'opencode', 'gemini', 'opencode', 'gemini', 'synthesizer']);
});

it('previews stderr at checkpoint 2 for failed reviewers', function (): void {
    $runner = new FakeProcessRunner();
    $runner->script('claude', stdout: '{"result":"ok"}');
    $runner->script('opencode', stdout: '', stderr: "line A\nline B\nERROR: Model not found: ollama/foo\nDid you mean: ollama-cloud?\n", exitCode: 1);
    $runner->script('gemini', stdout: 'prose');
    $runner->script('synthesizer', stdout: 'final');

    $questions = new ScriptedQuestionProvider([true, 'continue', true]);
    $command = new ReviewCommand(new ReviewPipeline($runner));
    $command->setQuestionProvider($questions);
    $tester = new CommandTester($command);
    $env = setupRun();

    $tester->execute([
        'plan' => $env['planPath'],
        '--config' => $env['configPath'],
    ]);

    expect($tester->getDisplay())
        ->toContain('opencode [nonzero_exit]')
        ->toContain('ERROR: Model not found')
        ->toContain('Did you mean: ollama-cloud?');
});

it('aborts at checkpoint 2', function (): void {
    $runner = new FakeProcessRunner();
    $runner->script('claude', stdout: '{"result":"ok"}');
    $runner->script('opencode', stdout: 'prose');
    $runner->script('gemini', stdout: 'gem');

    $questions = new ScriptedQuestionProvider([true, 'abort']);
    $command = new ReviewCommand(new ReviewPipeline($runner));
    $command->setQuestionProvider($questions);
    $tester = new CommandTester($command);
    $env = setupRun();

    $tester->execute([
        'plan' => $env['planPath'],
        '--config' => $env['configPath'],
    ]);

    expect($tester->getDisplay())->toContain('Aborted at checkpoint 2');
    $ids = array_map(fn ($spec) => $spec->id, $runner->invocations());
    expect($ids)->not->toContain('synthesizer');
});

it('aborts at checkpoint 3 when user rejects the synthesis', function (): void {
    $runner = new FakeProcessRunner();
    $runner->script('claude', stdout: '{"result":"ok"}');
    $runner->script('opencode', stdout: 'prose');
    $runner->script('gemini', stdout: 'gem');
    $runner->script('synthesizer', stdout: 'final');

    $questions = new ScriptedQuestionProvider([true, 'continue', false]);
    $command = new ReviewCommand(new ReviewPipeline($runner));
    $command->setQuestionProvider($questions);
    $tester = new CommandTester($command);
    $env = setupRun();

    $tester->execute([
        'plan' => $env['planPath'],
        '--config' => $env['configPath'],
    ]);

    expect($tester->getDisplay())->toContain('Aborted at checkpoint 3');
});

it('warns when paid reviewers are enabled at checkpoint 1', function (): void {
    $env = setupRun();
    $data = json_decode(file_get_contents($env['configPath']), true);
    $data['reviewers'][1]['paid'] = true;
    file_put_contents($env['configPath'], json_encode($data));

    $runner = new FakeProcessRunner();
    $runner->script('claude', stdout: '{"result":"ok"}');
    $runner->script('opencode', stdout: 'prose');
    $runner->script('gemini', stdout: 'gem');
    $runner->script('synthesizer', stdout: 'final');

    $questions = new ScriptedQuestionProvider([true, 'continue', true]);
    $command = new ReviewCommand(new ReviewPipeline($runner));
    $command->setQuestionProvider($questions);
    $tester = new CommandTester($command);

    $tester->execute([
        'plan' => $env['planPath'],
        '--config' => $env['configPath'],
    ]);

    expect($tester->getDisplay())
        ->toContain('1 paid reviewer(s) enabled')
        ->toContain('opencode')
        ->toContain('(paid)');
});

it('prints resolved commands and exits 0 on --dry-run when all binaries exist', function (): void {
    $env = setupRun();
    $data = json_decode(file_get_contents($env['configPath']), true);
    $data['reviewers'][0]['command'] = 'printf';
    $data['reviewers'][1]['command'] = 'printf';
    $data['reviewers'][2]['command'] = 'printf';
    $data['synthesizer']['reviewer_id'] = 'claude';
    file_put_contents($env['configPath'], json_encode($data));

    $runner = new FakeProcessRunner();
    $command = new ReviewCommand(new ReviewPipeline($runner));
    $tester = new CommandTester($command);

    $exit = $tester->execute([
        'plan' => $env['planPath'],
        '--config' => $env['configPath'],
        '--dry-run' => true,
    ]);

    expect($exit)->toBe(0)
        ->and($tester->getDisplay())->toContain('Dry run')
        ->and($tester->getDisplay())->toContain('found on PATH')
        ->and($runner->invocations())->toBe([]);
});

it('fails --dry-run when a reviewer command is not on PATH', function (): void {
    $runner = new FakeProcessRunner();
    $command = new ReviewCommand(new ReviewPipeline($runner));
    $tester = new CommandTester($command);
    $env = setupRun();

    $exit = $tester->execute([
        'plan' => $env['planPath'],
        '--config' => $env['configPath'],
        '--dry-run' => true,
    ]);

    expect($exit)->not->toBe(0)
        ->and($tester->getDisplay())->toContain('NOT FOUND');
});

it('PATH-checks a disabled synthesizer on --dry-run', function (): void {
    $env = setupRun();
    $data = json_decode(file_get_contents($env['configPath']), true);
    foreach ([0, 1, 2] as $i) {
        $data['reviewers'][$i]['command'] = 'printf';
    }
    $data['reviewers'][] = [
        'id' => 'arbiter',
        'enabled' => false,
        'command' => 'no-such-binary-xyz',
        'args' => ['-p', '{prompt}'],
        'prompt_via' => 'arg',
        'model' => null,
        'result_path' => null,
        'timeout' => 300,
    ];
    $data['synthesizer']['reviewer_id'] = 'arbiter';
    file_put_contents($env['configPath'], json_encode($data));

    $command = new ReviewCommand(new ReviewPipeline(new FakeProcessRunner()));
    $tester = new CommandTester($command);

    $exit = $tester->execute([
        'plan' => $env['planPath'],
        '--config' => $env['configPath'],
        '--dry-run' => true,
    ]);

    expect($exit)->not->toBe(0)
        ->and($tester->getDisplay())
        ->toContain('arbiter')
        ->toContain('synthesizer')
        ->toContain('NOT FOUND');
});

it('does not list the synthesizer twice when it is an enabled reviewer', function (): void {
    $env = setupRun();
    $data = json_decode(file_get_contents($env['configPath']), true);
    foreach ([0, 1, 2] as $i) {
        $data['reviewers'][$i]['command'] = 'printf';
    }
    $data['synthesizer']['reviewer_id'] = 'claude';
    file_put_contents($env['configPath'], json_encode($data));

    $command = new ReviewCommand(new ReviewPipeline(new FakeProcessRunner()));
    $tester = new CommandTester($command);

    $exit = $tester->execute([
        'plan' => $env['planPath'],
        '--config' => $env['configPath'],
        '--dry-run' => true,
    ]);

    expect($exit)->toBe(0)
        ->and(substr_count($tester->getDisplay(), 'printf'))->toBe(3);
});

it('names the synthesizer at checkpoint 1', function (): void {
    $runner = new FakeProcessRunner();
    $runner->script('claude', stdout: '{"result":"ok"}');
    $runner->script('opencode', stdout: 'prose');
    $runner->script('gemini', stdout: 'gem');
    $runner->script('synthesizer', stdout: 'final');

    $questions = new ScriptedQuestionProvider([true, 'continue', true]);
    $command = new ReviewCommand(new ReviewPipeline($runner));
    $command->setQuestionProvider($questions);
    $tester = new CommandTester($command);
    $env = setupRun();

    $tester->execute([
        'plan' => $env['planPath'],
        '--config' => $env['configPath'],
    ]);

    expect($tester->getDisplay())->toContain('Synthesizer: claude');
});
