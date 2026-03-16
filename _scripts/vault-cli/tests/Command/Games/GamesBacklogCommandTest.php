<?php

declare(strict_types=1);

use Vault\Command\Games\GamesBacklogCommand;

it('lists games in the backlog with seed or tbr status', function (): void {
    $db = freshDb();

    seedGame($db, ['title' => 'Cyberpunk 2077', 'id' => 'game-cyber', 'status' => 'tbr'], ['developer' => 'CD Projekt Red', 'platform' => 'PC']);
    seedGame($db, ['title' => 'Hollow Knight', 'id' => 'game-hollow', 'status' => 'seed'], ['developer' => 'Team Cherry', 'platform' => 'Switch']);
    seedGame($db, ['title' => 'Elden Ring', 'id' => 'game-elden', 'status' => 'growing'], ['developer' => 'FromSoftware', 'platform' => 'PC']);

    $result = runCommand(new GamesBacklogCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Cyberpunk 2077')
        ->and($result['output'])->toContain('CD Projekt Red')
        ->and($result['output'])->toContain('Hollow Knight')
        ->and($result['output'])->toContain('Team Cherry')
        ->and($result['output'])->not->toContain('Elden Ring')
        ->and($result['output'])->toContain('2 game(s) in backlog.');
});

it('shows empty state when no games in backlog', function (): void {
    $db = freshDb();

    seedGame($db, ['title' => 'Hades', 'id' => 'game-hades', 'status' => 'mature'], ['developer' => 'Supergiant Games']);

    $result = runCommand(new GamesBacklogCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('No games in the backlog.');
});

it('excludes mature, dormant, and closed games', function (): void {
    $db = freshDb();

    seedGame($db, ['title' => 'Game Mature', 'id' => 'game-mature', 'status' => 'mature'], ['developer' => 'Dev A']);
    seedGame($db, ['title' => 'Game Dormant', 'id' => 'game-dormant', 'status' => 'dormant'], ['developer' => 'Dev B']);
    seedGame($db, ['title' => 'Game Closed', 'id' => 'game-closed', 'status' => 'closed'], ['developer' => 'Dev C']);
    seedGame($db, ['title' => 'Game Backlog', 'id' => 'game-backlog', 'status' => 'tbr'], ['developer' => 'Dev D']);

    $result = runCommand(new GamesBacklogCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Game Backlog')
        ->and($result['output'])->not->toContain('Game Mature')
        ->and($result['output'])->not->toContain('Game Dormant')
        ->and($result['output'])->not->toContain('Game Closed')
        ->and($result['output'])->toContain('1 game(s) in backlog.');
});
