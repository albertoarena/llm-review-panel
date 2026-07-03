<?php

declare(strict_types=1);

use LlmReviewPanel\Process\CommandLocator;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/clt-'.bin2hex(random_bytes(6));
    mkdir($this->dir);
});

afterEach(function (): void {
    array_map('unlink', glob($this->dir.'/*') ?: []);
    @rmdir($this->dir);
});

function makeExecutable(string $path): void
{
    file_put_contents($path, "#!/bin/sh\necho hi\n");
    chmod($path, 0o755);
}

it('finds an executable on the PATH', function (): void {
    makeExecutable($this->dir.'/mycli');
    $locator = new CommandLocator($this->dir);

    expect($locator->exists('mycli'))->toBeTrue();
});

it('returns false for a command not on the PATH', function (): void {
    $locator = new CommandLocator($this->dir);

    expect($locator->exists('nope'))->toBeFalse();
});

it('returns false for a file that exists but is not executable', function (): void {
    file_put_contents($this->dir.'/notexec', 'plain');
    chmod($this->dir.'/notexec', 0o644);
    $locator = new CommandLocator($this->dir);

    expect($locator->exists('notexec'))->toBeFalse();
});

it('finds a command given as an absolute path', function (): void {
    makeExecutable($this->dir.'/abs');
    $locator = new CommandLocator('');

    expect($locator->exists($this->dir.'/abs'))->toBeTrue();
});

it('returns false for an absolute path that is not executable', function (): void {
    file_put_contents($this->dir.'/absplain', 'plain');
    chmod($this->dir.'/absplain', 0o644);
    $locator = new CommandLocator('');

    expect($locator->exists($this->dir.'/absplain'))->toBeFalse();
});

it('ignores empty PATH segments', function (): void {
    makeExecutable($this->dir.'/seg');
    $locator = new CommandLocator(':'.$this->dir.':', ':');

    expect($locator->exists('seg'))->toBeTrue();
});

it('returns false for an empty command', function (): void {
    $locator = new CommandLocator($this->dir);

    expect($locator->exists(''))->toBeFalse();
});
