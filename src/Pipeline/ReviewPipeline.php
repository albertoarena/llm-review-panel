<?php

declare(strict_types=1);

namespace LlmReviewPanel\Pipeline;

use LlmReviewPanel\Config\Config;
use LlmReviewPanel\Config\ConfigException;
use LlmReviewPanel\Config\ConfigLoader;
use LlmReviewPanel\Config\ReviewerConfig;
use LlmReviewPanel\Process\ProcessResult;
use LlmReviewPanel\Process\ProcessRunner;
use LlmReviewPanel\Process\ProcessSpec;
use LlmReviewPanel\Prompt\CommandBuilder;
use LlmReviewPanel\Prompt\PromptAssembler;
use LlmReviewPanel\Prompt\ReviewerOutput;
use LlmReviewPanel\Result\ResultParser;

final class ReviewPipeline
{
    private const SYNTHESIZER_ID = 'synthesizer';

    public function __construct(
        private readonly ProcessRunner $runner,
        private readonly ConfigLoader $loader = new ConfigLoader(),
        private readonly PromptAssembler $assembler = new PromptAssembler(),
        private readonly CommandBuilder $builder = new CommandBuilder(),
        private readonly ResultParser $parser = new ResultParser(),
    ) {
    }

    public function prepare(string $configPath, string $planPath): PreparedRun
    {
        $config = $this->loader->load($configPath);
        $rubric = $this->readFile($config->rubricFile, 'rubric_file');
        $synthesis = $this->readFile($config->synthesizer->promptFile, 'synthesizer.prompt_file');
        $plan = $this->readFile($planPath, 'plan');

        return new PreparedRun(
            config: $config,
            reviewPrompt: $this->assembler->assembleReview($rubric, $plan),
            synthesisInstructions: $synthesis,
        );
    }

    /**
     * @return list<ProcessSpec>
     */
    public function reviewerSpecs(PreparedRun $run): array
    {
        return array_map(
            fn (ReviewerConfig $r): ProcessSpec => $this->buildSpec($r, $run->reviewPrompt, $r->id),
            $run->config->enabledReviewers(),
        );
    }

    /**
     * @return list<ReviewerOutput>
     */
    public function runReviewers(PreparedRun $run): array
    {
        $specs = $this->reviewerSpecs($run);
        $results = $this->runner->runBatch($specs, $run->config->maxParallel);

        $outputs = [];
        foreach ($run->config->enabledReviewers() as $reviewer) {
            $outputs[] = $this->classify($reviewer, $results[$reviewer->id]);
        }

        return $outputs;
    }

    public function rerunReviewer(PreparedRun $run, string $reviewerId): ReviewerOutput
    {
        $reviewer = $this->findEnabledReviewer($run->config, $reviewerId);
        $spec = $this->buildSpec($reviewer, $run->reviewPrompt, $reviewer->id);
        $results = $this->runner->runBatch([$spec], 1);

        return $this->classify($reviewer, $results[$reviewer->id]);
    }

    /**
     * @param  list<ReviewerOutput>  $outputs
     */
    public function runSynthesis(PreparedRun $run, array $outputs): ?string
    {
        $usable = array_values(array_filter(
            $outputs,
            static fn (ReviewerOutput $o): bool => $o->status->isUsableForSynthesis(),
        ));

        if ($usable === []) {
            return null;
        }

        $prompt = $this->assembler->assembleSynthesis($run->synthesisInstructions, $usable);
        $synthesizer = $this->findReviewer($run->config, $run->config->synthesizer->reviewerId);
        $spec = $this->buildSpec($synthesizer, $prompt, self::SYNTHESIZER_ID);
        $results = $this->runner->runBatch([$spec], 1);

        return $results[self::SYNTHESIZER_ID]->stdout;
    }

    public function run(string $configPath, string $planPath): RunResult
    {
        $prepared = $this->prepare($configPath, $planPath);
        $outputs = $this->runReviewers($prepared);
        $synthesis = $this->runSynthesis($prepared, $outputs);

        return new RunResult(reviews: $outputs, synthesis: $synthesis);
    }

    private function buildSpec(ReviewerConfig $reviewer, string $prompt, string $specId): ProcessSpec
    {
        $args = $this->builder->build($reviewer, $prompt);
        $stdin = $reviewer->promptVia === ReviewerConfig::PROMPT_VIA_STDIN ? $prompt : null;

        return new ProcessSpec(
            id: $specId,
            command: $reviewer->command,
            args: $args,
            stdin: $stdin,
            timeout: $reviewer->timeout,
        );
    }

    private function classify(ReviewerConfig $reviewer, ProcessResult $result): ReviewerOutput
    {
        if ($result->timedOut) {
            return ReviewerOutput::timeout($reviewer->id, $result->stderr);
        }
        if ($result->exitCode !== 0) {
            return ReviewerOutput::nonzeroExit($reviewer->id, $result->exitCode, $result->stderr);
        }
        if (trim($result->stdout) === '') {
            return ReviewerOutput::emptyStdout($reviewer->id, $result->stderr);
        }

        return $this->parser->parse($reviewer, $result->stdout);
    }

    private function findReviewer(Config $config, string $id): ReviewerConfig
    {
        foreach ($config->reviewers as $reviewer) {
            if ($reviewer->id === $id) {
                return $reviewer;
            }
        }
        throw new ConfigException("reviewer '{$id}' not found");
    }

    private function findEnabledReviewer(Config $config, string $id): ReviewerConfig
    {
        foreach ($config->enabledReviewers() as $reviewer) {
            if ($reviewer->id === $id) {
                return $reviewer;
            }
        }
        throw new ConfigException("enabled reviewer '{$id}' not found");
    }

    private function readFile(string $path, string $label): string
    {
        if (! is_file($path)) {
            throw new ConfigException("{$label} not found: {$path}");
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new ConfigException("could not read {$label}: {$path}");
        }

        return $content;
    }
}
