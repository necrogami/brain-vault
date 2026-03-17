<?php

declare(strict_types=1);

use Vault\Command\MetricsCommand;

it('shows flow metrics for default 30-day period', function (): void {
    $db = freshDb();
    seedDocument($db, ['id' => 'r1', 'status' => 'seed', 'created_at' => date('Y-m-d\TH:i:s')]);
    seedDocument($db, ['id' => 'r2', 'status' => 'closed', 'created_at' => date('Y-m-d\TH:i:s'), 'modified_at' => date('Y-m-d\TH:i:s')]);
    $result = runCommand(new MetricsCommand($db));
    expect($result['code'])->toBe(0)->and($result['output'])->toContain('FLOW')->and($result['output'])->toContain('Created');
});

it('shows status funnel', function (): void {
    $db = freshDb();
    seedDocument($db, ['id' => 's1', 'status' => 'seed']);
    seedDocument($db, ['id' => 's2', 'status' => 'seed']);
    seedDocument($db, ['id' => 'g1', 'status' => 'growing']);
    seedDocument($db, ['id' => 'm1', 'status' => 'mature']);
    $result = runCommand(new MetricsCommand($db));
    expect($result['output'])->toContain('FUNNEL')->and($result['output'])->toContain('seed');
});

it('respects --period argument', function (): void {
    $db = freshDb();
    $result = runCommand(new MetricsCommand($db), ['--period' => '7']);
    expect($result['code'])->toBe(0)->and($result['output'])->toContain('7 days');
});

it('supports --all flag for all time', function (): void {
    $db = freshDb();
    $result = runCommand(new MetricsCommand($db), ['--all' => true]);
    expect($result['code'])->toBe(0)->and($result['output'])->toContain('all time');
});

it('shows domain breakdown', function (): void {
    $db = freshDb();
    seedDocument($db, ['id' => 't1', 'domain' => 'tech', 'created_at' => date('Y-m-d\TH:i:s')]);
    seedDocument($db, ['id' => 'b1', 'domain' => 'business', 'created_at' => date('Y-m-d\TH:i:s')]);
    $result = runCommand(new MetricsCommand($db));
    expect($result['output'])->toContain('DOMAIN FOCUS');
});

it('shows book stats in period', function (): void {
    $db = freshDb();
    $id = seedBook($db, ['created_at' => date('Y-m-d\TH:i:s')]);
    seedMediaEvent($db, $id, 'read', date('Y-m-d'));
    $result = runCommand(new MetricsCommand($db));
    expect($result['output'])->toContain('BOOKS');
});
