<?php

declare(strict_types=1);

use Vault\Command\Db\AddLinkCommand;

it('adds a link between two documents', function (): void {
    $db = freshDb();
    $sourceId = seedDocument($db, ['id' => 'doc-source']);
    $targetId = seedDocument($db, ['id' => 'doc-target', 'file_path' => 'vault/tech/fleeting/2026/03/target.md']);

    $result = runCommand(new AddLinkCommand($db), [
        '--source' => $sourceId,
        '--target' => $targetId,
        '--type' => 'related',
    ]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain("ok: link {$sourceId} --[related]--> {$targetId}");

    $link = $db->fetchOne(
        'SELECT * FROM links WHERE source_id = :source AND target_id = :target',
        [':source' => $sourceId, ':target' => $targetId],
    );

    expect($link)->not->toBeNull()
        ->and($link['link_type'])->toBe('related');
});

it('replaces an existing link via INSERT OR REPLACE', function (): void {
    $db = freshDb();
    $sourceId = seedDocument($db, ['id' => 'doc-src']);
    $targetId = seedDocument($db, ['id' => 'doc-tgt', 'file_path' => 'vault/tech/fleeting/2026/03/tgt.md']);

    // Insert the initial link
    runCommand(new AddLinkCommand($db), [
        '--source' => $sourceId,
        '--target' => $targetId,
        '--type' => 'related',
    ]);

    // Replace with the same source/target/type (should not create a duplicate)
    $result = runCommand(new AddLinkCommand($db), [
        '--source' => $sourceId,
        '--target' => $targetId,
        '--type' => 'related',
    ]);

    expect($result['code'])->toBe(0);

    $count = $db->fetchValue(
        'SELECT COUNT(*) FROM links WHERE source_id = :source AND target_id = :target AND link_type = :type',
        [':source' => $sourceId, ':target' => $targetId, ':type' => 'related'],
    );

    expect((int) $count)->toBe(1);
});
