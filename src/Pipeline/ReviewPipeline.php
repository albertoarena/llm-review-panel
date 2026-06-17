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

    public function run(string $configPath, string $planPath): RunResult
    {
        $config = $this->loader->load($configPath);

        $rubric = $this->readFile($config->rubricFile, 'rubric_file');
        $synthesisInstructions = $this->readFile(
            $config->synthesizer->promptFile,
            'synthesizer.prompt_file',
        );
        $plan = $this->readFile($planPath, 'plan');

        $reviewPrompt = $this->assembler->assembleReview($rubric, $plan);

        $reviewSpecs = $this->buildSpecs($config->enabledReviewers(), $reviewPrompt);
        $reviewResults = $this->runner->runBatch($reviewSpecs, $config->maxParallel);

        $outputs = [];
        foreach ($config->enabledReviewers() as $reviewer) {
            $outputs[] = $this->classify($reviewer, $reviewResults[$reviewer->id]);
        }

        $usable = array_values(array_filter(
            $outputs,
            static fn (ReviewerOutput $o): bool => $o->status->isUsableForSynthesis(),
        ));

        $synthesis = null;
        if ($usable !== []) {
            $synthesisPrompt = $this->assembler->assembleSynthesis($synthesisInstructions, $usable);
            $synthesizer = $this->findReviewer($config, $config->synthesizer->reviewerId);
            $synthSpec = $this->buildSpec($synthesizer, $synthesisPrompt, self::SYNTHESIZER_ID);
            $synthResults = $this->runner->runBatch([$synthSpec], 1);
            $synthesis = $synthResults[self::SYNTHESIZER_ID]->stdout;
        }

        return new RunResult(reviews: $outputs, synthesis: $synthesis);
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

    /**
     * @param  list<ReviewerConfig>  $reviewers
     * @return list<ProcessSpec>
     */
    private function buildSpecs(array $reviewers, string $prompt): array
    {
        return array_map(
            fn (ReviewerConfig $r): ProcessSpec => $this->buildSpec($r, $prompt, $r->id),
            $reviewers,
        );
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

    private function findReviewer(Config $config, string $id): ReviewerConfig
    {
        foreach ($config->reviewers as $reviewer) {
            if ($reviewer->id === $id) {
                return $reviewer;
            }
        }
        throw new ConfigException("synthesizer reviewer '{$id}' not found");
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
