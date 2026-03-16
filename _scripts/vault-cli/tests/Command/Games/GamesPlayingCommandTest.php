<?php

declare(strict_types=1);

use Vault\Command\Games\GamesPlayingCommand;

it('lists games currently being played', function (): void {
    $db = freshDb();

    seedGame($db, ['title' => 'Elden Ring', 'id' => 'game-elden', 'status' => 'growing'], ['developer' => 'FromSoftware', 'platform' => 'PC', 'hours_played' => 45.0]);
    seedGame($db, ['title' => 'Hades', 'id' => 'game-hades', 'status' => 'mature'], ['developer' => 'Supergiant Games', 'platform' => 'Switch', 'hours_played' => 85.0]);

    $result = runCommand(new GamesPlayingCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Elden Ring')
        ->and($result['output'])->toContain('FromSoftware')
        ->and($result['output'])->toContain('PC')
        ->and($result['output'])->not->toContain('Hades')
        ->and($result['output'])->toContain('1 game(s) in progress.');
});

it('shows empty state when no games are being played', function (): void {
    $db = freshDb();

    seedGame($db, ['title' => 'Hades', 'id' => 'game-hades', 'status' => 'mature'], ['developer' => 'Supergiant Games']);

    $result = runCommand(new GamesPlayingCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('No games currently being played.');
});

it('shows multiple games in progress', function (): void {
    $db = freshDb();

    seedGame($db, ['title' => 'Elden Ring', 'id' => 'game-elden', 'status' => 'growing'], ['developer' => 'FromSoftware', 'hours_played' => 45.0]);
    seedGame($db, ['title' => 'Baldurs Gate 3', 'id' => 'game-bg3', 'status' => 'growing'], ['developer' => 'Larian Studios', 'hours_played' => 20.0]);

    $result = runCommand(new GamesPlayingCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Elden Ring')
        ->and($result['output'])->toContain('Baldurs Gate 3')
        ->and($result['output'])->toContain('2 game(s) in progress.');
});
