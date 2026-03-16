<?php

declare(strict_types=1);

use Vault\Command\Games\GamesStatsCommand;

it('shows rating breakdown', function (): void {
    $db = freshDb();

    seedGame($db, ['title' => 'Game S1', 'id' => 'game-s1'], ['rating' => 'S']);
    seedGame($db, ['title' => 'Game A1', 'id' => 'game-a1'], ['rating' => 'A']);
    seedGame($db, ['title' => 'Game A2', 'id' => 'game-a2'], ['rating' => 'A']);
    seedGame($db, ['title' => 'Game B1', 'id' => 'game-b1'], ['rating' => 'B']);

    $result = runCommand(new GamesStatsCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Rating Breakdown')
        ->and($result['output'])->toContain('S')
        ->and($result['output'])->toContain('A')
        ->and($result['output'])->toContain('B');
});

it('shows platform breakdown', function (): void {
    $db = freshDb();

    seedGame($db, ['title' => 'Game PC1', 'id' => 'game-pc1'], ['platform' => 'PC']);
    seedGame($db, ['title' => 'Game PC2', 'id' => 'game-pc2'], ['platform' => 'PC']);
    seedGame($db, ['title' => 'Game SW1', 'id' => 'game-sw1'], ['platform' => 'Switch']);

    $result = runCommand(new GamesStatsCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Platform Breakdown')
        ->and($result['output'])->toContain('PC')
        ->and($result['output'])->toContain('Switch');
});

it('shows total hours and most played', function (): void {
    $db = freshDb();

    seedGame($db, ['title' => 'Elden Ring', 'id' => 'game-elden'], ['developer' => 'FromSoftware', 'hours_played' => 120.5]);
    seedGame($db, ['title' => 'Hades', 'id' => 'game-hades'], ['developer' => 'Supergiant Games', 'hours_played' => 85.0]);

    $result = runCommand(new GamesStatsCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Total Hours Played')
        ->and($result['output'])->toContain('205.5 hours across 2 game(s)')
        ->and($result['output'])->toContain('Most Played')
        ->and($result['output'])->toContain('Elden Ring')
        ->and($result['output'])->toContain('Hades');
});
