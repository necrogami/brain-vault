<?php

declare(strict_types=1);

use Vault\Command\Db\CloseDocCommand;

it('closes a document with status and reason', function (): void {
    $db = freshDb();
    $id = seedDocument($db, ['status' => 'growing']);

    $result = runCommand(new CloseDocCommand($db), [
        '--id' => $id,
        '--status' => 'closed',
        '--reason' => 'No longer relevant',
    ]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('closed');

    $row = $db->fetchOne('SELECT status, close_reason FROM documents WHERE id = :id', [':id' => $id]);

    expect($row['status'])->toBe('closed')
        ->and($row['close_reason'])->toBe('No longer relevant');
});

it('rejects invalid close statuses', function (): void {
    $db = freshDb();
    $id = seedDocument($db);

    $result = runCommand(new CloseDocCommand($db), [
        '--id' => $id,
        '--status' => 'growing',
        '--reason' => 'test',
    ]);

    expect($result['code'])->not->toBe(0);
});

it('cancels open todos when closing a document', function (): void {
    $db = freshDb();
    $id = seedDocument($db, ['status' => 'growing']);

    $db->execute(
        'INSERT INTO todos (doc_id, content, due_date, status, priority) VALUES (:id, :c1, :due, :s1, :p)',
        [':id' => $id, ':c1' => 'Open task', ':due' => '2026-03-20', ':s1' => 'open', ':p' => 'p2-medium'],
    );
    $db->execute(
        'INSERT INTO todos (doc_id, content, due_date, status, priority) VALUES (:id, :c1, :due, :s1, :p)',
        [':id' => $id, ':c1' => 'Done task', ':due' => '2026-03-18', ':s1' => 'done', ':p' => 'p3-low'],
    );

    $result = runCommand(new CloseDocCommand($db), [
        '--id' => $id,
        '--status' => 'closed',
        '--reason' => 'No longer needed',
    ]);

    expect($result['code'])->toBe(0);

    $todos = $db->fetchAll('SELECT content, status FROM todos WHERE doc_id = :id ORDER BY content', [':id' => $id]);

    expect($todos)->toHaveCount(2)
        ->and($todos[0]['content'])->toBe('Done task')
        ->and($todos[0]['status'])->toBe('done')
        ->and($todos[1]['content'])->toBe('Open task')
        ->and($todos[1]['status'])->toBe('cancelled');
});

it('supports built and migrated statuses', function (): void {
    $db = freshDb();
    $id = seedDocument($db);

    $result = runCommand(new CloseDocCommand($db), [
        '--id' => $id,
        '--status' => 'built',
        '--reason' => 'Successfully shipped',
    ]);

    expect($result['code'])->toBe(0);

    $row = $db->fetchOne('SELECT status, close_reason FROM documents WHERE id = :id', [':id' => $id]);

    expect($row['status'])->toBe('built')
        ->and($row['close_reason'])->toBe('Successfully shipped');
});
