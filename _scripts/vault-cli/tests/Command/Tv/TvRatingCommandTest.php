<?php

declare(strict_types=1);

use Vault\Command\Tv\TvRatingCommand;

it('filters TV shows by rating tier', function (): void {
    $db = freshDb();

    seedTvShow($db, ['title' => 'Breaking Bad', 'id' => 'tv-bb'], ['creator' => 'Vince Gilligan', 'year_start' => 2008, 'rating' => 'S']);
    seedTvShow($db, ['title' => 'The Wire', 'id' => 'tv-wire'], ['creator' => 'David Simon', 'year_start' => 2002, 'rating' => 'A']);
    seedTvShow($db, ['title' => 'Severance', 'id' => 'tv-sev'], ['creator' => 'Dan Erickson', 'year_start' => 2022, 'rating' => 'A']);
    seedTvShow($db, ['title' => 'Average Show', 'id' => 'tv-avg'], ['creator' => 'Some Creator', 'year_start' => 2020, 'rating' => 'C']);

    $result = runCommand(new TvRatingCommand($db), ['tier' => 'A']);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('The Wire')
        ->and($result['output'])->toContain('Severance')
        ->and($result['output'])->not->toContain('Breaking Bad')
        ->and($result['output'])->not->toContain('Average Show')
        ->and($result['output'])->toContain('2 show(s) rated A.');
});

it('rejects invalid rating tier', function (): void {
    $db = freshDb();

    $result = runCommand(new TvRatingCommand($db), ['tier' => 'Z']);

    expect($result['code'])->toBe(1)
        ->and($result['output'])->toContain('Invalid tier "Z"')
        ->and($result['output'])->toContain('Valid tiers:');
});

it('shows empty state when no shows match the tier', function (): void {
    $db = freshDb();

    seedTvShow($db, ['title' => 'Breaking Bad', 'id' => 'tv-bb'], ['rating' => 'S']);

    $result = runCommand(new TvRatingCommand($db), ['tier' => 'F']);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('No TV shows with rating "F".');
});
