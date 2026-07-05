<?php

declare(strict_types=1);

use LlmReviewPanel\Application;
use LlmReviewPanel\Console\InitCommand;
use Symfony\Component\Console\Tester\CommandTester;

function initTargetDir(): string
{
    $dir = sys_get_temp_dir().'/llm-review-panel-init-'.bin2hex(random_bytes(6));
    mkdir($dir, 0o777, true);

    return $dir;
}

function projectRoot(): string
{
    return dirname(__DIR__, 2);
}

it('scaffolds config and asset files into an empty directory', function (): void {
    $dir = initTargetDir();
    $tester = new CommandTester(new InitCommand());

    $exit = $tester->execute(['target-dir' => $dir]);

    expect($exit)->toBe(0)
        ->and(is_file($dir.'/config.json'))->toBeTrue()
        ->and(is_file($dir.'/config/rubric.md'))->toBeTrue()
        ->and(is_file($dir.'/config/synthesis.md'))->toBeTrue();

    $root = projectRoot();
    expect(file_get_contents($dir.'/config.json'))->toBe(file_get_contents($root.'/config.example.json'))
        ->and(file_get_contents($dir.'/config/rubric.md'))->toBe(file_get_contents($root.'/config/rubric.md'))
        ->and(file_get_contents($dir.'/config/synthesis.md'))->toBe(file_get_contents($root.'/config/synthesis.md'));
});

it('reports each file it writes', function (): void {
    $dir = initTargetDir();
    $tester = new CommandTester(new InitCommand());
    $tester->execute(['target-dir' => $dir]);

    $display = $tester->getDisplay();
    expect($display)->toContain('config.json')
        ->and($display)->toContain('config/rubric.md')
        ->and($display)->toContain('config/synthesis.md');
});

it('refuses to overwrite existing files without --force', function (): void {
    $dir = initTargetDir();
    file_put_contents($dir.'/config.json', 'KEEP ME');

    $tester = new CommandTester(new InitCommand());
    $exit = $tester->execute(['target-dir' => $dir]);

    expect($exit)->toBe(0)
        ->and(file_get_contents($dir.'/config.json'))->toBe('KEEP ME')
        ->and($tester->getDisplay())->toContain('skipped');
});

it('overwrites existing files with --force', function (): void {
    $dir = initTargetDir();
    file_put_contents($dir.'/config.json', 'OVERWRITE ME');

    $tester = new CommandTester(new InitCommand());
    $exit = $tester->execute(['target-dir' => $dir, '--force' => true]);

    expect($exit)->toBe(0)
        ->and(file_get_contents($dir.'/config.json'))->toBe(file_get_contents(projectRoot().'/config.example.json'));
});

it('creates the target directory and config subdir when missing', function (): void {
    $dir = initTargetDir().'/nested/project';

    $tester = new CommandTester(new InitCommand());
    $exit = $tester->execute(['target-dir' => $dir]);

    expect($exit)->toBe(0)
        ->and(is_dir($dir.'/config'))->toBeTrue()
        ->and(is_file($dir.'/config/synthesis.md'))->toBeTrue();
});

it('defaults to the current working directory', function (): void {
    $dir = initTargetDir();
    $cwd = getcwd();
    chdir($dir);

    try {
        $tester = new CommandTester(new InitCommand());
        $exit = $tester->execute([]);
    } finally {
        chdir($cwd !== false ? $cwd : projectRoot());
    }

    expect($exit)->toBe(0)
        ->and(is_file($dir.'/config.json'))->toBeTrue();
});

it('is registered on the application', function (): void {
    expect((new Application())->has('init'))->toBeTrue();
});
