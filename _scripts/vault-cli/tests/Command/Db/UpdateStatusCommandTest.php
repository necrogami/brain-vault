<?php

declare(strict_types=1);

use Vault\Command\Db\UpdateStatusCommand;

it('updates document status', function (): void {
    $db = freshDb();
    $id = seedDocument($db, ['id' => 'status-test', 'status' => 'seed']);

    $result = runCommand(new UpdateStatusCommand($db), [
        '--id' => $id,
        '--status' => 'growing',
        '--modified' => '2026-03-15T12:00:00',
    ]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain("ok: '{$id}' status → growing");

    $row = $db->fetchOne('SELECT * FROM documents WHERE id = :id', [':id' => $id]);

    expect($row['status'])->toBe('growing');
});

it('updates modified_at timestamp', function (): void {
    $db = freshDb();
    $id = seedDocument($db, ['id' => 'modified-test', 'modified_at' => '2026-03-15T10:00:00']);

    runCommand(new UpdateStatusCommand($db), [
        '--id' => $id,
        '--status' => 'mature',
        '--modified' => '2026-03-15T14:30:00',
    ]);

    $row = $db->fetchOne('SELECT * FROM documents WHERE id = :id', [':id' => $id]);

    expect($row['modified_at'])->toBe('2026-03-15T14:30:00');
});

it('uses current time as default for --modified when not provided', function (): void {
    $db = freshDb();
    $id = seedDocument($db, ['id' => 'default-modified']);

    $beforeRun = gmdate('Y-m-d\TH:i:s');

    runCommand(new UpdateStatusCommand($db), [
        '--id' => $id,
        '--status' => 'closed',
    ]);

    $afterRun = gmdate('Y-m-d\TH:i:s');

    $row = $db->fetchOne('SELECT * FROM documents WHERE id = :id', [':id' => $id]);

    // The modified_at should be between before and after the command run
    expect($row['modified_at'])->toBeGreaterThanOrEqual($beforeRun)
        ->and($row['modified_at'])->toBeLessThanOrEqual($afterRun);
});
