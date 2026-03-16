<?php

declare(strict_types=1);

use Vault\Command\BriefingCommand;

it('shows the briefing header with today\'s date', function (): void {
    $db = freshDb();
    $result = runCommand(new BriefingCommand($db));

    $today = date('Y-m-d');

    expect($result['output'])->toContain('VAULT BRIEFING')
        ->and($result['output'])->toContain($today);
});

it('shows overdue todos count', function (): void {
    $db = freshDb();
    $docId = seedDocument($db);

    $db->execute(
        'INSERT INTO todos (doc_id, content, due_date, status, priority) VALUES (:doc_id, :content, :due, :status, :priority)',
        [':doc_id' => $docId, ':content' => 'Overdue task one', ':due' => '2026-01-01', ':status' => 'open', ':priority' => 'p1-high'],
    );
    $db->execute(
        'INSERT INTO todos (doc_id, content, due_date, status, priority) VALUES (:doc_id, :content, :due, :status, :priority)',
        [':doc_id' => $docId, ':content' => 'Overdue task two', ':due' => '2026-02-01', ':status' => 'open', ':priority' => 'p2-medium'],
    );

    $result = runCommand(new BriefingCommand($db));

    expect($result['output'])->toContain('OVERDUE (2)')
        ->and($result['output'])->toContain('Overdue task one')
        ->and($result['output'])->toContain('Overdue task two');
});

it('shows due today count', function (): void {
    $db = freshDb();
    $docId = seedDocument($db);
    $today = date('Y-m-d');

    $db->execute(
        'INSERT INTO todos (doc_id, content, due_date, status, priority) VALUES (:doc_id, :content, :due, :status, :priority)',
        [':doc_id' => $docId, ':content' => 'Due today task', ':due' => $today, ':status' => 'open', ':priority' => 'p1-high'],
    );

    $result = runCommand(new BriefingCommand($db));

    expect($result['output'])->toContain('DUE TODAY (1)')
        ->and($result['output'])->toContain('Due today task');
});

it('shows revisit today documents', function (): void {
    $db = freshDb();
    $today = date('Y-m-d');

    seedDocument($db, [
        'id' => 'revisit-doc',
        'title' => 'Revisit Me',
        'status' => 'growing',
        'domain' => 'tech',
        'revisit_date' => $today,
    ]);

    $result = runCommand(new BriefingCommand($db));

    expect($result['output'])->toContain('REVISIT TODAY (1)')
        ->and($result['output'])->toContain('Revisit Me')
        ->and($result['output'])->toContain('status: growing')
        ->and($result['output'])->toContain('domain: tech');
});

it('shows recent activity counts', function (): void {
    $db = freshDb();
    $now = date('Y-m-d\TH:i:s');

    // Recently created
    seedDocument($db, ['id' => 'recent-1', 'created_at' => $now, 'modified_at' => $now]);
    seedDocument($db, ['id' => 'recent-2', 'created_at' => $now, 'modified_at' => $now]);

    // Recently updated (created before cutoff, modified recently)
    seedDocument($db, ['id' => 'updated-1', 'created_at' => '2025-01-01T00:00:00', 'modified_at' => $now]);

    // Recently closed
    seedDocument($db, ['id' => 'closed-1', 'status' => 'closed', 'created_at' => $now, 'modified_at' => $now]);

    $result = runCommand(new BriefingCommand($db));

    expect($result['output'])->toContain('RECENT ACTIVITY (last 48h)')
        ->and($result['output'])->toContain('ideas created')
        ->and($result['output'])->toContain('ideas updated')
        ->and($result['output'])->toContain('ideas closed/migrated/built');
});

it('shows vault stats with total, active, seeds, dormant, and closed', function (): void {
    $db = freshDb();

    seedDocument($db, ['id' => 'seed-1', 'status' => 'seed']);
    seedDocument($db, ['id' => 'seed-2', 'status' => 'seed']);
    seedDocument($db, ['id' => 'growing-1', 'status' => 'growing']);
    seedDocument($db, ['id' => 'dormant-1', 'status' => 'dormant']);
    seedDocument($db, ['id' => 'closed-1', 'status' => 'closed']);

    $result = runCommand(new BriefingCommand($db));

    expect($result['output'])->toContain('VAULT STATS')
        ->and($result['output'])->toContain('Total: 5')
        ->and($result['output'])->toContain('Active: 3')
        ->and($result['output'])->toContain('Seeds: 2')
        ->and($result['output'])->toContain('Dormant: 1')
        ->and($result['output'])->toContain('Closed: 1');
});

it('shows orphan count', function (): void {
    $db = freshDb();

    // Old doc with no links = orphan
    seedDocument($db, [
        'id' => 'orphan-1',
        'status' => 'seed',
        'created_at' => '2025-01-01T00:00:00',
        'modified_at' => '2025-01-01T00:00:00',
    ]);

    $result = runCommand(new BriefingCommand($db));

    expect($result['output'])->toContain('Orphaned ideas (>7 days, no links): 1');
});

it('returns SUCCESS exit code', function (): void {
    $db = freshDb();
    $result = runCommand(new BriefingCommand($db));

    expect($result['code'])->toBe(0);
});
