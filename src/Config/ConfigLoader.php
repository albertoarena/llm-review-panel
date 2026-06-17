<?php

declare(strict_types=1);

namespace LlmReviewPanel\Config;

use JsonException;

final class ConfigLoader
{
    private const TOP_LEVEL_FIELDS = [
        'reviewers',
        'synthesizer',
        'rubric_file',
        'output_dir',
        'max_parallel',
    ];

    private const REVIEWER_FIELDS = [
        'id',
        'enabled',
        'command',
        'args',
        'prompt_via',
        'model',
        'result_path',
        'timeout',
        'paid',
    ];

    private const REVIEWER_REQUIRED_FIELDS = [
        'id',
        'enabled',
        'command',
        'args',
        'prompt_via',
        'model',
        'result_path',
        'timeout',
    ];

    private const SYNTHESIZER_FIELDS = ['reviewer_id', 'prompt_file'];

    public function load(string $path): Config
    {
        if (! is_file($path)) {
            throw new ConfigException("Config file not found: {$path}");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new ConfigException("Could not read config file: {$path}");
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ConfigException("Invalid JSON in {$path}: {$e->getMessage()}", 0, $e);
        }

        if (! is_array($data)) {
            throw new ConfigException("Config root must be a JSON object: {$path}");
        }

        $baseDir = dirname((string) realpath($path));

        $this->assertKnownKeys($data, self::TOP_LEVEL_FIELDS, 'config');
        $this->assertRequiredKeys($data, self::TOP_LEVEL_FIELDS, 'config');

        $reviewers = $this->parseReviewers($data['reviewers']);
        $synthesizer = $this->parseSynthesizer($data['synthesizer'], $baseDir, $reviewers);

        if (! is_string($data['rubric_file']) || $data['rubric_file'] === '') {
            throw new ConfigException('rubric_file must be a non-empty string');
        }
        if (! is_string($data['output_dir']) || $data['output_dir'] === '') {
            throw new ConfigException('output_dir must be a non-empty string');
        }
        if (! is_int($data['max_parallel']) || $data['max_parallel'] < 1) {
            throw new ConfigException('max_parallel must be a positive integer');
        }

        return new Config(
            reviewers: $reviewers,
            synthesizer: $synthesizer,
            rubricFile: $this->resolvePath($data['rubric_file'], $baseDir),
            outputDir: $this->resolvePath($data['output_dir'], $baseDir),
            maxParallel: $data['max_parallel'],
        );
    }

    /**
     * @return list<ReviewerConfig>
     */
    private function parseReviewers(mixed $reviewers): array
    {
        if (! is_array($reviewers) || ! array_is_list($reviewers)) {
            throw new ConfigException('reviewers must be a JSON array');
        }
        if ($reviewers === []) {
            throw new ConfigException('reviewers must not be empty');
        }

        $parsed = [];
        $seen = [];
        $anyEnabled = false;

        foreach ($reviewers as $i => $entry) {
            if (! is_array($entry)) {
                throw new ConfigException("reviewers[{$i}] must be an object");
            }

            $label = "reviewers[{$i}]";
            $this->assertKnownKeys($entry, self::REVIEWER_FIELDS, $label);
            $this->assertRequiredKeys($entry, self::REVIEWER_REQUIRED_FIELDS, $label);

            $id = $entry['id'];
            if (! is_string($id) || $id === '') {
                throw new ConfigException("{$label}.id must be a non-empty string");
            }
            if (isset($seen[$id])) {
                throw new ConfigException("duplicate reviewer id: {$id}");
            }
            $seen[$id] = true;

            $enabled = $entry['enabled'];
            if (! is_bool($enabled)) {
                throw new ConfigException("{$label}.enabled must be a boolean");
            }
            $anyEnabled = $anyEnabled || $enabled;

            $command = $entry['command'];
            if (! is_string($command) || $command === '') {
                throw new ConfigException("{$label}.command must be a non-empty string");
            }

            $args = $entry['args'];
            if (! is_array($args) || ! array_is_list($args) || array_filter($args, 'is_string') !== $args) {
                throw new ConfigException("{$label}.args must be a list of strings");
            }

            $promptVia = $entry['prompt_via'];
            if (! in_array($promptVia, [ReviewerConfig::PROMPT_VIA_ARG, ReviewerConfig::PROMPT_VIA_STDIN], true)) {
                throw new ConfigException("{$label}.prompt_via must be 'arg' or 'stdin'");
            }

            $model = $entry['model'];
            if ($model !== null && ! is_string($model)) {
                throw new ConfigException("{$label}.model must be a string or null");
            }

            $resultPath = $entry['result_path'];
            if ($resultPath !== null && (! is_string($resultPath) || $resultPath === '')) {
                throw new ConfigException("{$label}.result_path must be a non-empty string or null");
            }

            $timeout = $entry['timeout'];
            if (! is_int($timeout) || $timeout < 1) {
                throw new ConfigException("{$label}.timeout must be a positive integer");
            }

            $paid = $entry['paid'] ?? false;
            if (! is_bool($paid)) {
                throw new ConfigException("{$label}.paid must be a boolean");
            }

            $parsed[] = new ReviewerConfig(
                id: $id,
                enabled: $enabled,
                command: $command,
                args: array_values($args),
                promptVia: $promptVia,
                model: $model,
                resultPath: $resultPath,
                timeout: $timeout,
                paid: $paid,
            );
        }

        if (! $anyEnabled) {
            throw new ConfigException('at least one reviewer must be enabled');
        }

        return $parsed;
    }

    /**
     * @param  list<ReviewerConfig>  $reviewers
     */
    private function parseSynthesizer(mixed $data, string $baseDir, array $reviewers): SynthesizerConfig
    {
        if (! is_array($data)) {
            throw new ConfigException('synthesizer must be an object');
        }

        $this->assertKnownKeys($data, self::SYNTHESIZER_FIELDS, 'synthesizer');
        $this->assertRequiredKeys($data, self::SYNTHESIZER_FIELDS, 'synthesizer');

        $reviewerId = $data['reviewer_id'];
        if (! is_string($reviewerId) || $reviewerId === '') {
            throw new ConfigException('synthesizer.reviewer_id must be a non-empty string');
        }

        $promptFile = $data['prompt_file'];
        if (! is_string($promptFile) || $promptFile === '') {
            throw new ConfigException('synthesizer.prompt_file must be a non-empty string');
        }

        $known = array_map(static fn (ReviewerConfig $r): string => $r->id, $reviewers);
        if (! in_array($reviewerId, $known, true)) {
            throw new ConfigException("synthesizer.reviewer_id '{$reviewerId}' does not match any reviewer");
        }

        return new SynthesizerConfig(
            reviewerId: $reviewerId,
            promptFile: $this->resolvePath($promptFile, $baseDir),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $allowed
     */
    private function assertKnownKeys(array $data, array $allowed, string $label): void
    {
        $unknown = array_diff(array_keys($data), $allowed);
        if ($unknown !== []) {
            throw new ConfigException("{$label}: unknown field(s): ".implode(', ', $unknown));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $required
     */
    private function assertRequiredKeys(array $data, array $required, string $label): void
    {
        $missing = array_diff($required, array_keys($data));
        if ($missing !== []) {
            throw new ConfigException("{$label}: missing required field(s): ".implode(', ', $missing));
        }
    }

    private function resolvePath(string $path, string $baseDir): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $baseDir.'/'.$path;
    }
}
