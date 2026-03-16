<?php

declare(strict_types=1);

use Vault\Command\Db\AddSourceCommand;

it('adds a source to a document', function (): void {
    $db = freshDb();
    $id = seedDocument($db, ['id' => 'source-test']);

    $result = runCommand(new AddSourceCommand($db), [
        '--id' => $id,
        '--url' => 'https://example.com/article',
        '--title' => 'Example Article',
        '--accessed' => '2026-03-15',
    ]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain("ok: added source to '{$id}'");

    $row = $db->fetchOne('SELECT * FROM sources WHERE doc_id = :id', [':id' => $id]);

    expect($row)->not->toBeNull()
        ->and($row['url'])->toBe('https://example.com/article')
        ->and($row['title'])->toBe('Example Article')
        ->and($row['accessed_date'])->toBe('2026-03-15');
});

it('handles optional fields', function (): void {
    $db = freshDb();
    $id = seedDocument($db, ['id' => 'source-optional']);

    $result = runCommand(new AddSourceCommand($db), [
        '--id' => $id,
        '--url' => 'https://example.com',
    ]);

    expect($result['code'])->toBe(0);

    $row = $db->fetchOne('SELECT * FROM sources WHERE doc_id = :id', [':id' => $id]);

    expect($row)->not->toBeNull()
        ->and($row['url'])->toBe('https://example.com')
        ->and($row['title'])->toBeNull()
        ->and($row['accessed_date'])->toBeNull();
});
