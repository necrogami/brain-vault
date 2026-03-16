<?php

declare(strict_types=1);

use Vault\Command\RebuildCommand;
use Vault\Database;

/**
 * Create a temporary project root directory with the required structure.
 *
 * @return array{root: string, db: Database}
 */
function createTempProjectRoot(): array
{
    $root = sys_get_temp_dir() . '/vault_rebuild_test_' . bin2hex(random_bytes(4));

    mkdir($root . '/_index', 0755, true);
    mkdir($root . '/vault', 0755, true);

    // Copy the real schema
    $realSchema = dirname(__DIR__, 4) . '/_index/schema.sql';
    copy($realSchema, $root . '/_index/schema.sql');

    // Create a fresh database
    $dbPath = $root . '/_index/vault.db';
    touch($dbPath);
    $db = new Database($dbPath);
    $db->executeRaw(file_get_contents($root . '/_index/schema.sql'));

    return ['root' => $root, 'db' => $db];
}

/**
 * Recursively remove a directory.
 */
function removeTempDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }

    rmdir($dir);
}

/**
 * Write a markdown file with YAML frontmatter into the temp vault.
 */
function writeVaultFile(string $root, string $relativePath, string $content): void
{
    $fullPath = $root . '/' . $relativePath;
    $dir = dirname($fullPath);

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($fullPath, $content);
}

it('fails when schema file is missing', function (): void {
    $root = sys_get_temp_dir() . '/vault_rebuild_noschema_' . bin2hex(random_bytes(4));
    mkdir($root . '/_index', 0755, true);
    mkdir($root . '/vault', 0755, true);

    $dbPath = $root . '/_index/vault.db';
    touch($dbPath);
    $db = new Database($dbPath);

    try {
        // No schema.sql file exists
        $result = runCommand(new RebuildCommand($db, $root));

        expect($result['code'])->not->toBe(0)
            ->and($result['output'])->toContain('Schema file not found');
    } finally {
        removeTempDir($root);
    }
});

it('handles empty vault directory', function (): void {
    ['root' => $root, 'db' => $db] = createTempProjectRoot();

    try {
        $result = runCommand(new RebuildCommand($db, $root));

        expect($result['code'])->toBe(0)
            ->and($result['output'])->toContain('No markdown files found');
    } finally {
        removeTempDir($root);
    }
});

it('processes sources from frontmatter', function (): void {
    ['root' => $root, 'db' => $db] = createTempProjectRoot();

    try {
        writeVaultFile($root, 'vault/tech/research/2026/03/20260315-160000-sourced.md', <<<'MD'
            ---
            id: "20260315-160000-sourced"
            title: "Sourced Document"
            domain: tech
            subdomain: research
            status: seed
            created: "2026-03-15T16:00:00"
            modified: "2026-03-15T16:00:00"
            tags: []
            priority: p3-low
            confidence: speculative
            effort: null
            summary: "A document with sources"
            revisit_date: null
            links: {}
            sources:
              - url: "https://example.com/article"
                title: "Example Article"
                accessed: "2026-03-15"
            todos: []
            ---

            Content.
            MD,
        );

        $result = runCommand(new RebuildCommand($db, $root));

        expect($result['code'])->toBe(0);

        $sources = $db->fetchAll(
            'SELECT url, title FROM sources WHERE doc_id = :id',
            [':id' => '20260315-160000-sourced'],
        );

        expect($sources)->toHaveCount(1)
            ->and($sources[0]['url'])->toBe('https://example.com/article')
            ->and($sources[0]['title'])->toBe('Example Article');
    } finally {
        removeTempDir($root);
    }
});

