<?php

declare(strict_types=1);

use Vault\Command\Movies\MoviesRatingCommand;

it('filters movies by rating tier', function (): void {
    $db = freshDb();

    seedMovie($db, ['title' => 'Blade Runner', 'id' => 'movie-br'], ['director' => 'Ridley Scott', 'rating' => 'S']);
    seedMovie($db, ['title' => 'The Matrix', 'id' => 'movie-matrix'], ['director' => 'Wachowskis', 'rating' => 'A']);
    seedMovie($db, ['title' => 'Inception', 'id' => 'movie-inception'], ['director' => 'Christopher Nolan', 'rating' => 'A']);
    seedMovie($db, ['title' => 'Average Film', 'id' => 'movie-avg'], ['director' => 'Some Director', 'rating' => 'C']);

    $result = runCommand(new MoviesRatingCommand($db), ['tier' => 'A']);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('The Matrix')
        ->and($result['output'])->toContain('Inception')
        ->and($result['output'])->not->toContain('Blade Runner')
        ->and($result['output'])->not->toContain('Average Film')
        ->and($result['output'])->toContain('2 movie(s) rated A.');
});

it('rejects invalid rating tier', function (): void {
    $db = freshDb();

    $result = runCommand(new MoviesRatingCommand($db), ['tier' => 'Z']);

    expect($result['code'])->toBe(1)
        ->and($result['output'])->toContain('Invalid tier "Z"')
        ->and($result['output'])->toContain('Valid tiers:');
});
