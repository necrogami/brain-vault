<?php

declare(strict_types=1);

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Vault\Command\InitCommand;

function createTempInitRoot(): string
{
    $root = sys_get_temp_dir() . '/vault_init_test_' . bin2hex(random_bytes(4));

    mkdir($root . '/_index', 0755, true);
    mkdir($root . '/_registry', 0755, true);
    mkdir($root . '/vault', 0755, true);

    // Copy the real schema and registry
    $realSchema = dirname(__DIR__, 4) . '/_index/schema.sql';
    copy($realSchema, $root . '/_index/schema.sql');

    $realRegistry = dirname(__DIR__, 4) . '/_registry/domains.yml';
    copy($realRegistry, $root . '/_registry/domains.yml');

    return $root;
}

function cleanupTempRoot(string $root): void
{
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($root);
}

function runInitCommand(string $root): array
{
    $command = new InitCommand($root);
    $input = new ArrayInput([]);
    $output = new BufferedOutput();

    $code = $command->run($input, $output);

    return ['code' => $code, 'output' => $output->fetch()];
}

it('creates database when it does not exist', function (): void {
    $root = createTempInitRoot();

    try {
        $dbPath = $root . '/_index/vault.db';
        expect(file_exists($dbPath))->toBeFalse();

        $result = runInitCommand($root);

        expect($result['code'])->toBe(Command::SUCCESS)
            ->and(file_exists($dbPath))->toBeTrue()
            ->and($result['output'])->toContain('Database created')
            ->and($result['output'])->toContain('Vault initialized successfully');
    } finally {
        cleanupTempRoot($root);
    }
});

it('succeeds when database already exists', function (): void {
    $root = createTempInitRoot();

    try {
        // Create the DB first
        $dbPath = $root . '/_index/vault.db';
        touch($dbPath);
        $pdo = new PDO("sqlite:{$dbPath}");
        $pdo->exec(file_get_contents($root . '/_index/schema.sql'));
        $pdo = null;

        $result = runInitCommand($root);

        expect($result['code'])->toBe(Command::SUCCESS)
            ->and($result['output'])->toContain('Database already exists')
            ->and($result['output'])->toContain('Vault initialized successfully');
    } finally {
        cleanupTempRoot($root);
    }
});

it('validates environment checks', function (): void {
    $root = createTempInitRoot();

    try {
        $result = runInitCommand($root);

        expect($result['output'])->toContain('PHP')
            ->and($result['output'])->toContain('pdo_sqlite')
            ->and($result['output'])->toContain('git')
            ->and($result['output'])->toContain('Schema file found')
            ->and($result['output'])->toContain('Domain registry found');
    } finally {
        cleanupTempRoot($root);
    }
});

it('fails when schema file is missing', function (): void {
    $root = createTempInitRoot();

    try {
        unlink($root . '/_index/schema.sql');

        $result = runInitCommand($root);

        expect($result['code'])->toBe(Command::FAILURE)
            ->and($result['output'])->toContain('Schema file not found');
    } finally {
        cleanupTempRoot($root);
    }
});

it('indexes existing vault files during init', function (): void {
    $root = createTempInitRoot();

    try {
        // Create a vault file before init
        $fileDir = $root . '/vault/tech/fleeting/2026/03';
        mkdir($fileDir, 0755, true);
        file_put_contents($fileDir . '/20260316-100000-test-idea.md', <<<'MD'
            ---
            id: "20260316-100000-test-idea"
            title: "Test Idea"
            domain: tech
            subdomain: fleeting
            status: seed
            created: 2026-03-16T10:00:00
            modified: 2026-03-16T10:00:00
            tags: [test]
            priority: p3-low
            confidence: speculative
            effort: null
            summary: "A test idea"
            revisit_date: null
            links: {}
            sources: []
            todos: []
            ---

            ## Spark

            Test content.
            MD,
        );

        $result = runInitCommand($root);

        expect($result['code'])->toBe(Command::SUCCESS)
            ->and($result['output'])->toContain('Files processed: 1');

        // Verify the document was indexed
        $db = new \Vault\Database($root . '/_index/vault.db');
        $doc = $db->fetchOne('SELECT * FROM documents WHERE id = :id', [':id' => '20260316-100000-test-idea']);

        expect($doc)->not->toBeNull()
            ->and($doc['title'])->toBe('Test Idea')
            ->and($doc['domain'])->toBe('tech');
    } finally {
        cleanupTempRoot($root);
    }
});
