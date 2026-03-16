<?php

declare(strict_types=1);

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Vault\Application;

it('registers all expected commands', function (): void {
    $app = new Application();
    $app->setAutoExit(false);

    $commands = $app->all();

    // Read commands
    expect($commands)->toHaveKey('briefing')
        ->toHaveKey('todos')
        ->toHaveKey('search')
        ->toHaveKey('stats')
        ->toHaveKey('recent')
        ->toHaveKey('orphans')
        ->toHaveKey('integrity')
        ->toHaveKey('rebuild');

    // Book commands
    expect($commands)->toHaveKey('books:list')
        ->toHaveKey('books:author')
        ->toHaveKey('books:series')
        ->toHaveKey('books:rating')
        ->toHaveKey('books:recent')
        ->toHaveKey('books:stats');

    // Db commands
    expect($commands)->toHaveKey('db:upsert-doc')
        ->toHaveKey('db:set-tags')
        ->toHaveKey('db:add-link')
        ->toHaveKey('db:upsert-book')
        ->toHaveKey('db:add-read')
        ->toHaveKey('db:update-status')
        ->toHaveKey('db:add-source')
        ->toHaveKey('db:add-todo');
});

it('can run the list command', function (): void {
    $app = new Application();
    $app->setAutoExit(false);

    $input = new ArrayInput(['command' => 'list']);
    $output = new BufferedOutput();

    $exitCode = $app->run($input, $output);

    expect($exitCode)->toBe(0)
        ->and($output->fetch())->toContain('vault');
});
