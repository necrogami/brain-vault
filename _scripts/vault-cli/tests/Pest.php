<?php

declare(strict_types=1);

use Vault\Database;

/*
|--------------------------------------------------------------------------
| Test Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create a fresh Database instance with the schema applied.
 * Uses a temp file so SQLite features like FTS work correctly.
 */
function freshDb(): Database
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'vault_test_') . '.db';
    $schemaPath = dirname(__DIR__) . '/../../_index/schema.sql';

    // Create empty file so Database constructor doesn't fail
    touch($tmpFile);
    $db = new Database($tmpFile);
    $db->executeRaw(file_get_contents($schemaPath));

    // Register cleanup
    register_shutdown_function(fn() => @unlink($tmpFile));

    return $db;
}

/**
 * Seed a minimal document into the database.
 */
function seedDocument(Database $db, array $overrides = []): string
{
    $defaults = [
        'id' => 'test-doc-' . bin2hex(random_bytes(4)),
        'title' => 'Test Document',
        'domain' => 'tech',
        'subdomain' => 'fleeting',
        'status' => 'seed',
        'priority' => 'p3-low',
        'confidence' => 'speculative',
        'effort' => null,
        'summary' => 'A test document',
        'file_path' => 'vault/tech/fleeting/2026/03/' . bin2hex(random_bytes(4)) . '.md',
        'created_at' => '2026-03-15T10:00:00',
        'modified_at' => '2026-03-15T10:00:00',
        'revisit_date' => null,
        'meta' => '{}',
    ];

    $data = array_merge($defaults, $overrides);

    $db->execute(
        'INSERT OR REPLACE INTO documents (id, title, domain, subdomain, status, priority, confidence, effort, summary, file_path, created_at, modified_at, revisit_date, meta)
         VALUES (:id, :title, :domain, :subdomain, :status, :priority, :confidence, :effort, :summary, :file_path, :created_at, :modified_at, :revisit_date, :meta)',
        [
            ':id' => $data['id'],
            ':title' => $data['title'],
            ':domain' => $data['domain'],
            ':subdomain' => $data['subdomain'],
            ':status' => $data['status'],
            ':priority' => $data['priority'],
            ':confidence' => $data['confidence'],
            ':effort' => $data['effort'],
            ':summary' => $data['summary'],
            ':file_path' => $data['file_path'],
            ':created_at' => $data['created_at'],
            ':modified_at' => $data['modified_at'],
            ':revisit_date' => $data['revisit_date'],
            ':meta' => $data['meta'],
        ],
    );

    return $data['id'];
}

/**
 * Seed a book document with meta JSON.
 */
function seedBook(Database $db, array $docOverrides = [], array $metaOverrides = []): string
{
    $meta = array_filter(array_merge([
        'author' => 'Test Author',
        'series_id' => null,
        'series_order' => null,
        'rating' => 'A',
        'cover_url' => null,
    ], $metaOverrides), fn($v) => $v !== null);

    $docDefaults = [
        'domain' => 'entertainment',
        'subdomain' => 'books',
        'title' => 'Test Book',
        'summary' => 'A test book',
        'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
    ];

    return seedDocument($db, array_merge($docDefaults, $docOverrides));
}

/**
 * Seed a movie document with meta JSON.
 */
function seedMovie(Database $db, array $docOverrides = [], array $metaOverrides = []): string
{
    $meta = array_filter(array_merge([
        'director' => 'Test Director',
        'year' => 2024,
        'genre' => null,
        'runtime_min' => null,
        'rating' => 'A',
        'poster_url' => null,
        'imdb_id' => null,
    ], $metaOverrides), fn($v) => $v !== null);

    $docDefaults = [
        'domain' => 'entertainment',
        'subdomain' => 'movies',
        'title' => 'Test Movie',
        'summary' => 'A test movie',
        'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
    ];

    return seedDocument($db, array_merge($docDefaults, $docOverrides));
}

/**
 * Seed a game document with meta JSON.
 */
function seedGame(Database $db, array $docOverrides = [], array $metaOverrides = []): string
{
    $meta = array_filter(array_merge([
        'developer' => 'Test Studio',
        'publisher' => null,
        'year' => 2024,
        'genre' => null,
        'platform' => 'PC',
        'hours_played' => null,
        'rating' => 'A',
        'cover_url' => null,
    ], $metaOverrides), fn($v) => $v !== null);

    $docDefaults = [
        'domain' => 'entertainment',
        'subdomain' => 'games',
        'title' => 'Test Game',
        'summary' => 'A test game',
        'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
    ];

    return seedDocument($db, array_merge($docDefaults, $docOverrides));
}

/**
 * Seed a TV show document with meta JSON.
 */
function seedTvShow(Database $db, array $docOverrides = [], array $tvOverrides = []): string
{
    $meta = array_filter(array_merge([
        'creator' => 'Test Creator',
        'year_start' => null,
        'year_end' => null,
        'genre' => null,
        'total_seasons' => null,
        'seasons_watched' => null,
        'rating' => null,
        'poster_url' => null,
        'imdb_id' => null,
    ], $tvOverrides), fn($v) => $v !== null);

    $docDefaults = [
        'domain' => 'entertainment',
        'subdomain' => 'tv',
        'title' => 'Test Show',
        'summary' => 'A test TV show',
        'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
    ];

    return seedDocument($db, array_merge($docDefaults, $docOverrides));
}

/**
 * Seed a media event (read, watch, play_session).
 */
function seedMediaEvent(Database $db, string $docId, string $type, string $date, array $meta = []): void
{
    $db->execute(
        'INSERT INTO media_events (doc_id, event_type, event_date, meta) VALUES (:doc_id, :type, :date, :meta)',
        [':doc_id' => $docId, ':type' => $type, ':date' => $date, ':meta' => json_encode($meta)],
    );
}

/**
 * Run a Symfony Console command and return the output + exit code.
 */
function runCommand(\Symfony\Component\Console\Command\Command $command, array $input = []): array
{
    $app = new \Symfony\Component\Console\Application();
    $app->addCommand($command);
    $app->setAutoExit(false);

    $inputArray = array_merge(['command' => $command->getName()], $input);
    $consoleInput = new \Symfony\Component\Console\Input\ArrayInput($inputArray);
    $output = new \Symfony\Component\Console\Output\BufferedOutput();

    $exitCode = $app->run($consoleInput, $output);

    return ['output' => $output->fetch(), 'code' => $exitCode];
}
