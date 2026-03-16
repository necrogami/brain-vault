<?php

declare(strict_types=1);

use Vault\Command\Db\AddPlaySessionCommand;

it('logs a play session for a game', function (): void {
    $db = freshDb();
    $id = seedGame($db, ['id' => 'game-session-test', 'title' => 'Session Test']);

    $result = runCommand(new AddPlaySessionCommand($db), [
        '--id' => $id,
        '--date' => '2026-03-15',
        '--hours' => '3.5',
    ]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain("ok: logged play session for '{$id}' on 2026-03-15")
        ->and($result['output'])->toContain('3.5h');

    $row = $db->fetchOne('SELECT * FROM play_sessions WHERE doc_id = :id', [':id' => $id]);

    expect($row)->not->toBeNull()
        ->and($row['date_played'])->toBe('2026-03-15')
        ->and((float) $row['hours'])->toBe(3.5);
});

it('allows multiple play sessions for the same game', function (): void {
    $db = freshDb();
    $id = seedGame($db, ['id' => 'game-multi-session', 'title' => 'Multi Session Test']);

    runCommand(new AddPlaySessionCommand($db), [
        '--id' => $id,
        '--date' => '2026-03-10',
        '--hours' => '2.0',
    ]);

    runCommand(new AddPlaySessionCommand($db), [
        '--id' => $id,
        '--date' => '2026-03-15',
        '--hours' => '4.5',
    ]);

    $sessions = $db->fetchAll('SELECT * FROM play_sessions WHERE doc_id = :id ORDER BY date_played', [':id' => $id]);

    expect($sessions)->toHaveCount(2)
        ->and($sessions[0]['date_played'])->toBe('2026-03-10')
        ->and((float) $sessions[0]['hours'])->toBe(2.0)
        ->and($sessions[1]['date_played'])->toBe('2026-03-15')
        ->and((float) $sessions[1]['hours'])->toBe(4.5);
});

it('logs a session without hours', function (): void {
    $db = freshDb();
    $id = seedGame($db, ['id' => 'game-no-hours', 'title' => 'No Hours Test']);

    $result = runCommand(new AddPlaySessionCommand($db), [
        '--id' => $id,
        '--date' => '2026-03-15',
    ]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain("ok: logged play session for '{$id}' on 2026-03-15")
        ->and($result['output'])->not->toContain('h)');

    $row = $db->fetchOne('SELECT * FROM play_sessions WHERE doc_id = :id', [':id' => $id]);

    expect($row)->not->toBeNull()
        ->and($row['date_played'])->toBe('2026-03-15')
        ->and($row['hours'])->toBeNull();
});
