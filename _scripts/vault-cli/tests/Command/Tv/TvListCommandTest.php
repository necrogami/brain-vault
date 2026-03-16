<?php

declare(strict_types=1);

use Vault\Command\Tv\TvListCommand;

it('lists all TV shows with title, creator, and rating', function (): void {
    $db = freshDb();

    seedTvShow($db, ['title' => 'Breaking Bad', 'id' => 'tv-bb'], ['creator' => 'Vince Gilligan', 'year_start' => 2008, 'total_seasons' => 5, 'seasons_watched' => 5, 'rating' => 'S']);
    seedTvShow($db, ['title' => 'The Wire', 'id' => 'tv-wire'], ['creator' => 'David Simon', 'year_start' => 2002, 'total_seasons' => 5, 'seasons_watched' => 3, 'rating' => 'A']);

    $result = runCommand(new TvListCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Breaking Bad')
        ->and($result['output'])->toContain('Vince Gilligan')
        ->and($result['output'])->toContain('S')
        ->and($result['output'])->toContain('The Wire')
        ->and($result['output'])->toContain('David Simon')
        ->and($result['output'])->toContain('A')
        ->and($result['output'])->toContain('2 show(s) total.');
});

it('shows progress as seasons watched over total', function (): void {
    $db = freshDb();

    seedTvShow($db, ['title' => 'Lost', 'id' => 'tv-lost'], ['creator' => 'J.J. Abrams', 'year_start' => 2004, 'total_seasons' => 6, 'seasons_watched' => 2, 'rating' => 'B']);

    $result = runCommand(new TvListCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('2/6');
});

it('shows empty state when no TV shows exist', function (): void {
    $db = freshDb();

    $result = runCommand(new TvListCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('No TV shows found.');
});
