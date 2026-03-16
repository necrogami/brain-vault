<?php

declare(strict_types=1);

use Vault\Command\Games\GamesRatingCommand;

it('filters games by rating tier', function (): void {
    $db = freshDb();

    seedGame($db, ['title' => 'Elden Ring', 'id' => 'game-elden'], ['developer' => 'FromSoftware', 'rating' => 'S']);
    seedGame($db, ['title' => 'Hades', 'id' => 'game-hades'], ['developer' => 'Supergiant Games', 'rating' => 'A']);
    seedGame($db, ['title' => 'Celeste', 'id' => 'game-celeste'], ['developer' => 'Maddy Makes Games', 'rating' => 'A']);
    seedGame($db, ['title' => 'Mediocre Game', 'id' => 'game-mid'], ['developer' => 'Some Studio', 'rating' => 'C']);

    $result = runCommand(new GamesRatingCommand($db), ['tier' => 'A']);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Hades')
        ->and($result['output'])->toContain('Celeste')
        ->and($result['output'])->not->toContain('Elden Ring')
        ->and($result['output'])->not->toContain('Mediocre Game')
        ->and($result['output'])->toContain('2 game(s) rated A.');
});

it('rejects invalid rating tier', function (): void {
    $db = freshDb();

    $result = runCommand(new GamesRatingCommand($db), ['tier' => 'Z']);

    expect($result['code'])->toBe(1)
        ->and($result['output'])->toContain('Invalid tier "Z"')
        ->and($result['output'])->toContain('Valid tiers:');
});

it('shows empty state when no games match the rating', function (): void {
    $db = freshDb();

    seedGame($db, ['title' => 'Elden Ring', 'id' => 'game-elden'], ['rating' => 'S']);

    $result = runCommand(new GamesRatingCommand($db), ['tier' => 'F']);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('No games with rating "F".');
});
