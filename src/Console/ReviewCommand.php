<?php

declare(strict_types=1);

namespace LlmReviewPanel\Console;

use LlmReviewPanel\Config\ReviewerConfig;
use LlmReviewPanel\Persistence\RunPersister;
use LlmReviewPanel\Pipeline\PreparedRun;
use LlmReviewPanel\Pipeline\ReviewPipeline;
use LlmReviewPanel\Process\CommandLocator;
use LlmReviewPanel\Prompt\CommandBuilder;
use LlmReviewPanel\Prompt\ReviewerOutput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'review', description: 'Run a multi-LLM review of a markdown plan.')]
final class ReviewCommand extends Command
{
    private const PROMPT_PREVIEW_CHARS = 80;

    private const STDERR_PREVIEW_LINES = 6;

    private ?QuestionProvider $questions = null;

    public function __construct(
        private readonly ReviewPipeline $pipeline,
        private readonly CommandBuilder $builder = new CommandBuilder(),
        private readonly RunPersister $persister = new RunPersister(),
        private readonly CommandLocator $locator = new CommandLocator(),
    ) {
        parent::__construct();
    }

    public function setQuestionProvider(QuestionProvider $questions): void
    {
        $this->questions = $questions;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('plan', InputArgument::REQUIRED, 'Path to the markdown plan to review')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config.json', 'config.json')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip all three checkpoints')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Resolve commands and exit without spawning');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $planPath = (string) $input->getArgument('plan');
        $configPath = (string) $input->getOption('config');
        $yes = (bool) $input->getOption('yes');
        $dryRun = (bool) $input->getOption('dry-run');

        $prepared = $this->pipeline->prepare($configPath, $planPath);

        if ($dryRun) {
            return $this->dryRun($prepared, $output);
        }

        $questions = fn (): QuestionProvider => $this->questions ??= new SymfonyQuestionProvider(
            $this->getHelper('question'),
            $input,
            $output,
        );

        if (! $yes && ! $this->checkpoint1($prepared, $output, $questions())) {
            $output->writeln('Aborted at checkpoint 1.');

            return Command::SUCCESS;
        }

        $outputs = $this->pipeline->runReviewers($prepared);

        if (! $yes) {
            while (true) {
                $choice = $this->checkpoint2($outputs, $output, $questions());
                if ($choice === 'continue') {
                    break;
                }
                if ($choice === 'abort') {
                    $output->writeln('Aborted at checkpoint 2.');

                    return Command::SUCCESS;
                }
                if (str_starts_with($choice, 'rerun:')) {
                    $id = substr($choice, 6);
                    $output->writeln("Re-running {$id}...");
                    $new = $this->pipeline->rerunReviewer($prepared, $id);
                    $outputs = $this->replaceOutput($outputs, $new);
                }
            }
        }

        $synthesis = $this->pipeline->runSynthesis($prepared, $outputs);

        if ($synthesis === null) {
            $output->writeln('<error>No reviewer produced usable output; synthesis skipped.</error>');
            $runDir = $this->persister->persist($prepared, $outputs, null, RunPersister::timestamp());
            $output->writeln("Manifest written to {$runDir}");

            return Command::FAILURE;
        }

        if (! $yes && ! $this->checkpoint3($synthesis, $output, $questions())) {
            $output->writeln('Aborted at checkpoint 3.');

            return Command::SUCCESS;
        }

        $runDir = $this->persister->persist($prepared, $outputs, $synthesis, RunPersister::timestamp());
        $output->writeln($synthesis);
        $output->writeln('');
        $output->writeln("Run persisted to {$runDir}");

