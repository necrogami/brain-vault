<?php

declare(strict_types=1);

use Vault\Command\Db\UpsertGameCommand;

it('inserts a game record', function (): void {
    $db = freshDb();
    $id = seedDocument($db, [
        'id' => 'game-test',
        'domain' => 'personal',
        'subdomain' => 'games',
        'title' => 'Test Game',
    ]);

    $result = runCommand(new UpsertGameCommand($db), [
        '--id' => $id,
        '--developer' => 'Test Studio',
        '--platform' => 'PC',
        '--rating' => 'A',
    ]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain("ok: upserted game '{$id}'");

    $row = $db->fetchOne('SELECT * FROM games WHERE doc_id = :id', [':id' => $id]);

    expect($row)->not->toBeNull()
        ->and($row['developer'])->toBe('Test Studio')
        ->and($row['platform'])->toBe('PC')
        ->and($row['rating'])->toBe('A');
});

it('handles all optional fields', function (): void {
    $db = freshDb();
    $id = seedDocument($db, [
        'id' => 'game-full',
        'domain' => 'personal',
        'subdomain' => 'games',
        'title' => 'Full Game',
    ]);

    $result = runCommand(new UpsertGameCommand($db), [
        '--id' => $id,
        '--developer' => 'FromSoftware',
        '--publisher' => 'Bandai Namco',
        '--year' => '2022',
        '--genre' => 'Action RPG',
        '--platform' => 'PC',
        '--hours-played' => '120.5',
        '--rating' => 'S',
        '--cover-url' => 'https://example.com/cover.jpg',
    ]);

    expect($result['code'])->toBe(0);

    $row = $db->fetchOne('SELECT * FROM games WHERE doc_id = :id', [':id' => $id]);

    expect($row['developer'])->toBe('FromSoftware')
        ->and($row['publisher'])->toBe('Bandai Namco')
        ->and((int) $row['year'])->toBe(2022)
        ->and($row['genre'])->toBe('Action RPG')
        ->and($row['platform'])->toBe('PC')
        ->and((float) $row['hours_played'])->toBe(120.5)
        ->and($row['rating'])->toBe('S')
        ->and($row['cover_url'])->toBe('https://example.com/cover.jpg');
});

it('replaces an existing game record on upsert', function (): void {
    $db = freshDb();
    $id = seedGame($db, ['id' => 'game-upsert', 'title' => 'Upsert Test'], ['developer' => 'Old Studio', 'rating' => 'B']);

    $result = runCommand(new UpsertGameCommand($db), [
        '--id' => $id,
        '--developer' => 'New Studio',
        '--rating' => 'A',
        '--platform' => 'PS5',
    ]);

    expect($result['code'])->toBe(0);

    $row = $db->fetchOne('SELECT * FROM games WHERE doc_id = :id', [':id' => $id]);

    expect($row['developer'])->toBe('New Studio')
        ->and($row['rating'])->toBe('A')
        ->and($row['platform'])->toBe('PS5');
});
