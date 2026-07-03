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

it('strips ```json fences when result_path is null', function (): void {
    $stdout = "```json\n".'{"summary":"hi","findings":[],"open_questions":[],"verdict":"ship"}'."\n```";

    $output = $this->parser->parse(rev(null), $stdout);

    expect($output->unstructured)->toBeFalse()
        ->and($output->content)->toBe('{"summary":"hi","findings":[],"open_questions":[],"verdict":"ship"}');
});

it('strips bare ``` fences when result_path is null', function (): void {
    $stdout = "```\n".'{"verdict":"ship"}'."\n```";

    $output = $this->parser->parse(rev(null), $stdout);

    expect($output->unstructured)->toBeFalse()
        ->and($output->content)->toBe('{"verdict":"ship"}');
});

it('strips fences before extracting via result_path', function (): void {
    $stdout = "```json\n".'{"result":"the review"}'."\n```";

    $output = $this->parser->parse(rev('result'), $stdout);

    expect($output->unstructured)->toBeFalse()
        ->and($output->content)->toBe('the review');
});

it('demotes prose-wrapped JSON to unstructured without attempting repair', function (): void {
    $stdout = 'Here is my review: {"verdict":"ship"} hope that helps!';

    $output = $this->parser->parse(rev(null), $stdout);

    expect($output->unstructured)->toBeTrue()
        ->and($output->content)->toBe($stdout);
});

it('handles trailing whitespace and newlines', function (): void {
    $stdout = "\n\n".'{"verdict":"ship"}'."\n\n  \n";

    $output = $this->parser->parse(rev(null), $stdout);

    expect($output->unstructured)->toBeFalse()
        ->and($output->content)->toBe('{"verdict":"ship"}');
});

it('extracts deeply nested keys via dot-path', function (): void {
    $stdout = '{"a":{"b":{"c":"deep value"}}}';

    $output = $this->parser->parse(rev('a.b.c'), $stdout);

    expect($output->unstructured)->toBeFalse()
        ->and($output->content)->toBe('deep value');
});

it('tags as unstructured when an intermediate dot-path key is missing', function (): void {
    $stdout = '{"a":{"x":"oops"}}';

    $output = $this->parser->parse(rev('a.b.c'), $stdout);

    expect($output->unstructured)->toBeTrue();
});

it('tags as unstructured when result_path resolves to a non-string', function (): void {
    $stdout = '{"result":{"nested":"object"}}';

    $output = $this->parser->parse(rev('result'), $stdout);

    expect($output->unstructured)->toBeTrue();
});

it('rejects array indexing in dot-path with a clear error', function (): void {
    $this->parser->parse(rev('choices[0].text'), '{"choices":[{"text":"x"}]}');
})->throws(InvalidArgumentException::class, 'array indexing');

it('rejects a numeric dot-path segment as array indexing', function (): void {
    $this->parser->parse(rev('choices.0.text'), '{"choices":[{"text":"x"}]}');
})->throws(InvalidArgumentException::class, 'array indexing');

it('rejects a leading numeric dot-path segment', function (): void {
    $this->parser->parse(rev('0'), '["x"]');
})->throws(InvalidArgumentException::class, 'array indexing');

it('allows keys that merely contain digits', function (): void {
    $output = $this->parser->parse(rev('data.item2'), '{"data":{"item2":"ok"}}');

    expect($output->unstructured)->toBeFalse()
        ->and($output->content)->toBe('ok');
});

it('strips inner fences from content extracted via result_path', function (): void {
    $inner = '{"summary":"hi","verdict":"ship"}';
    $stdout = json_encode(['result' => "```json\n{$inner}\n```"]);

    $output = $this->parser->parse(rev('result'), $stdout);

    expect($output->unstructured)->toBeFalse()
        ->and($output->content)->toBe($inner);
});

it('preserves the raw stdout on the output when demoted to unstructured', function (): void {
    $stdout = 'not json at all';

    $output = $this->parser->parse(rev(null), $stdout);

    expect($output->unstructured)->toBeTrue()
        ->and($output->content)->toBe($stdout);
});