        return Command::SUCCESS;
    }

    private function dryRun(PreparedRun $prepared, OutputInterface $output): int
    {
        $output->writeln('Dry run: resolved commands for enabled reviewers.');
        $output->writeln('');

        $missing = 0;
        $enabled = $prepared->config->enabledReviewers();
        foreach ($enabled as $reviewer) {
            $missing += $this->reportResolvedCommand($reviewer, $prepared, $output);
        }

        // A synthesizer that is not itself in the panel (a disabled "neutral
        // arbiter") is never covered by the loop above, so validate it here too.
        $synthesizer = $prepared->config->synthesizerReviewer();
        $enabledIds = array_map(static fn ($r): string => $r->id, $enabled);
        if ($synthesizer !== null && ! in_array($synthesizer->id, $enabledIds, true)) {
            $missing += $this->reportResolvedCommand($synthesizer, $prepared, $output, ' (synthesizer)');
        }

        return $missing === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function reportResolvedCommand(
        ReviewerConfig $reviewer,
        PreparedRun $prepared,
        OutputInterface $output,
        string $suffix = '',
    ): int {
        $args = $this->builder->build($reviewer, $this->elide($prepared->reviewPrompt));
        $line = $reviewer->id.$suffix.': '.$reviewer->command.' '.implode(' ', array_map(
            static fn (string $a): string => str_contains($a, ' ') ? '"'.$a.'"' : $a,
            $args,
        ));
        $found = $this->locator->exists($reviewer->command);
        $status = $found ? '<info>found on PATH</info>' : '<error>NOT FOUND on PATH</error>';
        $output->writeln("[{$status}] {$line}");

        return $found ? 0 : 1;
    }

    private function checkpoint1(PreparedRun $prepared, OutputInterface $output, QuestionProvider $questions): bool
    {
        $output->writeln('--- CHECKPOINT 1: assembled prompt and enabled reviewers ---');
        $reviewers = $prepared->config->enabledReviewers();
        foreach ($reviewers as $reviewer) {
            $paid = $reviewer->paid ? ' <comment>(paid)</comment>' : '';
            $output->writeln("  - {$reviewer->id} ({$reviewer->command}){$paid}");
        }

        $paidCount = count(array_filter($reviewers, static fn ($r) => $r->paid));
        if ($paidCount > 0) {
            $output->writeln("<comment>Warning: {$paidCount} paid reviewer(s) enabled. Each run will be billed.</comment>");
        }

        $synthesizer = $prepared->config->synthesizerReviewer();
        if ($synthesizer !== null) {
            $arbiter = $synthesizer->enabled ? '' : ' <comment>(neutral arbiter, not in panel)</comment>';
            $output->writeln("Synthesizer: {$synthesizer->id}{$arbiter}");
        }

        $output->writeln('');
        $output->writeln('Prompt preview (first '.self::PROMPT_PREVIEW_CHARS.' chars):');
        $output->writeln('  '.$this->elide($prepared->reviewPrompt));
        $output->writeln('');

        return $questions->confirm('Continue?', true);
    }

    /**
     * @param  list<ReviewerOutput>  $outputs
     */
    private function checkpoint2(array $outputs, OutputInterface $output, QuestionProvider $questions): string
    {
        $output->writeln('--- CHECKPOINT 2: raw reviews ---');
        $choices = ['continue', 'abort'];
        foreach ($outputs as $o) {
            $output->writeln(sprintf('  - %s [%s] (%dms)', $o->reviewerId, $o->status->value, $o->durationMs));
            if (! $o->status->isUsableForSynthesis() && $o->stderr !== '') {
                $this->printStderrPreview($o->stderr, $output);
            }
            $choices[] = "rerun:{$o->reviewerId}";
        }

        return $questions->choice('What now?', $choices, 'continue');
    }

    private function printStderrPreview(string $stderr, OutputInterface $output): void
    {
        $clean = preg_replace('/\x1b\[[0-9;]*m/', '', rtrim($stderr)) ?? $stderr;
        $lines = preg_split('/\r?\n/', $clean) ?: [];
        $tail = array_slice($lines, -self::STDERR_PREVIEW_LINES);
        foreach ($tail as $line) {
            $output->writeln('      <comment>'.$line.'</comment>');
        }
    }

    private function checkpoint3(string $synthesis, OutputInterface $output, QuestionProvider $questions): bool
    {
        $output->writeln('--- CHECKPOINT 3: consolidated review ---');
        $output->writeln($synthesis);
        $output->writeln('');

        return $questions->confirm('Accept this synthesis?', true);
    }

    /**
     * @param  list<ReviewerOutput>  $outputs
     * @return list<ReviewerOutput>
     */
    private function replaceOutput(array $outputs, ReviewerOutput $new): array
    {
        return array_map(
            static fn (ReviewerOutput $o): ReviewerOutput => $o->reviewerId === $new->reviewerId ? $new : $o,
            $outputs,
        );
    }

    private function elide(string $text): string
    {
        $oneline = preg_replace('/\s+/', ' ', $text) ?? $text;
        if (mb_strlen($oneline) <= self::PROMPT_PREVIEW_CHARS) {
            return $oneline;
        }

        return mb_substr($oneline, 0, self::PROMPT_PREVIEW_CHARS).'...';
    }
}
