<?php

declare(strict_types=1);

use Vault\Command\Db\AddExternalRefCommand;

it('adds an external reference to a document', function (): void {
    $db = freshDb();
    $id = seedDocument($db);

    $result = runCommand(new AddExternalRefCommand($db), [
        '--id' => $id,
        '--url' => 'https://github.com/example/repo',
        '--label' => 'Repository',
    ]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('ok:');

    $ref = $db->fetchOne('SELECT url, label FROM external_refs WHERE doc_id = :id', [':id' => $id]);

    expect($ref['url'])->toBe('https://github.com/example/repo')
        ->and($ref['label'])->toBe('Repository');
});

it('handles optional label', function (): void {
    $db = freshDb();
    $id = seedDocument($db);

    $result = runCommand(new AddExternalRefCommand($db), [
        '--id' => $id,
        '--url' => 'https://example.com',
    ]);

    expect($result['code'])->toBe(0);

    $ref = $db->fetchOne('SELECT url, label FROM external_refs WHERE doc_id = :id', [':id' => $id]);

    expect($ref['url'])->toBe('https://example.com')
        ->and($ref['label'])->toBeNull();
});
