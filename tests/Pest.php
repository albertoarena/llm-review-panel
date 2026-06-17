<?php

declare(strict_types=1);

function writeTempConfig(array $data, ?string $dir = null): string
{
    $dir ??= sys_get_temp_dir().'/llm-review-panel-test-'.bin2hex(random_bytes(6));
    if (! is_dir($dir)) {
        mkdir($dir, 0o777, true);
    }
    $path = $dir.'/config.json';
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return $path;
}

function validConfigData(array $overrides = []): array
{
    $base = [
        'reviewers' => [
            [
                'id' => 'claude',
                'enabled' => true,
                'command' => 'claude',
                'args' => ['-p', '{prompt}'],
                'prompt_via' => 'arg',
                'model' => null,
                'result_path' => 'result',
                'timeout' => 300,
            ],
        ],
        'synthesizer' => [
            'reviewer_id' => 'claude',
            'prompt_file' => 'synthesis.md',
        ],
        'rubric_file' => 'rubric.md',
        'output_dir' => '.aireview/runs',
        'max_parallel' => 3,
    ];

    foreach ($overrides as $key => $value) {
        if ($key === 'synthesizer' && is_array($value)) {
            $base['synthesizer'] = array_replace($base['synthesizer'], $value);
        } else {
            $base[$key] = $value;
        }
    }

    return $base;
}
