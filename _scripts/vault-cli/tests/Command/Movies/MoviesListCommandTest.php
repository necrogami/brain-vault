<?php

declare(strict_types=1);

use Vault\Command\Movies\MoviesListCommand;

it('lists all movies with title, director, year, and rating', function (): void {
    $db = freshDb();

    seedMovie($db, ['title' => 'Blade Runner', 'id' => 'movie-br'], ['director' => 'Ridley Scott', 'year' => 1982, 'rating' => 'S']);
    seedMovie($db, ['title' => 'The Matrix', 'id' => 'movie-matrix'], ['director' => 'Wachowskis', 'year' => 1999, 'rating' => 'A']);

    $result = runCommand(new MoviesListCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Blade Runner')
        ->and($result['output'])->toContain('Ridley Scott')
        ->and($result['output'])->toContain('1982')
        ->and($result['output'])->toContain('S')
        ->and($result['output'])->toContain('The Matrix')
        ->and($result['output'])->toContain('Wachowskis')
        ->and($result['output'])->toContain('1999')
        ->and($result['output'])->toContain('A')
        ->and($result['output'])->toContain('2 movie(s) total.');
});

it('shows empty state when no movies exist', function (): void {
    $db = freshDb();

    $result = runCommand(new MoviesListCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('No movies found.');
});