it('processes todos from frontmatter', function (): void {
    ['root' => $root, 'db' => $db] = createTempProjectRoot();

    try {
        writeVaultFile($root, 'vault/tech/fleeting/2026/03/20260315-170000-with-todos.md', <<<'MD'
            ---
            id: "20260315-170000-with-todos"
            title: "Document With Todos"
            domain: tech
            subdomain: fleeting
            status: growing
            created: "2026-03-15T17:00:00"
            modified: "2026-03-15T17:00:00"
            tags: []
            priority: p3-low
            confidence: speculative
            effort: null
            summary: "Has todos"
            revisit_date: null
            links: {}
            sources: []
            todos:
              - task: "Research alternatives"
                due: "2026-04-01"
                priority: p2-medium
                status: open
            ---

            Content.
            MD,
        );

        $result = runCommand(new RebuildCommand($db, $root));

        expect($result['code'])->toBe(0);

        $todos = $db->fetchAll(
            'SELECT content, priority, status FROM todos WHERE doc_id = :id',
            [':id' => '20260315-170000-with-todos'],
        );

        expect($todos)->toHaveCount(1)
            ->and($todos[0]['content'])->toBe('Research alternatives')
            ->and($todos[0]['priority'])->toBe('p2-medium')
            ->and($todos[0]['status'])->toBe('open');
    } finally {
        removeTempDir($root);
    }
});

it('processes files and inserts into documents table', function (): void {
    ['root' => $root, 'db' => $db] = createTempProjectRoot();

    try {
        writeVaultFile($root, 'vault/tech/fleeting/2026/03/20260315-100000-test-idea.md', <<<'MD'
            ---
            id: "20260315-100000-test-idea"
            title: "Test Idea"
            domain: tech
            subdomain: fleeting
            status: seed
            created: 2026-03-15T10:00:00
            modified: 2026-03-15T10:00:00
            tags: []
            priority: p3-low
            confidence: speculative
            effort: medium
            summary: "A test idea for rebuild"
            revisit_date: null
            links: {}
            sources: []
            todos: []
            ---

            ## Core Idea

            This is a test idea.
            MD,
        );

        $result = runCommand(new RebuildCommand($db, $root));

        expect($result['code'])->toBe(0)
            ->and($result['output'])->toContain('Files processed: 1')
            ->and($result['output'])->toContain('Errors: 0');

        $row = $db->fetchOne('SELECT * FROM documents WHERE id = :id', [':id' => '20260315-100000-test-idea']);

        expect($row)->not->toBeNull()
            ->and($row['title'])->toBe('Test Idea')
            ->and($row['domain'])->toBe('tech')
            ->and($row['subdomain'])->toBe('fleeting')
            ->and($row['status'])->toBe('seed');
    } finally {
        removeTempDir($root);
    }
});

it('inserts tags from frontmatter', function (): void {
    ['root' => $root, 'db' => $db] = createTempProjectRoot();

    try {
        writeVaultFile($root, 'vault/tech/fleeting/2026/03/20260315-110000-tagged-idea.md', <<<'MD'
            ---
            id: "20260315-110000-tagged-idea"
            title: "Tagged Idea"
            domain: tech
            subdomain: fleeting
            status: seed
            created: 2026-03-15T11:00:00
            modified: 2026-03-15T11:00:00
            tags: [rust, performance, systems]
            priority: p2-medium
            confidence: medium
            effort: null
            summary: "An idea with tags"
            revisit_date: null
            links: {}
            sources: []
            todos: []
            ---

            ## Core Idea

            Tagged content.
            MD,
        );

        $result = runCommand(new RebuildCommand($db, $root));

        expect($result['code'])->toBe(0);

        $tags = $db->fetchAll(
            'SELECT tag FROM tags WHERE doc_id = :id ORDER BY tag',
            [':id' => '20260315-110000-tagged-idea'],
        );

        expect($tags)->toHaveCount(3)
            ->and($tags[0]['tag'])->toBe('performance')
            ->and($tags[1]['tag'])->toBe('rust')
            ->and($tags[2]['tag'])->toBe('systems');
    } finally {
        removeTempDir($root);
    }
});

