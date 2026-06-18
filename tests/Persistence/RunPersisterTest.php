<?php

declare(strict_types=1);

use LlmReviewPanel\Config\ConfigLoader;
use LlmReviewPanel\Persistence\RunPersister;
use LlmReviewPanel\Pipeline\PreparedRun;
use LlmReviewPanel\Prompt\ReviewerOutput;
use LlmReviewPanel\Prompt\ReviewerStatus;

beforeEach(function (): void {
    $this->persister = new RunPersister();
    $env = setupRun();
    $config = (new ConfigLoader())->load($env['configPath']);
    $this->prepared = new PreparedRun(
        config: $config,
        reviewPrompt: "ASSEMBLED REVIEW PROMPT\n",
        synthesisInstructions: "SYNTHESIS INSTRUCTIONS\n",
    );
    $this->outDir = $config->outputDir;
});

it('writes prompt, per-reviewer files, synthesis, and manifest', function (): void {
    $outputs = [
        new ReviewerOutput('claude', '{"summary":"ok"}', false, ReviewerStatus::Ok, stderr: '', durationMs: 120),
        new ReviewerOutput('opencode', 'prose review', true, ReviewerStatus::Unstructured, stderr: 'warn: something', durationMs: 230),
    ];

    $runDir = $this->persister->persist($this->prepared, $outputs, 'FINAL_SYNTHESIS', '2026-06-17T09-00-00');

    expect($runDir)->toBe($this->outDir.'/2026-06-17T09-00-00')
        ->and(is_dir($runDir))->toBeTrue()
        ->and(file_get_contents($runDir.'/prompt.md'))->toBe("ASSEMBLED REVIEW PROMPT\n")
        ->and(file_get_contents($runDir.'/reviews/claude.json'))->toBe('{"summary":"ok"}')
        ->and(file_get_contents($runDir.'/reviews/opencode.txt'))->toBe('prose review')
        ->and(file_get_contents($runDir.'/reviews/opencode.stderr.log'))->toBe('warn: something')
        ->and(file_get_contents($runDir.'/synthesis.md'))->toBe('FINAL_SYNTHESIS');

    $manifest = json_decode(file_get_contents($runDir.'/manifest.json'), true);
    expect($manifest['timestamp'])->toBe('2026-06-17T09-00-00')
        ->and($manifest['reviewers'])->toHaveCount(2)
        ->and($manifest['reviewers'][0]['id'])->toBe('claude')
        ->and($manifest['reviewers'][0]['status'])->toBe('ok')
        ->and($manifest['reviewers'][0]['duration_ms'])->toBe(120)
        ->and($manifest['reviewers'][1]['status'])->toBe('unstructured')
        ->and($manifest['synthesis_written'])->toBeTrue()
        ->and($manifest['config'])->toBeArray()
        ->and($manifest['config']['reviewers'])->toHaveCount(3);
});

it('records failed reviewers in the manifest with reason and skips content files', function (): void {
    $outputs = [
        new ReviewerOutput('claude', '{"summary":"ok"}', false, ReviewerStatus::Ok),
        ReviewerOutput::timeout('opencode', 'still running...'),
        ReviewerOutput::nonzeroExit('gemini', 1, 'auth error'),
    ];

    $runDir = $this->persister->persist($this->prepared, $outputs, 'partial synthesis', '2026-06-17T10-00-00');

    expect(is_file($runDir.'/reviews/claude.json'))->toBeTrue()
        ->and(is_file($runDir.'/reviews/opencode.json'))->toBeFalse()
        ->and(is_file($runDir.'/reviews/opencode.txt'))->toBeFalse()
        ->and(file_get_contents($runDir.'/reviews/opencode.stderr.log'))->toContain('still running')
        ->and(file_get_contents($runDir.'/reviews/gemini.stderr.log'))->toContain('auth error');

    $manifest = json_decode(file_get_contents($runDir.'/manifest.json'), true);
    expect($manifest['reviewers'][1]['status'])->toBe('timeout')
        ->and($manifest['reviewers'][1]['failure_reason'])->toContain('still running')
        ->and($manifest['reviewers'][2]['status'])->toBe('nonzero_exit')
        ->and($manifest['reviewers'][2]['failure_reason'])->toContain('auth error');
});

it('writes synthesis.json when the synthesis is JSON', function (): void {
    $runDir = $this->persister->persist(
        $this->prepared,
        [new ReviewerOutput('claude', '{"x":1}', false, ReviewerStatus::Ok)],
        '{"summary":"ok","findings":[],"verdict":"ship"}',
        '2026-06-18T14-00-00',
    );

    expect(is_file($runDir.'/synthesis.json'))->toBeTrue()
        ->and(is_file($runDir.'/synthesis.md'))->toBeFalse();
});

it('falls back to synthesis.md when the synthesis is not JSON', function (): void {
    $runDir = $this->persister->persist(
        $this->prepared,
        [new ReviewerOutput('claude', '{"x":1}', false, ReviewerStatus::Ok)],
        'a prose synthesis, not JSON',
        '2026-06-18T14-00-01',
    );

    expect(is_file($runDir.'/synthesis.md'))->toBeTrue()
        ->and(is_file($runDir.'/synthesis.json'))->toBeFalse();
});

it('records synthesis_written=false when synthesis is null', function (): void {
    $runDir = $this->persister->persist(
        $this->prepared,
        [ReviewerOutput::timeout('claude', '')],
        null,
        '2026-06-17T11-00-00',
    );

    expect(is_file($runDir.'/synthesis.md'))->toBeFalse();
    $manifest = json_decode(file_get_contents($runDir.'/manifest.json'), true);
    expect($manifest['synthesis_written'])->toBeFalse();
});
