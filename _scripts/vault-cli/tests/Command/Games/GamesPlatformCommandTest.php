<?php

declare(strict_types=1);

use Vault\Command\Games\GamesPlatformCommand;

it('lists games filtered by platform', function (): void {
    $db = freshDb();

    seedGame($db, ['title' => 'Elden Ring', 'id' => 'game-elden'], ['developer' => 'FromSoftware', 'platform' => 'PC', 'rating' => 'S', 'hours_played' => 120.0]);
    seedGame($db, ['title' => 'Hades', 'id' => 'game-hades'], ['developer' => 'Supergiant Games', 'platform' => 'Switch', 'rating' => 'A', 'hours_played' => 85.0]);
    seedGame($db, ['title' => 'Baldurs Gate 3', 'id' => 'game-bg3'], ['developer' => 'Larian Studios', 'platform' => 'PC', 'rating' => 'S', 'hours_played' => 200.0]);

    $result = runCommand(new GamesPlatformCommand($db), ['name' => 'PC']);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Elden Ring')
        ->and($result['output'])->toContain('Baldurs Gate 3')
        ->and($result['output'])->not->toContain('Hades')
        ->and($result['output'])->toContain('2 game(s) found.');
});

it('uses partial match for platform name', function (): void {
    $db = freshDb();

    seedGame($db, ['title' => 'Zelda TOTK', 'id' => 'game-zelda'], ['developer' => 'Nintendo', 'platform' => 'Nintendo Switch']);
    seedGame($db, ['title' => 'Hades', 'id' => 'game-hades'], ['developer' => 'Supergiant Games', 'platform' => 'Switch']);

    $result = runCommand(new GamesPlatformCommand($db), ['name' => 'Switch']);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Zelda TOTK')
        ->and($result['output'])->toContain('Hades')
        ->and($result['output'])->toContain('2 game(s) found.');
});

it('shows empty state when no games match the platform', function (): void {
    $db = freshDb();

    seedGame($db, ['title' => 'Elden Ring', 'id' => 'game-elden'], ['platform' => 'PC']);

    $result = runCommand(new GamesPlatformCommand($db), ['name' => 'PlayStation']);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('No games found for platform matching "PlayStation".');
});
