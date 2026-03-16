<?php

declare(strict_types=1);

use Vault\Command\IntegrityCommand;

it('shows db count vs file count when they match', function (): void {
    $projectRoot = sys_get_temp_dir() . '/vault_integrity_' . bin2hex(random_bytes(4));
    $vaultDir = $projectRoot . '/vault/tech/fleeting/2026/03';
    mkdir($vaultDir, 0777, true);
    register_shutdown_function(function () use ($projectRoot): void {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($projectRoot);
    });

    // Create one markdown file on disk
    file_put_contents($vaultDir . '/test-doc.md', '---
id: match-doc
title: Match
---
');

    $db = freshDb();
    seedDocument($db, ['id' => 'match-doc']);

    $result = runCommand(new IntegrityCommand($db, $projectRoot));

    expect($result['output'])->toMatch('/Document count.*DB \(1\).*filesystem \(1\)/');
});

it('detects db vs file count mismatch', function (): void {
    $projectRoot = sys_get_temp_dir() . '/vault_integrity_' . bin2hex(random_bytes(4));
    mkdir($projectRoot . '/vault', 0777, true);
    register_shutdown_function(function () use ($projectRoot): void {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($projectRoot);
    });

    // DB has 2 documents, filesystem has 0
    $db = freshDb();
    seedDocument($db, ['id' => 'db-only-1']);
    seedDocument($db, ['id' => 'db-only-2']);

    $result = runCommand(new IntegrityCommand($db, $projectRoot));

    expect($result['output'])->toContain('Document count mismatch')
        ->and($result['output'])->toContain('DB has 2')
        ->and($result['output'])->toContain('filesystem has 0')
        ->and($result['code'])->toBe(1);
});

it('detects broken links', function (): void {
    $projectRoot = sys_get_temp_dir() . '/vault_integrity_' . bin2hex(random_bytes(4));
    mkdir($projectRoot . '/vault', 0777, true);
    register_shutdown_function(function () use ($projectRoot): void {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($projectRoot);
    });

    $db = freshDb();
    seedDocument($db, ['id' => 'existing-doc']);

    // Insert a link pointing to a nonexistent target
    $db->execute(
        'INSERT INTO links (source_id, target_id, link_type) VALUES (:source, :target, :type)',
        [':source' => 'existing-doc', ':target' => 'ghost-doc', ':type' => 'related'],
    );

    $result = runCommand(new IntegrityCommand($db, $projectRoot));

    expect($result['output'])->toContain('broken link(s) found')
        ->and($result['output'])->toContain('existing-doc')
        ->and($result['output'])->toContain('ghost-doc')
        ->and($result['output'])->toContain('(missing)')
        ->and($result['code'])->toBe(1);
});

it('shows journal mode', function (): void {
    $projectRoot = sys_get_temp_dir() . '/vault_integrity_' . bin2hex(random_bytes(4));
    mkdir($projectRoot . '/vault', 0777, true);
    register_shutdown_function(function () use ($projectRoot): void {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($projectRoot);
    });

    $db = freshDb();

    $result = runCommand(new IntegrityCommand($db, $projectRoot));

    expect($result['output'])->toContain('SQLite journal mode:');
});

it('reports all checks passed when no issues exist', function (): void {
    $projectRoot = sys_get_temp_dir() . '/vault_integrity_' . bin2hex(random_bytes(4));
    mkdir($projectRoot . '/vault', 0777, true);
    register_shutdown_function(function () use ($projectRoot): void {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($projectRoot);
    });

    $db = freshDb();

    // No documents in DB, no files on disk = counts match, no broken links
    $result = runCommand(new IntegrityCommand($db, $projectRoot));

    expect($result['output'])->toContain('All checks passed.')
        ->and($result['output'])->toContain('No broken links found')
        ->and($result['code'])->toBe(0);
});
