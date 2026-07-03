<?php

declare(strict_types=1);

namespace LlmReviewPanel\Config;

final readonly class Config
{
    /**
     * @param  list<ReviewerConfig>  $reviewers
     */
    public function __construct(
        public array $reviewers,
        public SynthesizerConfig $synthesizer,
        public string $rubricFile,
        public string $outputDir,
        public int $maxParallel,
    ) {
    }

    /**
     * @return list<ReviewerConfig>
     */
    public function enabledReviewers(): array
    {
        return array_values(array_filter(
            $this->reviewers,
            static fn (ReviewerConfig $r): bool => $r->enabled,
        ));
    }

    /**
     * The reviewer that synthesizes. May be disabled: a synthesizer that is not
     * a panel voice acts as a neutral arbiter over the other reviews.
     */
    public function synthesizerReviewer(): ?ReviewerConfig
    {
        foreach ($this->reviewers as $reviewer) {
            if ($reviewer->id === $this->synthesizer->reviewerId) {
                return $reviewer;
            }
        }

        return null;
    }
}
