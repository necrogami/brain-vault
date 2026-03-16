<?php

declare(strict_types=1);

use Vault\Command\StatsCommand;

it('shows status breakdown', function (): void {
    $db = freshDb();

    seedDocument($db, ['id' => 'stat-1', 'status' => 'seed']);
    seedDocument($db, ['id' => 'stat-2', 'status' => 'seed']);
    seedDocument($db, ['id' => 'stat-3', 'status' => 'growing']);
    seedDocument($db, ['id' => 'stat-4', 'status' => 'closed']);

    $result = runCommand(new StatsCommand($db));

    expect($result['output'])->toContain('Status Breakdown')
        ->and($result['output'])->toContain('seed')
        ->and($result['output'])->toContain('growing')
        ->and($result['output'])->toContain('closed');
});

it('shows domain breakdown', function (): void {
    $db = freshDb();

    seedDocument($db, ['id' => 'dom-1', 'domain' => 'tech', 'subdomain' => 'fleeting']);
    seedDocument($db, ['id' => 'dom-2', 'domain' => 'tech', 'subdomain' => 'research']);
    seedDocument($db, ['id' => 'dom-3', 'domain' => 'personal', 'subdomain' => 'journal']);

    $result = runCommand(new StatsCommand($db));

    expect($result['output'])->toContain('Domain / Subdomain Breakdown')
        ->and($result['output'])->toContain('tech')
        ->and($result['output'])->toContain('fleeting')
        ->and($result['output'])->toContain('research')
        ->and($result['output'])->toContain('personal')
        ->and($result['output'])->toContain('journal');
});

it('shows priority breakdown', function (): void {
    $db = freshDb();

    seedDocument($db, ['id' => 'pri-1', 'priority' => 'p1-high']);
    seedDocument($db, ['id' => 'pri-2', 'priority' => 'p1-high']);
    seedDocument($db, ['id' => 'pri-3', 'priority' => 'p3-low']);

    $result = runCommand(new StatsCommand($db));

    expect($result['output'])->toContain('Priority Breakdown')
        ->and($result['output'])->toContain('p1-high')
        ->and($result['output'])->toContain('p3-low');
});

it('shows tag counts', function (): void {
    $db = freshDb();

    $id1 = seedDocument($db, ['id' => 'tag-1']);
    $id2 = seedDocument($db, ['id' => 'tag-2']);

    $db->execute('INSERT INTO tags (doc_id, tag) VALUES (:doc_id, :tag)', [':doc_id' => $id1, ':tag' => 'python']);
    $db->execute('INSERT INTO tags (doc_id, tag) VALUES (:doc_id, :tag)', [':doc_id' => $id2, ':tag' => 'python']);
    $db->execute('INSERT INTO tags (doc_id, tag) VALUES (:doc_id, :tag)', [':doc_id' => $id1, ':tag' => 'rust']);

    $result = runCommand(new StatsCommand($db));

    expect($result['output'])->toContain('Top 20 Tags')
        ->and($result['output'])->toContain('python')
        ->and($result['output'])->toContain('rust');
});

it('shows no tags message when none exist', function (): void {
    $db = freshDb();

    seedDocument($db, ['id' => 'no-tags']);

    $result = runCommand(new StatsCommand($db));

    expect($result['output'])->toContain('No tags found.');
});

it('returns SUCCESS exit code', function (): void {
    $db = freshDb();

    seedDocument($db);

    $result = runCommand(new StatsCommand($db));

    expect($result['code'])->toBe(0);
});
