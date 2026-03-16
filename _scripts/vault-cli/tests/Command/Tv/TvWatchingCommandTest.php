<?php

declare(strict_types=1);

use Vault\Command\Tv\TvWatchingCommand;

it('lists only currently watching shows', function (): void {
    $db = freshDb();

    seedTvShow($db, ['title' => 'Breaking Bad', 'id' => 'tv-bb', 'status' => 'growing'], ['creator' => 'Vince Gilligan', 'total_seasons' => 5, 'seasons_watched' => 3]);
    seedTvShow($db, ['title' => 'The Wire', 'id' => 'tv-wire', 'status' => 'mature'], ['creator' => 'David Simon', 'total_seasons' => 5, 'seasons_watched' => 5]);
    seedTvShow($db, ['title' => 'Severance', 'id' => 'tv-sev', 'status' => 'growing'], ['creator' => 'Dan Erickson', 'total_seasons' => 2, 'seasons_watched' => 1]);

    $result = runCommand(new TvWatchingCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Breaking Bad')
        ->and($result['output'])->toContain('Severance')
        ->and($result['output'])->not->toContain('The Wire')
        ->and($result['output'])->toContain('2 show(s) currently watching.');
});

it('shows progress for currently watching shows', function (): void {
    $db = freshDb();

    seedTvShow($db, ['title' => 'Succession', 'id' => 'tv-succ', 'status' => 'growing'], ['creator' => 'Jesse Armstrong', 'total_seasons' => 4, 'seasons_watched' => 2]);

    $result = runCommand(new TvWatchingCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('2/4');
});

it('shows empty state when nothing is being watched', function (): void {
    $db = freshDb();

    seedTvShow($db, ['title' => 'The Wire', 'id' => 'tv-wire', 'status' => 'mature'], ['creator' => 'David Simon']);

    $result = runCommand(new TvWatchingCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('No shows currently being watched.');
});