it('inserts links from frontmatter', function (): void {
    ['root' => $root, 'db' => $db] = createTempProjectRoot();

    try {
        writeVaultFile($root, 'vault/tech/fleeting/2026/03/20260315-120000-linked-idea.md', <<<'MD'
            ---
            id: "20260315-120000-linked-idea"
            title: "Linked Idea"
            domain: tech
            subdomain: fleeting
            status: growing
            created: 2026-03-15T12:00:00
            modified: 2026-03-15T12:00:00
            tags: []
            priority: p3-low
            confidence: speculative
            effort: null
            summary: "An idea with links"
            revisit_date: null
            links:
              parent: []
              children: []
              related: ["some-other-idea", "another-idea"]
              inspired_by: ["original-idea"]
              blocks: []
              blocked_by: []
              evolved_into: []
            sources: []
            todos: []
            ---

            ## Core Idea

            Linked content.
            MD,
        );

        $result = runCommand(new RebuildCommand($db, $root));

        expect($result['code'])->toBe(0);

        $links = $db->fetchAll(
            'SELECT target_id, link_type FROM links WHERE source_id = :id ORDER BY target_id',
            [':id' => '20260315-120000-linked-idea'],
        );

        expect($links)->toHaveCount(3)
            ->and($links[0]['target_id'])->toBe('another-idea')
            ->and($links[0]['link_type'])->toBe('related')
            ->and($links[1]['target_id'])->toBe('original-idea')
            ->and($links[1]['link_type'])->toBe('inspired_by')
            ->and($links[2]['target_id'])->toBe('some-other-idea')
            ->and($links[2]['link_type'])->toBe('related');
    } finally {
        removeTempDir($root);
    }
});

it('shows progress report', function (): void {
    ['root' => $root, 'db' => $db] = createTempProjectRoot();

    try {
        writeVaultFile($root, 'vault/tech/fleeting/2026/03/20260315-130000-report-test.md', <<<'MD'
            ---
            id: "20260315-130000-report-test"
            title: "Report Test"
            domain: tech
            subdomain: fleeting
            status: seed
            created: 2026-03-15T13:00:00
            modified: 2026-03-15T13:00:00
            tags: []
            priority: p3-low
            confidence: speculative
            effort: null
            summary: "Testing the report"
            revisit_date: null
            links: {}
            sources: []
            todos: []
            ---

            Content.
            MD,
        );

        $result = runCommand(new RebuildCommand($db, $root));

        expect($result['code'])->toBe(0)
            ->and($result['output'])->toContain('Rebuild complete.')
            ->and($result['output'])->toContain('Files processed: 1')
            ->and($result['output'])->toContain('Errors: 0')
            ->and($result['output'])->toContain('Orphaned ideas');
    } finally {
        removeTempDir($root);
    }
});

it('handles files with invalid frontmatter gracefully', function (): void {
    ['root' => $root, 'db' => $db] = createTempProjectRoot();

    try {
        // Valid file
        writeVaultFile($root, 'vault/tech/fleeting/2026/03/20260315-140000-valid.md', <<<'MD'
            ---
            id: "20260315-140000-valid"
            title: "Valid Document"
            domain: tech
            subdomain: fleeting
            status: seed
            created: 2026-03-15T14:00:00
            modified: 2026-03-15T14:00:00
            tags: []
            priority: p3-low
            confidence: speculative
            effort: null
            summary: "A valid document"
            revisit_date: null
            links: {}
            sources: []
            todos: []
            ---

            Valid content.
            MD,
        );

        // Invalid file (no frontmatter delimiters)
        writeVaultFile($root, 'vault/tech/fleeting/2026/03/broken-file.md', <<<'MD'
            This file has no frontmatter at all.
            Just plain text.
            MD,
        );

        // Missing required fields
        writeVaultFile($root, 'vault/tech/fleeting/2026/03/missing-fields.md', <<<'MD'
            ---
            status: seed
            tags: []
            ---

            Missing id, title, domain, subdomain.
            MD,
        );

        $result = runCommand(new RebuildCommand($db, $root));

        expect($result['code'])->toBe(0)
            ->and($result['output'])->toContain('Files processed: 1')
            ->and($result['output'])->toContain('Errors: 2');

        // Only the valid file should be in the database
        $count = $db->fetchValue('SELECT COUNT(*) FROM documents');
        expect((int) $count)->toBe(1);
    } finally {
        removeTempDir($root);
    }
});

