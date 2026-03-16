<?php

declare(strict_types=1);

use Vault\Command\ReviewCommand;

it('shows stale seeds older than 14 days', function (): void {
    $db = freshDb();
    seedDocument($db, ['id' => 'old-seed', 'title' => 'Old Seed Idea', 'status' => 'seed', 'modified_at' => '2026-02-01T10:00:00']);
    seedDocument($db, ['id' => 'fresh-seed', 'title' => 'Fresh Seed', 'status' => 'seed', 'modified_at' => date('Y-m-d\TH:i:s')]);
    $result = runCommand(new ReviewCommand($db));
    expect($result['output'])->toContain('Old Seed Idea')->and($result['output'])->not->toContain('Fresh Seed');
});

it('shows stalled growing ideas older than 21 days', function (): void {
    $db = freshDb();
    seedDocument($db, ['id' => 'stalled', 'title' => 'Stalled Project', 'status' => 'growing', 'modified_at' => '2026-01-15T10:00:00']);
    $result = runCommand(new ReviewCommand($db));
    expect($result['output'])->toContain('Stalled Project');
});

it('shows dormant ideas older than 90 days', function (): void {
    $db = freshDb();
    seedDocument($db, ['id' => 'old-dormant', 'title' => 'Dormant Idea', 'status' => 'dormant', 'modified_at' => '2025-10-01T10:00:00']);
    $result = runCommand(new ReviewCommand($db));
    expect($result['output'])->toContain('Dormant Idea');
});

it('shows tbr books older than 60 days', function (): void {
    $db = freshDb();
    seedDocument($db, ['id' => 'old-tbr', 'title' => 'Old TBR Book', 'status' => 'tbr', 'domain' => 'personal', 'subdomain' => 'books', 'modified_at' => '2025-12-01T10:00:00']);
    $result = runCommand(new ReviewCommand($db));
    expect($result['output'])->toContain('Old TBR Book');
});

it('shows manual revisit dates that are due', function (): void {
    $db = freshDb();
    seedDocument($db, ['id' => 'revisit-due', 'title' => 'Revisit This', 'status' => 'growing', 'revisit_date' => '2026-01-01', 'modified_at' => date('Y-m-d\TH:i:s')]);
    $result = runCommand(new ReviewCommand($db));
    expect($result['output'])->toContain('Revisit This');
});

it('returns SUCCESS and shows header', function (): void {
    $db = freshDb();
    $result = runCommand(new ReviewCommand($db));
    expect($result['code'])->toBe(0)->and($result['output'])->toContain('WEEKLY REVIEW');
});
