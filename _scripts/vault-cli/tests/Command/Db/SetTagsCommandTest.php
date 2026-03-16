<?php

declare(strict_types=1);

use Vault\Command\Db\SetTagsCommand;

it('sets tags on a document', function (): void {
    $db = freshDb();
    $id = seedDocument($db);

    $result = runCommand(new SetTagsCommand($db), [
        '--id' => $id,
        '--tags' => 'rust,systems,performance',
    ]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain("ok: set 3 tags on '{$id}'");

    $tags = $db->fetchAll('SELECT tag FROM tags WHERE doc_id = :id ORDER BY tag', [':id' => $id]);

    expect($tags)->toHaveCount(3)
        ->and($tags[0]['tag'])->toBe('performance')
        ->and($tags[1]['tag'])->toBe('rust')
        ->and($tags[2]['tag'])->toBe('systems');
});

it('replaces existing tags', function (): void {
    $db = freshDb();
    $id = seedDocument($db);

    // Set initial tags
    runCommand(new SetTagsCommand($db), [
        '--id' => $id,
        '--tags' => 'old-tag-1,old-tag-2',
    ]);

    // Replace with new tags
    $result = runCommand(new SetTagsCommand($db), [
        '--id' => $id,
        '--tags' => 'new-tag-1,new-tag-2,new-tag-3',
    ]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain("ok: set 3 tags on '{$id}'");

    $tags = $db->fetchAll('SELECT tag FROM tags WHERE doc_id = :id ORDER BY tag', [':id' => $id]);

    expect($tags)->toHaveCount(3)
        ->and($tags[0]['tag'])->toBe('new-tag-1')
        ->and($tags[1]['tag'])->toBe('new-tag-2')
        ->and($tags[2]['tag'])->toBe('new-tag-3');
});
