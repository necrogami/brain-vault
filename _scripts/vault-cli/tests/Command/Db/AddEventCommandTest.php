<?php

declare(strict_types=1);

use Vault\Command\Db\AddEventCommand;

it('adds a media event', function (): void {
    $db = freshDb();
    $id = seedDocument($db, ['id' => 'event-test']);

    $result = runCommand(new AddEventCommand($db), [
        '--id' => $id,
        '--type' => 'read',
        '--date' => '2026-03-17',
    ]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain("ok: logged read");

    $row = $db->fetchOne(
        'SELECT * FROM media_events WHERE doc_id = :id',
        [':id' => $id],
    );
    expect($row['event_type'])->toBe('read')
        ->and($row['event_date'])->toBe('2026-03-17');
});

it('accepts optional json metadata for events', function (): void {
    $db = freshDb();
    $id = seedDocument($db, ['id' => 'event-meta-test']);

    $result = runCommand(new AddEventCommand($db), [
        '--id' => $id,
        '--type' => 'play_session',
        '--date' => '2026-03-17',
        '--json' => '{"hours":3.5}',
    ]);

    expect($result['code'])->toBe(0);

    $row = $db->fetchOne(
        'SELECT * FROM media_events WHERE doc_id = :id',
        [':id' => $id],
    );
    $meta = json_decode($row['meta'], true);
    expect($meta['hours'])->toBe(3.5);
});

it('allows multiple events for the same document', function (): void {
    $db = freshDb();
    $id = seedDocument($db, ['id' => 'multi-event-test']);

    runCommand(new AddEventCommand($db), [
        '--id' => $id,
        '--type' => 'read',
        '--date' => '2026-01-15',
    ]);

    runCommand(new AddEventCommand($db), [
        '--id' => $id,
        '--type' => 'read',
        '--date' => '2026-03-17',
    ]);

    $rows = $db->fetchAll(
        'SELECT * FROM media_events WHERE doc_id = :id ORDER BY event_date',
        [':id' => $id],
    );
    expect($rows)->toHaveCount(2);
});
