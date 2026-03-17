<?php

declare(strict_types=1);

use Vault\Command\Db\UpsertMetaCommand;

it('updates meta JSON on a document', function (): void {
    $db = freshDb();
    $id = seedDocument($db, ['id' => 'meta-test']);

    $result = runCommand(new UpsertMetaCommand($db), [
        '--id' => $id,
        '--json' => '{"author":"Test Author","rating":"S"}',
    ]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain("ok: updated meta");

    $row = $db->fetchOne('SELECT meta, rating FROM documents WHERE id = :id', [':id' => $id]);
    $meta = json_decode($row['meta'], true);

    expect($meta['author'])->toBe('Test Author')
        ->and($row['rating'])->toBe('S');
});

it('merges with existing meta via json_patch', function (): void {
    $db = freshDb();
    $id = seedDocument($db, ['id' => 'merge-test', 'meta' => '{"author":"Old","genre":"sci-fi"}']);

    $result = runCommand(new UpsertMetaCommand($db), [
        '--id' => $id,
        '--json' => '{"author":"New","rating":"A"}',
    ]);

    expect($result['code'])->toBe(0);

    $row = $db->fetchOne('SELECT meta FROM documents WHERE id = :id', [':id' => $id]);
    $meta = json_decode($row['meta'], true);

    expect($meta['author'])->toBe('New')
        ->and($meta['genre'])->toBe('sci-fi')
        ->and($meta['rating'])->toBe('A');
});
