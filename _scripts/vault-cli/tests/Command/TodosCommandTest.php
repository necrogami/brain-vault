<?php

declare(strict_types=1);

use Vault\Command\TodosCommand;

it('lists open todos by default', function (): void {
    $db = freshDb();
    $docId = seedDocument($db);

    $db->execute(
        'INSERT INTO todos (doc_id, content, due_date, status, priority) VALUES (:doc_id, :content, :due, :status, :priority)',
        [':doc_id' => $docId, ':content' => 'Open task', ':due' => '2026-04-01', ':status' => 'open', ':priority' => 'p1-high'],
    );
    $db->execute(
        'INSERT INTO todos (doc_id, content, due_date, status, priority) VALUES (:doc_id, :content, :due, :status, :priority)',
        [':doc_id' => $docId, ':content' => 'Done task', ':due' => '2026-03-01', ':status' => 'done', ':priority' => 'p2-medium'],
    );

    $result = runCommand(new TodosCommand($db));

    expect($result['output'])->toContain('Open task')
        ->and($result['output'])->not->toContain('Done task')
        ->and($result['output'])->toContain('1 TODO(s) listed.');
});

it('includes done todos with --all flag', function (): void {
    $db = freshDb();
    $docId = seedDocument($db);

    $db->execute(
        'INSERT INTO todos (doc_id, content, due_date, status, priority) VALUES (:doc_id, :content, :due, :status, :priority)',
        [':doc_id' => $docId, ':content' => 'Open task', ':due' => '2026-04-01', ':status' => 'open', ':priority' => 'p1-high'],
    );
    $db->execute(
        'INSERT INTO todos (doc_id, content, due_date, status, priority) VALUES (:doc_id, :content, :due, :status, :priority)',
        [':doc_id' => $docId, ':content' => 'Done task', ':due' => '2026-03-01', ':status' => 'done', ':priority' => 'p2-medium'],
    );

    $result = runCommand(new TodosCommand($db), ['--all' => true]);

    expect($result['output'])->toContain('Open task')
        ->and($result['output'])->toContain('Done task')
        ->and($result['output'])->toContain('2 TODO(s) listed.');
});

it('shows priority, content, due date, and context', function (): void {
    $db = freshDb();
    $docId = seedDocument($db, ['id' => 'context-doc', 'title' => 'My Research Project']);

    $db->execute(
        'INSERT INTO todos (doc_id, content, due_date, status, priority) VALUES (:doc_id, :content, :due, :status, :priority)',
        [':doc_id' => $docId, ':content' => 'Review findings', ':due' => '2026-04-15', ':status' => 'open', ':priority' => 'p1-high'],
    );

    $result = runCommand(new TodosCommand($db));

    expect($result['output'])->toContain('p1-high')
        ->and($result['output'])->toContain('Review findings')
        ->and($result['output'])->toContain('2026-04-15')
        ->and($result['output'])->toContain('My Research Project');
});

it('shows standalone for todos without doc_id', function (): void {
    $db = freshDb();

    $db->execute(
        'INSERT INTO todos (doc_id, content, due_date, status, priority) VALUES (:doc_id, :content, :due, :status, :priority)',
        [':doc_id' => null, ':content' => 'Standalone task', ':due' => '2026-05-01', ':status' => 'open', ':priority' => 'p3-low'],
    );

    $result = runCommand(new TodosCommand($db));

    expect($result['output'])->toContain('Standalone task')
        ->and($result['output'])->toContain('(standalone)');
});

it('shows no todos message when none exist', function (): void {
    $db = freshDb();

    $result = runCommand(new TodosCommand($db));

    expect($result['output'])->toContain('No TODOs found.');
});
