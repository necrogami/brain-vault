<?php

declare(strict_types=1);

use Vault\Command\Db\UpsertDocCommand;

it('inserts a new document with required fields', function (): void {
    $db = freshDb();

    $result = runCommand(new UpsertDocCommand($db), [
        '--id' => 'test-insert-doc',
        '--title' => 'Test Document',
        '--domain' => 'tech',
        '--subdomain' => 'fleeting',
        '--file-path' => 'vault/tech/fleeting/2026/03/test.md',
        '--created' => '2026-03-15T10:00:00',
        '--modified' => '2026-03-15T10:00:00',
    ]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain("ok: upserted document 'test-insert-doc'");

    $row = $db->fetchOne('SELECT * FROM documents WHERE id = :id', [':id' => 'test-insert-doc']);

    expect($row)->not->toBeNull()
        ->and($row['title'])->toBe('Test Document')
        ->and($row['domain'])->toBe('tech')
        ->and($row['subdomain'])->toBe('fleeting')
        ->and($row['file_path'])->toBe('vault/tech/fleeting/2026/03/test.md')
        ->and($row['created_at'])->toBe('2026-03-15T10:00:00')
        ->and($row['modified_at'])->toBe('2026-03-15T10:00:00');
});

it('updates an existing document via upsert', function (): void {
    $db = freshDb();
    $id = seedDocument($db, ['id' => 'upsert-test', 'title' => 'Original Title']);

    $result = runCommand(new UpsertDocCommand($db), [
        '--id' => $id,
        '--title' => 'Updated Title',
        '--domain' => 'tech',
        '--subdomain' => 'fleeting',
        '--file-path' => 'vault/tech/fleeting/2026/03/updated.md',
        '--created' => '2026-03-15T10:00:00',
        '--modified' => '2026-03-15T11:00:00',
        '--summary' => 'Updated summary',
    ]);

    expect($result['code'])->toBe(0);

    $row = $db->fetchOne('SELECT * FROM documents WHERE id = :id', [':id' => $id]);

    expect($row['title'])->toBe('Updated Title')
        ->and($row['summary'])->toBe('Updated summary')
        ->and($row['modified_at'])->toBe('2026-03-15T11:00:00');
});

it('uses default values for optional fields', function (): void {
    $db = freshDb();

    $result = runCommand(new UpsertDocCommand($db), [
        '--id' => 'default-test',
        '--title' => 'Defaults Test',
        '--domain' => 'tech',
        '--subdomain' => 'fleeting',
        '--file-path' => 'vault/tech/fleeting/2026/03/defaults.md',
        '--created' => '2026-03-15T10:00:00',
        '--modified' => '2026-03-15T10:00:00',
    ]);

    expect($result['code'])->toBe(0);

    $row = $db->fetchOne('SELECT * FROM documents WHERE id = :id', [':id' => 'default-test']);

    expect($row['status'])->toBe('seed')
        ->and($row['priority'])->toBe('p3-low')
        ->and($row['confidence'])->toBe('speculative')
        ->and($row['effort'])->toBeNull()
        ->and($row['summary'])->toBeNull()
        ->and($row['revisit_date'])->toBeNull();
});
