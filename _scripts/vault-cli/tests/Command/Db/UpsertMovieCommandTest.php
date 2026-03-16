<?php

declare(strict_types=1);

use Vault\Command\Db\UpsertMovieCommand;

it('inserts a movie record', function (): void {
    $db = freshDb();
    $id = seedDocument($db, [
        'id' => 'movie-test',
        'domain' => 'personal',
        'subdomain' => 'movies',
        'title' => 'Test Movie',
    ]);

    $result = runCommand(new UpsertMovieCommand($db), [
        '--id' => $id,
        '--director' => 'Test Director',
        '--rating' => 'A',
        '--year' => '2024',
    ]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain("ok: upserted movie '{$id}'");

    $row = $db->fetchOne('SELECT * FROM movies WHERE doc_id = :id', [':id' => $id]);

    expect($row)->not->toBeNull()
        ->and($row['director'])->toBe('Test Director')
        ->and($row['rating'])->toBe('A')
        ->and((int) $row['year'])->toBe(2024);
});

it('handles optional fields like genre, runtime, poster-url, and imdb-id', function (): void {
    $db = freshDb();

    $id = seedDocument($db, [
        'id' => 'movie-full',
        'title' => 'Full Movie',
        'domain' => 'personal',
        'subdomain' => 'movies',
    ]);

    $result = runCommand(new UpsertMovieCommand($db), [
        '--id' => $id,
        '--director' => 'Christopher Nolan',
        '--year' => '2010',
        '--genre' => 'Sci-Fi',
        '--runtime-min' => '148',
        '--rating' => 'S',
        '--poster-url' => 'https://example.com/poster.jpg',
        '--imdb-id' => 'tt1375666',
    ]);

    expect($result['code'])->toBe(0);

    $row = $db->fetchOne('SELECT * FROM movies WHERE doc_id = :id', [':id' => $id]);

    expect($row['director'])->toBe('Christopher Nolan')
        ->and((int) $row['year'])->toBe(2010)
        ->and($row['genre'])->toBe('Sci-Fi')
        ->and((int) $row['runtime_min'])->toBe(148)
        ->and($row['rating'])->toBe('S')
        ->and($row['poster_url'])->toBe('https://example.com/poster.jpg')
        ->and($row['imdb_id'])->toBe('tt1375666');
});
