<?php

declare(strict_types=1);

namespace LlmReviewPanel\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'init', description: 'Scaffold config.json and the rubric/synthesis prompts into a directory.')]
final class InitCommand extends Command
{
    /**
     * Template source (relative to the project or PHAR root) => destination
     * (relative to the target directory). The same relative paths resolve both
     * from a clone and from inside a `phar://` stream.
     *
     * @var array<string, string>
     */
    private const TEMPLATES = [
        'config.example.json' => 'config.json',
        'config/rubric.md' => 'config/rubric.md',
        'config/synthesis.md' => 'config/synthesis.md',
    ];

    protected function configure(): void
    {
        $this
            ->addArgument('target-dir', InputArgument::OPTIONAL, 'Directory to scaffold into (defaults to the current directory)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite files that already exist');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = (string) ($input->getArgument('target-dir') ?? getcwd());
        $force = (bool) $input->getOption('force');
        $root = dirname(__DIR__, 2);

        foreach (self::TEMPLATES as $source => $dest) {
            $destPath = $target.'/'.$dest;

            if (is_file($destPath) && ! $force) {
                $output->writeln("  skipped {$dest} (exists, use --force to overwrite)");

                continue;
            }

            $dir = dirname($destPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0o777, true);
            }

            copy($root.'/'.$source, $destPath);
            $output->writeln("  wrote {$dest}");
        }

        $output->writeln('');
        $output->writeln('Next: edit config.json to enable the reviewers you have installed, then run:');
        $output->writeln('  llm-review-panel review <plan.md> --dry-run');

        return Command::SUCCESS;
    }
}
