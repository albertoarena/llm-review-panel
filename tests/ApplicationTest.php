<?php

declare(strict_types=1);

use LlmReviewPanel\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

it('boots with an empty command list', function (): void {
    $app = new Application();
    $app->setAutoExit(false);
    $app->setCatchExceptions(false);

    $tester = new ApplicationTester($app);
    $tester->run(['command' => 'list']);

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain(Application::NAME);
});
