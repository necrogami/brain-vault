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
    ];

    $data = array_merge($defaults, $overrides);

    $db->execute(
        'INSERT OR REPLACE INTO documents (id, title, domain, subdomain, status, priority, confidence, effort, summary, file_path, created_at, modified_at, revisit_date)
         VALUES (:id, :title, :domain, :subdomain, :status, :priority, :confidence, :effort, :summary, :file_path, :created_at, :modified_at, :revisit_date)',
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
        ],
    );

    return $data['id'];
}

/**
 * Seed a book document + books table entry.
 */
function seedBook(Database $db, array $docOverrides = [], array $bookOverrides = []): string
{
    $docDefaults = [
        'domain' => 'personal',
        'subdomain' => 'books',
        'title' => 'Test Book',
        'summary' => 'A test book',
    ];

    $id = seedDocument($db, array_merge($docDefaults, $docOverrides));

    $bookDefaults = [
        'doc_id' => $id,
        'author' => 'Test Author',
        'series_id' => null,
        'series_order' => null,
        'rating' => 'A',
        'cover_url' => null,
    ];

    $book = array_merge($bookDefaults, $bookOverrides);

    $db->execute(
        'INSERT OR REPLACE INTO books (doc_id, author, series_id, series_order, rating, cover_url)
         VALUES (:doc_id, :author, :series_id, :series_order, :rating, :cover_url)',
        [
            ':doc_id' => $book['doc_id'],
            ':author' => $book['author'],
            ':series_id' => $book['series_id'],
            ':series_order' => $book['series_order'],
            ':rating' => $book['rating'],
            ':cover_url' => $book['cover_url'],
        ],
    );

    return $id;
}

/**
 * Seed a movie document + movies table entry.
 */
function seedMovie(Database $db, array $docOverrides = [], array $movieOverrides = []): string
{
    $docDefaults = [
        'domain' => 'personal',
        'subdomain' => 'movies',
        'title' => 'Test Movie',
        'summary' => 'A test movie',
    ];

    $id = seedDocument($db, array_merge($docDefaults, $docOverrides));

    $movieDefaults = [
        'doc_id' => $id,
        'director' => 'Test Director',
        'year' => 2024,
        'genre' => null,
        'runtime_min' => null,
        'rating' => 'A',
        'poster_url' => null,
        'imdb_id' => null,
    ];

    $movie = array_merge($movieDefaults, $movieOverrides);

    $db->execute(
        'INSERT OR REPLACE INTO movies (doc_id, director, year, genre, runtime_min, rating, poster_url, imdb_id)
         VALUES (:doc_id, :director, :year, :genre, :runtime_min, :rating, :poster_url, :imdb_id)',
        [
            ':doc_id' => $movie['doc_id'],
            ':director' => $movie['director'],
            ':year' => $movie['year'],
            ':genre' => $movie['genre'],
            ':runtime_min' => $movie['runtime_min'],
            ':rating' => $movie['rating'],
            ':poster_url' => $movie['poster_url'],
            ':imdb_id' => $movie['imdb_id'],
        ],
    );

    return $id;
}

/**
 * Seed a game document + games table entry.
 */
function seedGame(Database $db, array $docOverrides = [], array $gameOverrides = []): string
{
    $docDefaults = [
        'domain' => 'personal',
        'subdomain' => 'games',
        'title' => 'Test Game',
        'summary' => 'A test game',
    ];

    $id = seedDocument($db, array_merge($docDefaults, $docOverrides));

    $gameDefaults = [
        'doc_id' => $id,
        'developer' => 'Test Studio',
        'publisher' => null,
        'year' => 2024,
        'genre' => null,
        'platform' => 'PC',
        'hours_played' => null,
        'rating' => 'A',
        'cover_url' => null,
    ];

    $game = array_merge($gameDefaults, $gameOverrides);

    $db->execute(
        'INSERT OR REPLACE INTO games (doc_id, developer, publisher, year, genre, platform, hours_played, rating, cover_url)
         VALUES (:doc_id, :developer, :publisher, :year, :genre, :platform, :hours_played, :rating, :cover_url)',
        [
            ':doc_id' => $game['doc_id'],
            ':developer' => $game['developer'],
            ':publisher' => $game['publisher'],
            ':year' => $game['year'],
            ':genre' => $game['genre'],
            ':platform' => $game['platform'],
            ':hours_played' => $game['hours_played'],
            ':rating' => $game['rating'],
            ':cover_url' => $game['cover_url'],
        ],
    );

    return $id;
}

/**
 * Seed a TV show document + tv_shows table entry.
 */
function seedTvShow(Database $db, array $docOverrides = [], array $tvOverrides = []): string
{
    $docDefaults = [
        'domain' => 'personal',
        'subdomain' => 'tv',
        'title' => 'Test Show',
        'summary' => 'A test TV show',
    ];

    $id = seedDocument($db, array_merge($docDefaults, $docOverrides));

    $tvDefaults = [
        'doc_id' => $id,
        'creator' => 'Test Creator',
        'year_start' => null,
        'year_end' => null,
        'genre' => null,
        'total_seasons' => null,
        'seasons_watched' => null,
        'rating' => null,
        'poster_url' => null,
        'imdb_id' => null,
    ];

    $tv = array_merge($tvDefaults, $tvOverrides);

    $db->execute(
        'INSERT OR REPLACE INTO tv_shows (doc_id, creator, year_start, year_end, genre, total_seasons, seasons_watched, rating, poster_url, imdb_id)
         VALUES (:doc_id, :creator, :year_start, :year_end, :genre, :total_seasons, :seasons_watched, :rating, :poster_url, :imdb_id)',
        [
            ':doc_id' => $tv['doc_id'],
            ':creator' => $tv['creator'],
            ':year_start' => $tv['year_start'],
            ':year_end' => $tv['year_end'],
            ':genre' => $tv['genre'],
            ':total_seasons' => $tv['total_seasons'],
            ':seasons_watched' => $tv['seasons_watched'],
            ':rating' => $tv['rating'],
            ':poster_url' => $tv['poster_url'],
            ':imdb_id' => $tv['imdb_id'],
        ],
    );

    return $id;
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
