<?php

declare(strict_types=1);

use Vault\Command\Db\AddReadCommand;

it('logs a read date for a book', function (): void {
    $db = freshDb();
    $id = seedBook($db, ['id' => 'book-read-test', 'title' => 'Read Test']);

    $result = runCommand(new AddReadCommand($db), [
        '--id' => $id,
        '--date' => '2026-03-15',
    ]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain("ok: logged read for '{$id}' on 2026-03-15");

    $row = $db->fetchOne('SELECT * FROM reads WHERE doc_id = :id', [':id' => $id]);

    expect($row)->not->toBeNull()
        ->and($row['date_read'])->toBe('2026-03-15');
});

it('allows multiple reads for the same book', function (): void {
    $db = freshDb();
    $id = seedBook($db, ['id' => 'book-multi-read', 'title' => 'Multi Read Test']);

    runCommand(new AddReadCommand($db), [
        '--id' => $id,
        '--date' => '2025-01-10',
    ]);

    runCommand(new AddReadCommand($db), [
        '--id' => $id,
        '--date' => '2026-03-15',
    ]);

    $reads = $db->fetchAll('SELECT * FROM reads WHERE doc_id = :id ORDER BY date_read', [':id' => $id]);

    expect($reads)->toHaveCount(2)
        ->and($reads[0]['date_read'])->toBe('2025-01-10')
        ->and($reads[1]['date_read'])->toBe('2026-03-15');
});
