<?php

declare(strict_types=1);

use Vault\Database;

it('creates a PDO connection', function (): void {
    $db = freshDb();

    expect($db->getPdo())->toBeInstanceOf(PDO::class);
});

it('fetchAll returns array of rows', function (): void {
    $db = freshDb();
    seedDocument($db, ['id' => 'doc-1', 'title' => 'First']);
    seedDocument($db, ['id' => 'doc-2', 'title' => 'Second']);

    $rows = $db->fetchAll('SELECT id, title FROM documents ORDER BY id ASC');

    expect($rows)->toBeArray()
        ->toHaveCount(2)
        ->and($rows[0]['id'])->toBe('doc-1')
        ->and($rows[1]['id'])->toBe('doc-2');
});

it('fetchAll returns empty array when no rows match', function (): void {
    $db = freshDb();

    $rows = $db->fetchAll('SELECT * FROM documents WHERE id = :id', [':id' => 'nonexistent']);

    expect($rows)->toBeArray()->toBeEmpty();
});

it('fetchOne returns single row', function (): void {
    $db = freshDb();
    seedDocument($db, ['id' => 'doc-1', 'title' => 'The Document']);

    $row = $db->fetchOne('SELECT id, title FROM documents WHERE id = :id', [':id' => 'doc-1']);

    expect($row)->toBeArray()
        ->and($row['id'])->toBe('doc-1')
        ->and($row['title'])->toBe('The Document');
});

it('fetchOne returns null when no row matches', function (): void {
    $db = freshDb();

    $row = $db->fetchOne('SELECT * FROM documents WHERE id = :id', [':id' => 'nonexistent']);

    expect($row)->toBeNull();
});

it('fetchValue returns scalar value', function (): void {
    $db = freshDb();
    seedDocument($db, ['id' => 'doc-1']);
    seedDocument($db, ['id' => 'doc-2']);

    $count = $db->fetchValue('SELECT COUNT(*) FROM documents');

    expect($count)->toBe(2);
});

it('execute returns row count', function (): void {
    $db = freshDb();
    seedDocument($db, ['id' => 'doc-1', 'status' => 'seed']);
    seedDocument($db, ['id' => 'doc-2', 'status' => 'seed']);
    seedDocument($db, ['id' => 'doc-3', 'status' => 'growing']);

    $affected = $db->execute(
        'UPDATE documents SET status = :new WHERE status = :old',
        [':new' => 'growing', ':old' => 'seed'],
    );

    expect($affected)->toBe(2);
});

it('executeRaw executes DDL statements', function (): void {
    $db = freshDb();

    $db->executeRaw('CREATE TABLE test_raw (id INTEGER PRIMARY KEY, name TEXT)');
    $db->execute('INSERT INTO test_raw (id, name) VALUES (:id, :name)', [':id' => 1, ':name' => 'hello']);

    $row = $db->fetchOne('SELECT name FROM test_raw WHERE id = 1');

    expect($row['name'])->toBe('hello');
});

it('commits a transaction', function (): void {
    $db = freshDb();

    $db->beginTransaction();
    seedDocument($db, ['id' => 'tx-doc']);
    $db->commit();

    $row = $db->fetchOne('SELECT id FROM documents WHERE id = :id', [':id' => 'tx-doc']);

    expect($row)->not->toBeNull()
        ->and($row['id'])->toBe('tx-doc');
});

it('rolls back a transaction', function (): void {
    $db = freshDb();

    $db->beginTransaction();
    seedDocument($db, ['id' => 'rollback-doc']);
    $db->rollback();

    $row = $db->fetchOne('SELECT id FROM documents WHERE id = :id', [':id' => 'rollback-doc']);

    expect($row)->toBeNull();
});

it('constructor throws on missing file', function (): void {
    new Database('/tmp/nonexistent_vault_db_' . bin2hex(random_bytes(8)) . '.db');
})->throws(RuntimeException::class, 'Database not found');
