<?php

declare(strict_types=1);

use Vault\Command\SuggestLinksCommand;

it('finds documents sharing 2+ tags across different domains', function (): void {
    $db = freshDb();
    $id1 = seedDocument($db, ['id' => 'tech-ai', 'title' => 'AI Research', 'domain' => 'tech', 'subdomain' => 'research']);
    $id2 = seedDocument($db, ['id' => 'biz-ai', 'title' => 'AI Business Plan', 'domain' => 'business', 'subdomain' => 'strategy']);
    $db->execute('INSERT INTO tags VALUES (:id, :tag)', [':id' => $id1, ':tag' => 'ai']);
    $db->execute('INSERT INTO tags VALUES (:id, :tag)', [':id' => $id1, ':tag' => 'automation']);
    $db->execute('INSERT INTO tags VALUES (:id, :tag)', [':id' => $id2, ':tag' => 'ai']);
    $db->execute('INSERT INTO tags VALUES (:id, :tag)', [':id' => $id2, ':tag' => 'automation']);
    $result = runCommand(new SuggestLinksCommand($db));
    expect($result['code'])->toBe(0)->and($result['output'])->toContain('AI Research')->and($result['output'])->toContain('AI Business Plan');
});

it('does not suggest links within the same domain', function (): void {
    $db = freshDb();
    $id1 = seedDocument($db, ['id' => 'tech-1', 'title' => 'Tech One', 'domain' => 'tech', 'subdomain' => 'fleeting']);
    $id2 = seedDocument($db, ['id' => 'tech-2', 'title' => 'Tech Two', 'domain' => 'tech', 'subdomain' => 'research']);
    $db->execute('INSERT INTO tags VALUES (:id, :tag)', [':id' => $id1, ':tag' => 'rust']);
    $db->execute('INSERT INTO tags VALUES (:id, :tag)', [':id' => $id1, ':tag' => 'performance']);
    $db->execute('INSERT INTO tags VALUES (:id, :tag)', [':id' => $id2, ':tag' => 'rust']);
    $db->execute('INSERT INTO tags VALUES (:id, :tag)', [':id' => $id2, ':tag' => 'performance']);
    $result = runCommand(new SuggestLinksCommand($db));
    expect($result['output'])->not->toContain('Tech One');
});

it('filters by --id for a specific document', function (): void {
    $db = freshDb();
    $id1 = seedDocument($db, ['id' => 'target-doc', 'title' => 'Target', 'domain' => 'tech']);
    $id2 = seedDocument($db, ['id' => 'match-doc', 'title' => 'Match', 'domain' => 'business']);
    $id3 = seedDocument($db, ['id' => 'nomatch', 'title' => 'NoMatch', 'domain' => 'creative']);
    $db->execute('INSERT INTO tags VALUES (:id, :tag)', [':id' => $id1, ':tag' => 'saas']);
    $db->execute('INSERT INTO tags VALUES (:id, :tag)', [':id' => $id1, ':tag' => 'startup']);
    $db->execute('INSERT INTO tags VALUES (:id, :tag)', [':id' => $id2, ':tag' => 'saas']);
    $db->execute('INSERT INTO tags VALUES (:id, :tag)', [':id' => $id2, ':tag' => 'startup']);
    $db->execute('INSERT INTO tags VALUES (:id, :tag)', [':id' => $id3, ':tag' => 'unrelated']);
    $result = runCommand(new SuggestLinksCommand($db), ['--id' => 'target-doc']);
    expect($result['output'])->toContain('Match')->and($result['output'])->not->toContain('NoMatch');
});

it('excludes already-linked documents', function (): void {
    $db = freshDb();
    $id1 = seedDocument($db, ['id' => 'linked-a', 'title' => 'Already Linked A', 'domain' => 'tech']);
    $id2 = seedDocument($db, ['id' => 'linked-b', 'title' => 'Already Linked B', 'domain' => 'business']);
    $db->execute('INSERT INTO tags VALUES (:id, :tag)', [':id' => $id1, ':tag' => 'shared1']);
    $db->execute('INSERT INTO tags VALUES (:id, :tag)', [':id' => $id1, ':tag' => 'shared2']);
    $db->execute('INSERT INTO tags VALUES (:id, :tag)', [':id' => $id2, ':tag' => 'shared1']);
    $db->execute('INSERT INTO tags VALUES (:id, :tag)', [':id' => $id2, ':tag' => 'shared2']);
    $db->execute('INSERT OR REPLACE INTO links (source_id, target_id, link_type) VALUES (:s, :t, :type)', [':s' => $id1, ':t' => $id2, ':type' => 'related']);
    $result = runCommand(new SuggestLinksCommand($db));
    expect($result['output'])->not->toContain('Already Linked A');
});

it('shows no suggestions when none exist', function (): void {
    $db = freshDb();
    $result = runCommand(new SuggestLinksCommand($db));
    expect($result['code'])->toBe(0)->and($result['output'])->toContain('No suggestions');
});
