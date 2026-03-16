<?php

declare(strict_types=1);

use Vault\Command\Db\AddWatchCommand;

it('logs a watch date for a movie', function (): void {
    $db = freshDb();
    $id = seedMovie($db, ['id' => 'movie-watch-test', 'title' => 'Watch Test']);

    $result = runCommand(new AddWatchCommand($db), [
        '--id' => $id,
        '--date' => '2026-03-15',
    ]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain("ok: logged watch for '{$id}' on 2026-03-15");

    $row = $db->fetchOne('SELECT * FROM watches WHERE doc_id = :id', [':id' => $id]);

    expect($row)->not->toBeNull()
        ->and($row['date_watched'])->toBe('2026-03-15');
});

it('allows multiple watches for the same movie', function (): void {
    $db = freshDb();
    $id = seedMovie($db, ['id' => 'movie-multi-watch', 'title' => 'Multi Watch Test']);

    runCommand(new AddWatchCommand($db), [
        '--id' => $id,
        '--date' => '2025-01-10',
    ]);

    runCommand(new AddWatchCommand($db), [
        '--id' => $id,
        '--date' => '2026-03-15',
    ]);

    $watches = $db->fetchAll('SELECT * FROM watches WHERE doc_id = :id ORDER BY date_watched', [':id' => $id]);

    expect($watches)->toHaveCount(2)
        ->and($watches[0]['date_watched'])->toBe('2025-01-10')
        ->and($watches[1]['date_watched'])->toBe('2026-03-15');
});
