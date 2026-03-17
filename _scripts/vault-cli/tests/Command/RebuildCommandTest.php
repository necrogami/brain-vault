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

    $result = runCommand(new RebuildCommand($db, $root));

    expect($result['code'])->toBe(1)
        ->and($result['output'])->toContain('Schema file not found');

    removeTempDir($root);
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
        writeVaultFile($root, 'vault/tech/research/2026/03/20260315-120000-test-sources.md', <<<'MD'
            ---
            id: "20260315-120000-test-sources"
            title: "Test Sources"
            domain: tech
            subdomain: research
            status: growing
            created: "2026-03-15T12:00:00"
            modified: "2026-03-15T12:00:00"
            tags: []
            priority: p3-low
            confidence: speculative
            effort: null
            summary: "Testing source parsing"
            revisit_date: null
            links: {}
            sources:
              - url: "https://example.com"
                title: "Example Source"
                accessed: "2026-03-15"
            todos: []
            ---

            Content with sources.
            MD,
        );

        $result = runCommand(new RebuildCommand($db, $root));

        expect($result['code'])->toBe(0);

        $sources = $db->fetchAll(
            'SELECT url, title FROM sources WHERE doc_id = :id',
            [':id' => '20260315-120000-test-sources'],
        );

        expect($sources)->toHaveCount(1)
            ->and($sources[0]['url'])->toBe('https://example.com')
            ->and($sources[0]['title'])->toBe('Example Source');
    } finally {
        removeTempDir($root);
    }
});

it('processes todos from frontmatter', function (): void {
    ['root' => $root, 'db' => $db] = createTempProjectRoot();

    try {
        writeVaultFile($root, 'vault/tech/research/2026/03/20260315-130000-test-todos.md', <<<'MD'
            ---
            id: "20260315-130000-test-todos"
            title: "Test TODOs"
            domain: tech
            subdomain: research
            status: growing
            created: "2026-03-15T13:00:00"
            modified: "2026-03-15T13:00:00"
            tags: []
            priority: p3-low
            confidence: speculative
            effort: null
            summary: "Testing TODO parsing"
            revisit_date: null
            links: {}
            sources: []
            todos:
              - task: "Write tests"
                due: "2026-03-20"
                priority: p1-high
                status: open
              - task: "Review code"
                due: null
                priority: p3-low
                status: open
            ---

            Content with TODOs.
            MD,
        );

        $result = runCommand(new RebuildCommand($db, $root));

        expect($result['code'])->toBe(0);

        $todos = $db->fetchAll(
            'SELECT content, priority, status FROM todos WHERE doc_id = :id ORDER BY content',
            [':id' => '20260315-130000-test-todos'],
        );

        expect($todos)->toHaveCount(2)
            ->and($todos[0]['content'])->toBe('Review code')
            ->and($todos[1]['content'])->toBe('Write tests')
            ->and($todos[1]['priority'])->toBe('p1-high');
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
            status: growing
            created: "2026-03-15T10:00:00"
            modified: "2026-03-15T12:00:00"
            tags: [ai, testing]
            priority: p2-medium
            confidence: medium
            effort: small
            summary: "A test idea for rebuild"
            revisit_date: null
            links: {}
            sources: []
            todos: []
            ---

            Test content.
            MD,
        );

        $result = runCommand(new RebuildCommand($db, $root));

        expect($result['code'])->toBe(0)
            ->and($result['output'])->toContain('Files processed: 1')
            ->and($result['output'])->toContain('Errors: 0');

        $doc = $db->fetchOne(
            'SELECT * FROM documents WHERE id = :id',
            [':id' => '20260315-100000-test-idea'],
        );

        expect($doc)->not->toBeNull()
            ->and($doc['title'])->toBe('Test Idea')
            ->and($doc['domain'])->toBe('tech')
            ->and($doc['subdomain'])->toBe('fleeting')
            ->and($doc['status'])->toBe('growing');
    } finally {
        removeTempDir($root);
    }
});

