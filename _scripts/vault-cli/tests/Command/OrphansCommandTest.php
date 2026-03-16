<?php

declare(strict_types=1);

use Vault\Command\OrphansCommand;

it('shows documents with no links', function (): void {
    $db = freshDb();

    seedDocument($db, [
        'id' => 'orphan-doc',
        'title' => 'Lonely Idea',
        'status' => 'seed',
    ]);

    $result = runCommand(new OrphansCommand($db));

    expect($result['output'])->toContain('Lonely Idea')
        ->and($result['output'])->toContain('orphan-doc')
        ->and($result['output'])->toContain('1 orphaned document(s) found.');
});

it('excludes closed, migrated, and built documents', function (): void {
    $db = freshDb();

    seedDocument($db, ['id' => 'closed-doc', 'title' => 'Closed Idea', 'status' => 'closed']);
    seedDocument($db, ['id' => 'migrated-doc', 'title' => 'Migrated Idea', 'status' => 'migrated']);
    seedDocument($db, ['id' => 'built-doc', 'title' => 'Built Idea', 'status' => 'built']);

    $result = runCommand(new OrphansCommand($db));

    expect($result['output'])->toContain('No orphaned documents found.')
        ->and($result['output'])->not->toContain('Closed Idea')
        ->and($result['output'])->not->toContain('Migrated Idea')
        ->and($result['output'])->not->toContain('Built Idea');
});

it('excludes documents that have links', function (): void {
    $db = freshDb();

    $id1 = seedDocument($db, ['id' => 'linked-a', 'title' => 'Linked A', 'status' => 'seed']);
    $id2 = seedDocument($db, ['id' => 'linked-b', 'title' => 'Linked B', 'status' => 'seed']);

    $db->execute(
        'INSERT INTO links (source_id, target_id, link_type) VALUES (:source, :target, :type)',
        [':source' => $id1, ':target' => $id2, ':type' => 'related'],
    );

    $result = runCommand(new OrphansCommand($db));

    expect($result['output'])->toContain('No orphaned documents found.')
        ->and($result['output'])->not->toContain('Linked A')
        ->and($result['output'])->not->toContain('Linked B');
});

it('shows orphaned document details in table', function (): void {
    $db = freshDb();

    seedDocument($db, [
        'id' => 'detail-orphan',
        'title' => 'Detailed Orphan',
        'status' => 'growing',
        'file_path' => 'vault/tech/fleeting/2026/03/detail.md',
        'created_at' => '2026-03-10T08:00:00',
    ]);

    $result = runCommand(new OrphansCommand($db));

    expect($result['output'])->toContain('detail-orphan')
        ->and($result['output'])->toContain('Detailed Orphan')
        ->and($result['output'])->toContain('vault/tech/fleeting/2026/03/detail.md')
        ->and($result['output'])->toContain('growing');
});
