<?php

declare(strict_types=1);

use Vault\Command\Movies\MoviesDirectorCommand;

it('filters movies by director name with partial match', function (): void {
    $db = freshDb();

    seedMovie($db, ['title' => 'Blade Runner', 'id' => 'movie-br'], ['director' => 'Ridley Scott', 'year' => 1982, 'rating' => 'S']);
    seedMovie($db, ['title' => 'Alien', 'id' => 'movie-alien'], ['director' => 'Ridley Scott', 'year' => 1979, 'rating' => 'A']);
    seedMovie($db, ['title' => 'The Matrix', 'id' => 'movie-matrix'], ['director' => 'Wachowskis', 'year' => 1999, 'rating' => 'A']);

    $result = runCommand(new MoviesDirectorCommand($db), ['name' => 'Scott']);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Blade Runner')
        ->and($result['output'])->toContain('Alien')
        ->and($result['output'])->not->toContain('The Matrix')
        ->and($result['output'])->toContain('2 movie(s) found.');
});

it('shows no results for unknown director', function (): void {
    $db = freshDb();

    seedMovie($db, ['title' => 'Blade Runner', 'id' => 'movie-br'], ['director' => 'Ridley Scott']);

    $result = runCommand(new MoviesDirectorCommand($db), ['name' => 'Spielberg']);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('No movies found for director matching "Spielberg".');
});