it('inserts tags from frontmatter', function (): void {
    ['root' => $root, 'db' => $db] = createTempProjectRoot();

    try {
        writeVaultFile($root, 'vault/tech/fleeting/2026/03/20260315-110000-tags-test.md', <<<'MD'
            ---
            id: "20260315-110000-tags-test"
            title: "Tags Test"
            domain: tech
            subdomain: fleeting
            status: seed
            created: "2026-03-15T11:00:00"
            modified: "2026-03-15T11:00:00"
            tags: [ai, ml, testing]
            priority: p3-low
            confidence: speculative
            effort: null
            summary: "Testing tag insertion"
            revisit_date: null
            links: {}
            sources: []
            todos: []
            ---

            Tag test content.
            MD,
        );

        $result = runCommand(new RebuildCommand($db, $root));

        expect($result['code'])->toBe(0);

        $tags = $db->fetchAll(
            'SELECT tag FROM tags WHERE doc_id = :id ORDER BY tag',
            [':id' => '20260315-110000-tags-test'],
        );

        expect($tags)->toHaveCount(3)
            ->and($tags[0]['tag'])->toBe('ai')
            ->and($tags[1]['tag'])->toBe('ml')
            ->and($tags[2]['tag'])->toBe('testing');
    } finally {
        removeTempDir($root);
    }
});

it('inserts links from frontmatter', function (): void {
    ['root' => $root, 'db' => $db] = createTempProjectRoot();

    try {
        writeVaultFile($root, 'vault/tech/fleeting/2026/03/20260315-link-source.md', <<<'MD'
            ---
            id: "20260315-link-source"
            title: "Link Source"
            domain: tech
            subdomain: fleeting
            status: seed
            created: "2026-03-15T10:00:00"
            modified: "2026-03-15T10:00:00"
            tags: []
            priority: p3-low
            confidence: speculative
            effort: null
            summary: "Source of a link"
            revisit_date: null
            links:
              parent: []
              children: []
              related: ["other-idea"]
            sources: []
            todos: []
            ---

            Content.
            MD,
        );

        $result = runCommand(new RebuildCommand($db, $root));

        expect($result['code'])->toBe(0);

        $links = $db->fetchAll(
            'SELECT target_id, link_type FROM links WHERE source_id = :id',
            [':id' => '20260315-link-source'],
        );

        expect($links)->toHaveCount(1)
            ->and($links[0]['target_id'])->toBe('other-idea')
            ->and($links[0]['link_type'])->toBe('related');
    } finally {
        removeTempDir($root);
    }
});

it('shows progress report', function (): void {
    ['root' => $root, 'db' => $db] = createTempProjectRoot();

    try {
        writeVaultFile($root, 'vault/tech/fleeting/2026/03/20260315-progress.md', <<<'MD'
            ---
            id: "20260315-progress"
            title: "Progress Test"
            domain: tech
            subdomain: fleeting
            status: seed
            created: "2026-03-15T10:00:00"
            modified: "2026-03-15T10:00:00"
            tags: []
            priority: p3-low
            ---

            Content.
            MD,
        );

        $result = runCommand(new RebuildCommand($db, $root));

        expect($result['code'])->toBe(0)
            ->and($result['output'])->toContain('Rebuild complete.')
            ->and($result['output'])->toContain('Files processed:')
            ->and($result['output'])->toContain('Errors:')
            ->and($result['output'])->toContain('Orphaned ideas');
    } finally {
        removeTempDir($root);
    }
});

it('handles files with invalid frontmatter gracefully', function (): void {
    ['root' => $root, 'db' => $db] = createTempProjectRoot();

    try {
        writeVaultFile($root, 'vault/tech/fleeting/2026/03/invalid.md', "No frontmatter here.");

        $result = runCommand(new RebuildCommand($db, $root));

        expect($result['code'])->toBe(0)
            ->and($result['output'])->toContain('Errors: 1');
    } finally {
        removeTempDir($root);
    }
});

