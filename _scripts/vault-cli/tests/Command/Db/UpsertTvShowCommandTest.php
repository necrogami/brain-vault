<?php

declare(strict_types=1);

use Vault\Command\Db\UpsertTvShowCommand;

it('inserts a TV show record', function (): void {
    $db = freshDb();
    $id = seedDocument($db, [
        'id' => 'tv-test',
        'domain' => 'personal',
        'subdomain' => 'tv',
        'title' => 'Test Show',
    ]);

    $result = runCommand(new UpsertTvShowCommand($db), [
        '--id' => $id,
        '--creator' => 'Test Creator',
        '--rating' => 'A',
    ]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain("ok: upserted tv show '{$id}'");

    $row = $db->fetchOne('SELECT * FROM tv_shows WHERE doc_id = :id', [':id' => $id]);

    expect($row)->not->toBeNull()
        ->and($row['creator'])->toBe('Test Creator')
        ->and($row['rating'])->toBe('A');
});

it('handles all optional fields', function (): void {
    $db = freshDb();
    $id = seedDocument($db, [
        'id' => 'tv-full',
        'domain' => 'personal',
        'subdomain' => 'tv',
        'title' => 'Full Show',
    ]);

    $result = runCommand(new UpsertTvShowCommand($db), [
        '--id' => $id,
        '--creator' => 'Vince Gilligan',
        '--year-start' => '2008',
        '--year-end' => '2013',
        '--genre' => 'Drama',
        '--total-seasons' => '5',
        '--seasons-watched' => '5',
        '--rating' => 'S',
        '--poster-url' => 'https://example.com/poster.jpg',
        '--imdb-id' => 'tt0903747',
    ]);

    expect($result['code'])->toBe(0);

    $row = $db->fetchOne('SELECT * FROM tv_shows WHERE doc_id = :id', [':id' => $id]);

    expect($row['creator'])->toBe('Vince Gilligan')
        ->and((int) $row['year_start'])->toBe(2008)
        ->and((int) $row['year_end'])->toBe(2013)
        ->and($row['genre'])->toBe('Drama')
        ->and((int) $row['total_seasons'])->toBe(5)
        ->and((int) $row['seasons_watched'])->toBe(5)
        ->and($row['rating'])->toBe('S')
        ->and($row['poster_url'])->toBe('https://example.com/poster.jpg')
        ->and($row['imdb_id'])->toBe('tt0903747');
});

it('replaces an existing TV show record on upsert', function (): void {
    $db = freshDb();
    $id = seedDocument($db, [
        'id' => 'tv-upsert',
        'domain' => 'personal',
        'subdomain' => 'tv',
        'title' => 'Upsert Show',
    ]);

    // First insert
    runCommand(new UpsertTvShowCommand($db), [
        '--id' => $id,
        '--creator' => 'Original Creator',
        '--rating' => 'B',
        '--seasons-watched' => '2',
    ]);

    // Upsert with updated values
    $result = runCommand(new UpsertTvShowCommand($db), [
        '--id' => $id,
        '--creator' => 'Original Creator',
        '--rating' => 'A',
        '--seasons-watched' => '4',
    ]);

    expect($result['code'])->toBe(0);

    $row = $db->fetchOne('SELECT * FROM tv_shows WHERE doc_id = :id', [':id' => $id]);

    expect($row['rating'])->toBe('A')
        ->and((int) $row['seasons_watched'])->toBe(4);
});
