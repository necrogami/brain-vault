<?php

declare(strict_types=1);

use Vault\Command\Db\AddTodoCommand;

it('adds a todo with content', function (): void {
    $db = freshDb();

    $result = runCommand(new AddTodoCommand($db), [
        '--content' => 'Research vector databases',
        '--due' => '2026-04-01',
        '--priority' => 'p2-medium',
    ]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('ok: added todo');

    $row = $db->fetchOne('SELECT * FROM todos WHERE content = :content', [':content' => 'Research vector databases']);

    expect($row)->not->toBeNull()
        ->and($row['content'])->toBe('Research vector databases')
        ->and($row['due_date'])->toBe('2026-04-01')
        ->and($row['priority'])->toBe('p2-medium')
        ->and($row['status'])->toBe('open')
        ->and($row['doc_id'])->toBeNull();
});

it('adds a standalone todo without doc-id', function (): void {
    $db = freshDb();

    $result = runCommand(new AddTodoCommand($db), [
        '--content' => 'Buy groceries',
    ]);

    expect($result['code'])->toBe(0);

    $row = $db->fetchOne('SELECT * FROM todos WHERE content = :content', [':content' => 'Buy groceries']);

    expect($row)->not->toBeNull()
        ->and($row['doc_id'])->toBeNull()
        ->and($row['priority'])->toBe('p3-low')
        ->and($row['status'])->toBe('open');
});

it('adds a todo attached to a document', function (): void {
    $db = freshDb();
    $docId = seedDocument($db, ['id' => 'todo-parent-doc']);

    $result = runCommand(new AddTodoCommand($db), [
        '--content' => 'Finish research section',
        '--doc-id' => $docId,
        '--due' => '2026-03-20',
        '--priority' => 'p1-high',
    ]);

    expect($result['code'])->toBe(0);

    $row = $db->fetchOne('SELECT * FROM todos WHERE doc_id = :id', [':id' => $docId]);

    expect($row)->not->toBeNull()
        ->and($row['content'])->toBe('Finish research section')
        ->and($row['doc_id'])->toBe($docId)
        ->and($row['due_date'])->toBe('2026-03-20')
        ->and($row['priority'])->toBe('p1-high');
});