it('processes book with meta key during rebuild', function (): void {
    ['root' => $root, 'db' => $db] = createTempProjectRoot();

    try {
        writeVaultFile($root, 'vault/entertainment/books/2026/03/20260315-150000-dune.md', <<<'MD'
            ---
            id: "20260315-150000-dune"
            title: "Dune"
            domain: entertainment
            subdomain: books
            status: mature
            created: "2026-03-15T15:00:00"
            modified: "2026-03-15T15:00:00"
            tags: [sci-fi, classic]
            priority: p3-low
            confidence: high
            effort: null
            summary: "Frank Herbert's masterpiece"
            revisit_date: null
            links: {}
            sources: []
            todos: []
            meta:
              author: "Frank Herbert"
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

        $doc = $db->fetchOne('SELECT meta, rating FROM documents WHERE id = :id', [':id' => '20260315-150000-dune']);
        $meta = json_decode($doc['meta'], true);

        expect($meta['author'])->toBe('Frank Herbert')
            ->and($meta['rating'])->toBe('S')
            ->and($meta['cover_url'])->toBe('https://example.com/dune.jpg')
            ->and($doc['rating'])->toBe('S');

        $events = $db->fetchAll(
            'SELECT event_type, event_date FROM media_events WHERE doc_id = :id ORDER BY event_date',
            [':id' => '20260315-150000-dune'],
        );

        expect($events)->toHaveCount(2)
            ->and($events[0]['event_type'])->toBe('read')
            ->and($events[0]['event_date'])->toContain('2024-06-15')
            ->and($events[1]['event_date'])->toContain('2026-01-20');
    } finally {
        removeTempDir($root);
    }
});

it('processes book with flat fields for backward compatibility', function (): void {
    ['root' => $root, 'db' => $db] = createTempProjectRoot();

    try {
        writeVaultFile($root, 'vault/entertainment/books/2026/03/20260315-old-book.md', <<<'MD'
            ---
            id: "20260315-old-book"
            title: "Old Format Book"
            domain: entertainment
            subdomain: books
            status: mature
            created: "2026-03-15T15:00:00"
            modified: "2026-03-15T15:00:00"
            tags: []
            priority: p3-low
            summary: "Backward compat test"
            links: {}
            sources: []
            todos: []
            author: "Test Author"
            rating: "A"
            cover_url: "https://example.com/old.jpg"
            reads:
              - "2026-03-15"
            ---

            Content.
            MD,
        );

        $result = runCommand(new RebuildCommand($db, $root));

        expect($result['code'])->toBe(0);

        $doc = $db->fetchOne('SELECT meta, rating FROM documents WHERE id = :id', [':id' => '20260315-old-book']);
        $meta = json_decode($doc['meta'], true);

        expect($meta['author'])->toBe('Test Author')
            ->and($meta['rating'])->toBe('A')
            ->and($doc['rating'])->toBe('A');

        $events = $db->fetchAll('SELECT * FROM media_events WHERE doc_id = :id', [':id' => '20260315-old-book']);
        expect($events)->toHaveCount(1)
            ->and($events[0]['event_type'])->toBe('read');
    } finally {
        removeTempDir($root);
    }
});

it('processes serial book fields during rebuild', function (): void {
    ['root' => $root, 'db' => $db] = createTempProjectRoot();

    try {
        writeVaultFile($root, 'vault/entertainment/books/2026/03/20260315-serial-book.md', <<<'MD'
            ---
            id: "20260315-serial-book"
            title: "Serial Book"
            domain: entertainment
            subdomain: books
            status: growing
            created: "2026-03-15T10:00:00"
            modified: "2026-03-15T12:00:00"
            tags: [litrpg]
            priority: p3-low
            confidence: high
            effort: null
            summary: "A serial book on Patreon"
            revisit_date: null
            links: {}
            sources: []
            todos: []
            meta:
              author: "Serial Author"
              serial: true
              platform: "patreon"
              serial_url: "https://patreon.com/example"
              current_chapter: 26
              chapters_available: 53
            reads: []
            ---

            ## Progress

            Reading on Patreon.
            MD,
        );

        $result = runCommand(new RebuildCommand($db, $root));

        expect($result['code'])->toBe(0)
            ->and($result['output'])->toContain('Files processed: 1');

        $doc = $db->fetchOne('SELECT meta FROM documents WHERE id = :id', [':id' => '20260315-serial-book']);
        $meta = json_decode($doc['meta'], true);

        expect($meta['author'])->toBe('Serial Author')
            ->and($meta['serial'])->toBeTrue()
            ->and($meta['platform'])->toBe('patreon')
            ->and($meta['serial_url'])->toBe('https://patreon.com/example')
            ->and($meta['current_chapter'])->toBe(26)
            ->and($meta['chapters_available'])->toBe(53);
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
            summary: "Closed for testing"
            revisit_date: null
            close_reason: "Superseded by better approach"
            links: {}
            sources: []
            todos: []
            ---

            Content.
            MD,
        );

        $result = runCommand(new RebuildCommand($db, $root));

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
        $result = runCommand(new RebuildCommand($db, $root));
        expect($result['code'])->toBe(0);
        $refs = $db->fetchAll('SELECT url, label FROM external_refs WHERE doc_id = :id ORDER BY url', [':id' => '20260315-built']);
        expect($refs)->toHaveCount(2)
            ->and($refs[0]['url'])->toBe('https://example.com')
            ->and($refs[1]['url'])->toBe('https://github.com/example/repo');
    } finally {
        removeTempDir($root);
    }
});
