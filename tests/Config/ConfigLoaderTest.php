<?php

declare(strict_types=1);

use LlmReviewPanel\Config\Config;
use LlmReviewPanel\Config\ConfigException;
use LlmReviewPanel\Config\ConfigLoader;
use LlmReviewPanel\Config\ReviewerConfig;

beforeEach(function (): void {
    $this->loader = new ConfigLoader();
});

it('loads a valid config', function (): void {
    $path = writeTempConfig(validConfigData());

    $config = $this->loader->load($path);

    expect($config)->toBeInstanceOf(Config::class)
        ->and($config->reviewers)->toHaveCount(1)
        ->and($config->reviewers[0])->toBeInstanceOf(ReviewerConfig::class)
        ->and($config->reviewers[0]->id)->toBe('claude')
        ->and($config->reviewers[0]->enabled)->toBeTrue()
        ->and($config->reviewers[0]->promptVia)->toBe('arg')
        ->and($config->reviewers[0]->resultPath)->toBe('result')
        ->and($config->reviewers[0]->timeout)->toBe(300)
        ->and($config->synthesizer->reviewerId)->toBe('claude')
        ->and($config->maxParallel)->toBe(3);
});

it('resolves rubric, synthesis and output paths relative to the config file directory', function (): void {
    $dir = sys_get_temp_dir().'/llm-review-panel-test-'.bin2hex(random_bytes(6));
    $path = writeTempConfig(validConfigData(), $dir);
    $canonical = dirname((string) realpath($path));

    $config = $this->loader->load($path);

    expect($config->rubricFile)->toBe($canonical.'/rubric.md')
        ->and($config->synthesizer->promptFile)->toBe($canonical.'/synthesis.md')
        ->and($config->outputDir)->toBe($canonical.'/.aireview/runs');
});

it('keeps absolute paths absolute', function (): void {
    $path = writeTempConfig(validConfigData([
        'rubric_file' => '/etc/rubric.md',
        'synthesizer' => ['prompt_file' => '/etc/synthesis.md'],
        'output_dir' => '/tmp/out',
    ]));

    $config = $this->loader->load($path);

    expect($config->rubricFile)->toBe('/etc/rubric.md')
        ->and($config->synthesizer->promptFile)->toBe('/etc/synthesis.md')
        ->and($config->outputDir)->toBe('/tmp/out');
});

it('throws when the file does not exist', function (): void {
    $this->loader->load('/nonexistent/config.json');
})->throws(ConfigException::class, 'not found');

it('throws on invalid JSON', function (): void {
    $dir = sys_get_temp_dir().'/llm-review-panel-test-'.bin2hex(random_bytes(6));
    mkdir($dir, 0o777, true);
    $path = $dir.'/config.json';
    file_put_contents($path, '{ this is not json');

    $this->loader->load($path);
})->throws(ConfigException::class, 'JSON');

it('throws when a required top-level field is missing', function (): void {
    $data = validConfigData();
    unset($data['rubric_file']);
    $path = writeTempConfig($data);

    $this->loader->load($path);
})->throws(ConfigException::class, 'rubric_file');

it('throws on unknown top-level fields', function (): void {
    $path = writeTempConfig(validConfigData(['surprise' => true]));

    $this->loader->load($path);
})->throws(ConfigException::class, 'surprise');

it('throws when reviewers is empty', function (): void {
    $path = writeTempConfig(validConfigData(['reviewers' => []]));

    $this->loader->load($path);
})->throws(ConfigException::class, 'reviewers');

it('throws when no reviewer is enabled', function (): void {
    $data = validConfigData();
    $data['reviewers'][0]['enabled'] = false;
    $path = writeTempConfig($data);

    $this->loader->load($path);
})->throws(ConfigException::class, 'enabled');

it('throws when synthesizer.reviewer_id does not match any reviewer', function (): void {
    $data = validConfigData();
    $data['synthesizer']['reviewer_id'] = 'ghost';
    $path = writeTempConfig($data);

    $this->loader->load($path);
})->throws(ConfigException::class, 'ghost');

it('throws on invalid prompt_via', function (): void {
    $data = validConfigData();
    $data['reviewers'][0]['prompt_via'] = 'pigeon';
    $path = writeTempConfig($data);

    $this->loader->load($path);
})->throws(ConfigException::class, 'prompt_via');

it('throws on duplicate reviewer ids', function (): void {
    $data = validConfigData();
    $data['reviewers'][] = $data['reviewers'][0];
    $path = writeTempConfig($data);

    $this->loader->load($path);
})->throws(ConfigException::class, 'duplicate');

it('throws on unknown reviewer fields', function (): void {
    $data = validConfigData();
    $data['reviewers'][0]['oops'] = true;
    $path = writeTempConfig($data);

    $this->loader->load($path);
})->throws(ConfigException::class, 'oops');

it('throws on non-positive timeout', function (): void {
    $data = validConfigData();
    $data['reviewers'][0]['timeout'] = 0;
    $path = writeTempConfig($data);

    $this->loader->load($path);
})->throws(ConfigException::class, 'timeout');

it('defaults paid to false when omitted', function (): void {
    $path = writeTempConfig(validConfigData());

    $config = $this->loader->load($path);

    expect($config->reviewers[0]->paid)->toBeFalse();
});

it('parses paid=true when set', function (): void {
    $data = validConfigData();
    $data['reviewers'][0]['paid'] = true;
    $path = writeTempConfig($data);

    $config = $this->loader->load($path);

    expect($config->reviewers[0]->paid)->toBeTrue();
});

it('throws when paid is not a boolean', function (): void {
    $data = validConfigData();
    $data['reviewers'][0]['paid'] = 'yes';
    $path = writeTempConfig($data);

    $this->loader->load($path);
})->throws(LlmReviewPanel\Config\ConfigException::class, 'paid');

it('exposes enabledReviewers helper', function (): void {
    $data = validConfigData();
    $data['reviewers'][] = [
        'id' => 'opencode',
        'enabled' => false,
        'command' => 'opencode',
        'args' => ['{prompt}'],
        'prompt_via' => 'arg',
        'model' => null,
        'result_path' => null,
        'timeout' => 300,
    ];
    $path = writeTempConfig($data);

    $config = $this->loader->load($path);

    expect($config->enabledReviewers())->toHaveCount(1)
        ->and($config->enabledReviewers()[0]->id)->toBe('claude');
});
