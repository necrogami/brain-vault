<?php

declare(strict_types=1);

use Vault\Command\Games\GamesListCommand;

it('lists all games with title, developer, platform, hours, and rating', function (): void {
    $db = freshDb();

    seedGame($db, ['title' => 'Elden Ring', 'id' => 'game-elden'], ['developer' => 'FromSoftware', 'platform' => 'PC', 'hours_played' => 120.5, 'rating' => 'S']);
    seedGame($db, ['title' => 'Hades', 'id' => 'game-hades'], ['developer' => 'Supergiant Games', 'platform' => 'Switch', 'hours_played' => 85.0, 'rating' => 'A']);

    $result = runCommand(new GamesListCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Elden Ring')
        ->and($result['output'])->toContain('FromSoftware')
        ->and($result['output'])->toContain('PC')
        ->and($result['output'])->toContain('S')
        ->and($result['output'])->toContain('Hades')
        ->and($result['output'])->toContain('Supergiant Games')
        ->and($result['output'])->toContain('Switch')
        ->and($result['output'])->toContain('A')
        ->and($result['output'])->toContain('2 game(s) total.');
});

it('shows empty state when no games exist', function (): void {
    $db = freshDb();

    $result = runCommand(new GamesListCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('No games found.');
});

it('sorts games by developer then title', function (): void {
    $db = freshDb();

    seedGame($db, ['title' => 'Zelda', 'id' => 'game-zelda'], ['developer' => 'Nintendo']);
    seedGame($db, ['title' => 'Bloodborne', 'id' => 'game-bb'], ['developer' => 'FromSoftware']);
    seedGame($db, ['title' => 'Elden Ring', 'id' => 'game-elden'], ['developer' => 'FromSoftware']);

    $result = runCommand(new GamesListCommand($db));

    // FromSoftware games should appear before Nintendo
    $bbPos = strpos($result['output'], 'Bloodborne');
    $eldenPos = strpos($result['output'], 'Elden Ring');
    $zeldaPos = strpos($result['output'], 'Zelda');

    expect($bbPos)->toBeLessThan($zeldaPos)
        ->and($eldenPos)->toBeLessThan($zeldaPos);
});
