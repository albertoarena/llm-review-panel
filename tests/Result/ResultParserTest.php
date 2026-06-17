<?php

declare(strict_types=1);

use LlmReviewPanel\Config\ReviewerConfig;
use LlmReviewPanel\Result\ResultParser;

beforeEach(function (): void {
    $this->parser = new ResultParser();
});

function rev(?string $resultPath = null): ReviewerConfig
{
    return new ReviewerConfig(
        id: 'r',
        enabled: true,
        command: 'cli',
        args: ['-p', '{prompt}'],
        promptVia: ReviewerConfig::PROMPT_VIA_ARG,
        model: null,
        resultPath: $resultPath,
        timeout: 300,
    );
}

it('extracts a top-level key when result_path is set', function (): void {
    $output = $this->parser->parse(rev('result'), '{"result":"the review text"}');

    expect($output->content)->toBe('the review text')
        ->and($output->unstructured)->toBeFalse();
});

it('extracts a nested key with dot-path', function (): void {
    $output = $this->parser->parse(rev('data.text'), '{"data":{"text":"nested review"}}');

    expect($output->content)->toBe('nested review')
        ->and($output->unstructured)->toBeFalse();
});

it('returns full stdout as JSON-tagged when result_path is null and stdout parses', function (): void {
    $stdout = '{"summary":"hi","findings":[],"open_questions":[],"verdict":"ship"}';

    $output = $this->parser->parse(rev(null), $stdout);

    expect($output->content)->toBe($stdout)
        ->and($output->unstructured)->toBeFalse();
});

it('tags as unstructured when stdout is not JSON and result_path is null', function (): void {
    $output = $this->parser->parse(rev(null), 'just some prose, not JSON');

    expect($output->content)->toBe('just some prose, not JSON')
        ->and($output->unstructured)->toBeTrue();
});

it('tags as unstructured when result_path is set but stdout is not JSON', function (): void {
    $output = $this->parser->parse(rev('result'), 'plain text, no JSON');

    expect($output->content)->toBe('plain text, no JSON')
        ->and($output->unstructured)->toBeTrue();
});

it('tags as unstructured when the result_path key is missing', function (): void {
    $output = $this->parser->parse(rev('result'), '{"other":"value"}');

    expect($output->unstructured)->toBeTrue();
});
