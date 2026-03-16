<?php

declare(strict_types=1);

use Vault\Command\Tv\TvStatsCommand;

it('shows rating breakdown', function (): void {
    $db = freshDb();

    seedTvShow($db, ['title' => 'Show S1', 'id' => 'tv-s1'], ['rating' => 'S']);
    seedTvShow($db, ['title' => 'Show A1', 'id' => 'tv-a1'], ['rating' => 'A']);
    seedTvShow($db, ['title' => 'Show A2', 'id' => 'tv-a2'], ['rating' => 'A']);
    seedTvShow($db, ['title' => 'Show B1', 'id' => 'tv-b1'], ['rating' => 'B']);

    $result = runCommand(new TvStatsCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Rating Breakdown')
        ->and($result['output'])->toContain('S')
        ->and($result['output'])->toContain('A')
        ->and($result['output'])->toContain('B');
});

it('shows status overview with watching, completed, and dropped counts', function (): void {
    $db = freshDb();

    seedTvShow($db, ['title' => 'Watching Show', 'id' => 'tv-w1', 'status' => 'growing'], ['rating' => 'A']);
    seedTvShow($db, ['title' => 'Completed Show', 'id' => 'tv-c1', 'status' => 'mature'], ['rating' => 'S']);
    seedTvShow($db, ['title' => 'Dropped Show', 'id' => 'tv-d1', 'status' => 'closed'], ['rating' => 'D']);

    $result = runCommand(new TvStatsCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Status Overview')
        ->and($result['output'])->toContain('Currently watching')
        ->and($result['output'])->toContain('Completed')
        ->and($result['output'])->toContain('Dropped')
        ->and($result['output'])->toContain('Total');
});

it('handles empty state gracefully', function (): void {
    $db = freshDb();

    $result = runCommand(new TvStatsCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Rating Breakdown')
        ->and($result['output'])->toContain('No rated shows found.')
        ->and($result['output'])->toContain('Total');
});
