<?php

declare(strict_types=1);

use Vault\Command\Movies\MoviesRecentCommand;

it('shows recently watched movies', function (): void {
    $db = freshDb();

    $id1 = seedMovie($db, ['title' => 'Blade Runner', 'id' => 'movie-br'], ['director' => 'Ridley Scott', 'rating' => 'S']);
    $id2 = seedMovie($db, ['title' => 'The Matrix', 'id' => 'movie-matrix'], ['director' => 'Wachowskis', 'rating' => 'A']);

    seedMediaEvent($db, $id1, 'watch', '2026-03-10');
    seedMediaEvent($db, $id2, 'watch', '2026-03-12');

    $result = runCommand(new MoviesRecentCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Blade Runner')
        ->and($result['output'])->toContain('Ridley Scott')
        ->and($result['output'])->toContain('The Matrix')
        ->and($result['output'])->toContain('Wachowskis')
        ->and($result['output'])->toContain('2026-03-12');
});

it('respects limit argument', function (): void {
    $db = freshDb();

    $id1 = seedMovie($db, ['title' => 'Movie One', 'id' => 'movie-one'], ['director' => 'Director A', 'rating' => 'A']);
    $id2 = seedMovie($db, ['title' => 'Movie Two', 'id' => 'movie-two'], ['director' => 'Director B', 'rating' => 'B']);
    $id3 = seedMovie($db, ['title' => 'Movie Three', 'id' => 'movie-three'], ['director' => 'Director C', 'rating' => 'C']);

    seedMediaEvent($db, $id1, 'watch', '2026-03-01');
    seedMediaEvent($db, $id2, 'watch', '2026-03-05');
    seedMediaEvent($db, $id3, 'watch', '2026-03-10');

    $result = runCommand(new MoviesRecentCommand($db), ['n' => '2']);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Movie Three')
        ->and($result['output'])->toContain('Movie Two')
        ->and($result['output'])->not->toContain('Movie One');
});

it('shows empty state when no watches exist', function (): void {
    $db = freshDb();

    seedMovie($db, ['title' => 'Blade Runner', 'id' => 'movie-br'], ['director' => 'Ridley Scott']);

    $result = runCommand(new MoviesRecentCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('No watch history found.');
});
