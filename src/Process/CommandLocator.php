<?php

declare(strict_types=1);

namespace LlmReviewPanel\Process;

/**
 * Resolves whether a command is runnable by scanning the PATH with is_executable,
 * without shelling out. shell_exec is often disabled via disable_functions in
 * hardened PHP installs, which would make a `command -v` probe silently fail.
 */
final class CommandLocator
{
    private readonly string $pathEnv;

    public function __construct(?string $pathEnv = null, private readonly string $separator = PATH_SEPARATOR)
    {
        $this->pathEnv = $pathEnv ?? (getenv('PATH') ?: '');
    }

    public function exists(string $command): bool
    {
        if ($command === '') {
            return false;
        }

        // A command given with a path is checked directly, not via PATH.
        if (str_contains($command, DIRECTORY_SEPARATOR) || str_contains($command, '/')) {
            return $this->isExecutableFile($command);
        }

        foreach (explode($this->separator, $this->pathEnv) as $dir) {
            if ($dir === '') {
                continue;
            }
            if ($this->isExecutableFile($dir.DIRECTORY_SEPARATOR.$command)) {
                return true;
            }
        }

        return false;
    }

    private function isExecutableFile(string $path): bool
    {
        return is_file($path) && is_executable($path);
    }
}
