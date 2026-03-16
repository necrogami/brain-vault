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
