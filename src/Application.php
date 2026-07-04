<?php

declare(strict_types=1);

namespace LlmReviewPanel;

use LlmReviewPanel\Console\ReviewCommand;
use LlmReviewPanel\Pipeline\ReviewPipeline;
use LlmReviewPanel\Process\RealProcessRunner;
use Symfony\Component\Console\Application as BaseApplication;

final class Application extends BaseApplication
{
    public const NAME = 'llm-review-panel';

    public const VERSION = '0.1.0';

    public function __construct()
    {
        parent::__construct(self::NAME, self::VERSION);
        $this->addCommand(new ReviewCommand(new ReviewPipeline(new RealProcessRunner())));
    }
}
