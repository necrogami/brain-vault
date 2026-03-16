<?php

declare(strict_types=1);

use Vault\Command\RecentCommand;

it('shows recently created documents', function (): void {
    $db = freshDb();
    $now = date('Y-m-d\TH:i:s');

    seedDocument($db, [
        'id' => 'new-doc',
        'title' => 'Brand New Idea',
        'created_at' => $now,
        'modified_at' => $now,
    ]);

    $result = runCommand(new RecentCommand($db));

    expect($result['output'])->toContain('Created (last 2 days)')
        ->and($result['output'])->toContain('Brand New Idea')
        ->and($result['output'])->toContain('1 document(s) created.');
});

it('shows recently updated documents', function (): void {
    $db = freshDb();
    $now = date('Y-m-d\TH:i:s');

    // Created long ago, modified recently
    seedDocument($db, [
        'id' => 'updated-doc',
        'title' => 'Updated Idea',
        'created_at' => '2025-01-01T00:00:00',
        'modified_at' => $now,
    ]);

    $result = runCommand(new RecentCommand($db));

    expect($result['output'])->toContain('Updated (last 2 days)')
        ->and($result['output'])->toContain('Updated Idea')
        ->and($result['output'])->toContain('1 document(s) updated.');
});

it('respects days argument', function (): void {
    $db = freshDb();

    // Created 5 days ago
    $fiveDaysAgo = date('Y-m-d\TH:i:s', strtotime('-5 days'));
    seedDocument($db, [
        'id' => 'older-doc',
        'title' => 'Older Idea',
        'created_at' => $fiveDaysAgo,
        'modified_at' => $fiveDaysAgo,
    ]);

    // Not found with default 2 days
    $result2 = runCommand(new RecentCommand($db), ['days' => '2']);
    expect($result2['output'])->toContain('No documents created in this period.');

    // Found with 7 days
    $result7 = runCommand(new RecentCommand($db), ['days' => '7']);
    expect($result7['output'])->toContain('Older Idea')
        ->and($result7['output'])->toContain('Created (last 7 days)');
});

it('defaults to 2 days', function (): void {
    $db = freshDb();

    $result = runCommand(new RecentCommand($db));

    expect($result['output'])->toContain('last 2 days');
});

it('shows no documents message when none match', function (): void {
    $db = freshDb();

    // Old document outside the window
    seedDocument($db, [
        'id' => 'old-doc',
        'created_at' => '2020-01-01T00:00:00',
        'modified_at' => '2020-01-01T00:00:00',
    ]);

    $result = runCommand(new RecentCommand($db));

    expect($result['output'])->toContain('No documents created in this period.')
        ->and($result['output'])->toContain('No documents updated in this period.');
});
