<?php

declare(strict_types=1);

use Vault\Command\Movies\MoviesStatsCommand;

it('shows rating breakdown', function (): void {
    $db = freshDb();

    seedMovie($db, ['title' => 'Movie S1', 'id' => 'movie-s1'], ['rating' => 'S']);
    seedMovie($db, ['title' => 'Movie A1', 'id' => 'movie-a1'], ['rating' => 'A']);
    seedMovie($db, ['title' => 'Movie A2', 'id' => 'movie-a2'], ['rating' => 'A']);
    seedMovie($db, ['title' => 'Movie B1', 'id' => 'movie-b1'], ['rating' => 'B']);

    $result = runCommand(new MoviesStatsCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Rating Breakdown (All Time)')
        ->and($result['output'])->toContain('S')
        ->and($result['output'])->toContain('A')
        ->and($result['output'])->toContain('B');
});

it('shows this year stats', function (): void {
    $db = freshDb();

    $id1 = seedMovie($db, ['title' => 'Movie One', 'id' => 'movie-one'], ['rating' => 'A']);
    $id2 = seedMovie($db, ['title' => 'Movie Two', 'id' => 'movie-two'], ['rating' => 'B']);

    $currentYear = date('Y');
    $db->execute('INSERT INTO watches (doc_id, date_watched) VALUES (:id, :date)', [':id' => $id1, ':date' => "{$currentYear}-02-15"]);
    $db->execute('INSERT INTO watches (doc_id, date_watched) VALUES (:id, :date)', [':id' => $id2, ':date' => "{$currentYear}-03-01"]);

    $result = runCommand(new MoviesStatsCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('This Year')
        ->and($result['output'])->toContain('Total watched this year: 2');
});

it('shows most re-watched movies', function (): void {
    $db = freshDb();

    $id1 = seedMovie($db, ['title' => 'Blade Runner', 'id' => 'movie-br'], ['director' => 'Ridley Scott', 'rating' => 'S']);
    $id2 = seedMovie($db, ['title' => 'The Matrix', 'id' => 'movie-matrix'], ['director' => 'Wachowskis', 'rating' => 'A']);

    // Blade Runner watched 3 times
    $db->execute('INSERT INTO watches (doc_id, date_watched) VALUES (:id, :date)', [':id' => $id1, ':date' => '2024-01-10']);
    $db->execute('INSERT INTO watches (doc_id, date_watched) VALUES (:id, :date)', [':id' => $id1, ':date' => '2025-06-15']);
    $db->execute('INSERT INTO watches (doc_id, date_watched) VALUES (:id, :date)', [':id' => $id1, ':date' => '2026-03-01']);

    // The Matrix watched only once (should not appear in re-watched list)
    $db->execute('INSERT INTO watches (doc_id, date_watched) VALUES (:id, :date)', [':id' => $id2, ':date' => '2026-02-20']);

    $result = runCommand(new MoviesStatsCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Most Re-watched')
        ->and($result['output'])->toContain('Blade Runner')
        ->and($result['output'])->toContain('3');
});