it('processes book-specific fields for personal/books domain', function (): void {
    ['root' => $root, 'db' => $db] = createTempProjectRoot();

    try {
        writeVaultFile($root, 'vault/personal/books/2026/03/20260315-150000-dune.md', <<<'MD'
            ---
            id: "20260315-150000-dune"
            title: "Dune"
            domain: personal
            subdomain: books
            status: mature
            created: 2026-03-15T15:00:00
            modified: 2026-03-15T15:00:00
            tags: [sci-fi, classic]
            priority: p3-low
            confidence: high
            effort: null
            summary: "Frank Herbert's masterpiece"
            revisit_date: null
            links: {}
            sources: []
            todos: []
            author: "Frank Herbert"
            series: null
            series_order: null
            rating: "S"
            cover_url: "https://example.com/dune.jpg"
            reads:
              - "2024-06-15"
              - "2026-01-20"
            ---

            ## Review

            A masterpiece of science fiction.
            MD,
        );

        $result = runCommand(new RebuildCommand($db, $root));

        expect($result['code'])->toBe(0)
            ->and($result['output'])->toContain('Files processed: 1');

        $book = $db->fetchOne('SELECT * FROM books WHERE doc_id = :id', [':id' => '20260315-150000-dune']);

        expect($book)->not->toBeNull()
            ->and($book['author'])->toBe('Frank Herbert')
            ->and($book['rating'])->toBe('S')
            ->and($book['cover_url'])->toBe('https://example.com/dune.jpg');

        $reads = $db->fetchAll(
            'SELECT date_read FROM reads WHERE doc_id = :id ORDER BY date_read',
            [':id' => '20260315-150000-dune'],
        );

        expect($reads)->toHaveCount(2)
            ->and($reads[0]['date_read'])->toContain('2024-06-15')
            ->and($reads[1]['date_read'])->toContain('2026-01-20');
    } finally {
        removeTempDir($root);
    }
});

it('processes close_reason field', function (): void {
    ['root' => $root, 'db' => $db] = createTempProjectRoot();
    try {
        writeVaultFile($root, 'vault/tech/fleeting/2026/03/20260315-closed.md', <<<'MD'
            ---
            id: "20260315-closed"
            title: "Closed Idea"
            domain: tech
            subdomain: fleeting
            status: closed
            created: "2026-03-15T10:00:00"
            modified: "2026-03-15T12:00:00"
            tags: []
            priority: p3-low
            confidence: speculative
            effort: null
            summary: "Closed"
            revisit_date: null
            close_reason: "Superseded by better approach"
            links: {}
            sources: []
            todos: []
            ---
            Content.
            MD);
        $result = runCommand(new \Vault\Command\RebuildCommand($db, $root));
        expect($result['code'])->toBe(0);
        $row = $db->fetchOne('SELECT close_reason FROM documents WHERE id = :id', [':id' => '20260315-closed']);
        expect($row['close_reason'])->toBe('Superseded by better approach');
    } finally {
        removeTempDir($root);
    }
});

it('processes external_refs from frontmatter', function (): void {
    ['root' => $root, 'db' => $db] = createTempProjectRoot();
    try {
        writeVaultFile($root, 'vault/tech/projects/2026/03/20260315-built.md', <<<'MD'
            ---
            id: "20260315-built"
            title: "Built Project"
            domain: tech
            subdomain: projects
            status: built
            created: "2026-03-15T10:00:00"
            modified: "2026-03-15T12:00:00"
            tags: []
            priority: p3-low
            confidence: high
            effort: large
            summary: "Shipped"
            revisit_date: null
            close_reason: "Launched"
            external_refs:
              - url: "https://github.com/example/repo"
                label: "Repository"
              - url: "https://example.com"
                label: "Production"
            links: {}
            sources: []
            todos: []
            ---
            Content.
            MD);
        $result = runCommand(new \Vault\Command\RebuildCommand($db, $root));
        expect($result['code'])->toBe(0);
        $refs = $db->fetchAll('SELECT url, label FROM external_refs WHERE doc_id = :id ORDER BY url', [':id' => '20260315-built']);
        expect($refs)->toHaveCount(2)
            ->and($refs[0]['url'])->toBe('https://example.com')
            ->and($refs[1]['url'])->toBe('https://github.com/example/repo');
    } finally {
        removeTempDir($root);
    }
});
