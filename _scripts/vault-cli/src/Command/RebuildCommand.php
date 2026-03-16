<?php

declare(strict_types=1);

namespace Vault\Command;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;
use Vault\FrontmatterParser;

#[AsCommand(name: 'rebuild', description: 'Drop and rebuild the SQLite index from all vault markdown files')]
final class RebuildCommand extends Command
{
    private const array LINK_TYPES = [
        'parent',
        'children',
        'related',
        'inspired_by',
        'blocks',
        'blocked_by',
        'evolved_into',
    ];

    public function __construct(
        private readonly Database $db,
        private readonly string $projectRoot,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schemaPath = $this->projectRoot . '/_index/schema.sql';

        if (!file_exists($schemaPath)) {
            $output->writeln('<error>Schema file not found: ' . $schemaPath . '</error>');

            return Command::FAILURE;
        }

        $schemaSql = file_get_contents($schemaPath);

        if ($schemaSql === false) {
            $output->writeln('<error>Failed to read schema file.</error>');

            return Command::FAILURE;
        }

        // Drop and recreate all tables
        $output->writeln('<info>Dropping and recreating tables...</info>');
        $this->db->executeRaw($schemaSql);

        // Discover all markdown files under vault/
        $files = $this->discoverMarkdownFiles();
        $fileCount = count($files);

        if ($fileCount === 0) {
            $output->writeln('<comment>No markdown files found under vault/.</comment>');

            return Command::SUCCESS;
        }

        $output->writeln("<info>Found {$fileCount} markdown file(s). Indexing...</info>");

        $parser = new FrontmatterParser();
        $processed = 0;
        /** @var list<string> $errors */
        $errors = [];

        $progressBar = new ProgressBar($output, $fileCount);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s% %message%');
        $progressBar->setMessage('Starting...');
        $progressBar->start();

        $this->db->beginTransaction();

        try {
            foreach ($files as $filePath) {
                $relativePath = $this->relativePath($filePath);
                $progressBar->setMessage($relativePath);

                $parsed = $parser->parse($filePath);

                if ($parsed === null) {
                    $errors[] = $relativePath;
                    $progressBar->advance();

                    continue;
                }

                $fm = $parsed['frontmatter'];

                if ($fm['id'] === null || $fm['title'] === null || $fm['domain'] === null || $fm['subdomain'] === null) {
                    $errors[] = $relativePath . ' (missing required fields)';
                    $progressBar->advance();

                    continue;
                }

                $this->insertDocument($fm, $relativePath);
                $this->insertTags($fm);
                $this->insertLinks($fm);
                $this->insertBook($fm);
                $this->insertReads($fm);
                $this->insertMovie($fm);
                $this->insertWatches($fm);
                $this->insertTvShow($fm);
                $this->insertGame($fm);
                $this->insertPlaySessions($fm);
                $this->insertSources($fm);
                $this->insertTodos($fm);
                $this->insertExternalRefs($fm);

                $processed++;
                $progressBar->advance();
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            $progressBar->finish();
            $output->writeln('');
            $output->writeln('<error>Rebuild failed: ' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $progressBar->setMessage('Done.');
        $progressBar->finish();
        $output->writeln('');
        $output->writeln('');

        // Report results
        $output->writeln('<info>Rebuild complete.</info>');
        $output->writeln("  Files processed: {$processed}");

        if ($errors !== []) {
            $output->writeln('  <comment>Errors: ' . count($errors) . '</comment>');

            foreach ($errors as $error) {
                $output->writeln("    - {$error}");
            }
        } else {
            $output->writeln('  Errors: 0');
        }

        $orphanCount = $this->countOrphans();
        $output->writeln("  Orphaned ideas (>7 days, no links): {$orphanCount}");

        return Command::SUCCESS;
    }

    /**
     * @return list<string> Absolute paths to .md files under vault/
     */
    private function discoverMarkdownFiles(): array
    {
        $vaultDir = $this->projectRoot . '/vault';

        if (!is_dir($vaultDir)) {
            return [];
        }

        $directory = new RecursiveDirectoryIterator(
            $vaultDir,
            RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS,
        );
        $iterator = new RecursiveIteratorIterator($directory);
        $regex = new RegexIterator($iterator, '/\.md$/i');

        $files = [];

        foreach ($regex as $file) {
            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    private function relativePath(string $absolutePath): string
    {
        return ltrim(str_replace($this->projectRoot, '', $absolutePath), '/');
    }

    /**
     * @param array<string, mixed> $fm
     */
    private function insertDocument(array $fm, string $relativePath): void
    {
        $this->db->execute(
            <<<'SQL'
                INSERT OR REPLACE INTO documents
                    (id, title, domain, subdomain, status, priority, confidence, effort, summary, file_path, created_at, modified_at, revisit_date, close_reason)
                VALUES
                    (:id, :title, :domain, :subdomain, :status, :priority, :confidence, :effort, :summary, :file_path, :created_at, :modified_at, :revisit_date, :close_reason)
            SQL,
            [
                ':id' => $fm['id'],
                ':title' => $fm['title'],
                ':domain' => $fm['domain'],
                ':subdomain' => $fm['subdomain'],
                ':status' => $fm['status'] ?? 'seed',
                ':priority' => $fm['priority'] ?? 'p3-low',
                ':confidence' => $fm['confidence'] ?? 'speculative',
                ':effort' => $fm['effort'],
                ':summary' => $fm['summary'],
                ':file_path' => $relativePath,
                ':created_at' => $this->formatDatetime($fm['created']),
                ':modified_at' => $this->formatDatetime($fm['modified']),
                ':revisit_date' => $this->formatDatetime($fm['revisit_date']),
                ':close_reason' => $fm['close_reason'] ?? null,
            ],
        );
    }

    /**
     * @param array<string, mixed> $fm
     */
    private function insertTags(array $fm): void
    {
        $tags = $fm['tags'] ?? [];

        if (!is_array($tags)) {
            return;
        }

        foreach ($tags as $tag) {
            $this->db->execute(
                'INSERT INTO tags (doc_id, tag) VALUES (:doc_id, :tag)',
                [':doc_id' => $fm['id'], ':tag' => (string) $tag],
            );
        }
    }

    /**
     * @param array<string, mixed> $fm
     */
    private function insertLinks(array $fm): void
    {
        $links = $fm['links'] ?? [];

        if (!is_array($links)) {
            return;
        }

        foreach (self::LINK_TYPES as $linkType) {
            $targets = $links[$linkType] ?? [];

            if (!is_array($targets)) {
                continue;
            }

            foreach ($targets as $targetId) {
                if ($targetId === null || $targetId === '') {
                    continue;
                }

                $this->db->execute(
                    'INSERT OR IGNORE INTO links (source_id, target_id, link_type) VALUES (:source_id, :target_id, :link_type)',
                    [
                        ':source_id' => $fm['id'],
                        ':target_id' => (string) $targetId,
                        ':link_type' => $linkType,
                    ],
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $fm
     */
    private function insertBook(array $fm): void
    {
        if (($fm['subdomain'] ?? null) !== 'books') {
            return;
        }

        if ($fm['author'] === null) {
            return;
        }

        $this->db->execute(
            <<<'SQL'
                INSERT OR REPLACE INTO books
                    (doc_id, author, series_id, series_order, rating, cover_url)
                VALUES
                    (:doc_id, :author, :series_id, :series_order, :rating, :cover_url)
            SQL,
            [
                ':doc_id' => $fm['id'],
                ':author' => $fm['author'],
                ':series_id' => $fm['series'],
                ':series_order' => $fm['series_order'],
                ':rating' => $fm['rating'],
                ':cover_url' => $fm['cover_url'],
            ],
        );
    }

    /**
     * @param array<string, mixed> $fm
     */
    private function insertReads(array $fm): void
    {
        $reads = $fm['reads'] ?? [];

        if (!is_array($reads) || $reads === []) {
            return;
        }

        foreach ($reads as $dateRead) {
            $this->db->execute(
                'INSERT INTO reads (doc_id, date_read) VALUES (:doc_id, :date_read)',
                [
                    ':doc_id' => $fm['id'],
                    ':date_read' => $this->formatDatetime($dateRead),
                ],
            );
        }
    }

    /**
     * @param array<string, mixed> $fm
     */
    private function insertMovie(array $fm): void
    {
        if (($fm['subdomain'] ?? null) !== 'movies') {
            return;
        }

        if ($fm['director'] === null && $fm['imdb_id'] === null) {
            return;
        }

        $this->db->execute(
            <<<'SQL'
                INSERT OR REPLACE INTO movies
                    (doc_id, director, year, genre, runtime_min, rating, poster_url, imdb_id)
                VALUES
                    (:doc_id, :director, :year, :genre, :runtime_min, :rating, :poster_url, :imdb_id)
            SQL,
            [
                ':doc_id' => $fm['id'],
                ':director' => $fm['director'],
                ':year' => $fm['year'],
                ':genre' => $fm['genre'],
                ':runtime_min' => $fm['runtime_min'],
                ':rating' => $fm['rating'],
                ':poster_url' => $fm['poster_url'],
                ':imdb_id' => $fm['imdb_id'],
            ],
        );
    }

    /**
     * @param array<string, mixed> $fm
     */
    private function insertWatches(array $fm): void
    {
        $watches = $fm['watches'] ?? [];

        if (!is_array($watches) || $watches === []) {
            return;
        }

        foreach ($watches as $dateWatched) {
            $this->db->execute(
                'INSERT INTO watches (doc_id, date_watched) VALUES (:doc_id, :date_watched)',
                [
                    ':doc_id' => $fm['id'],
                    ':date_watched' => $this->formatDatetime($dateWatched),
                ],
            );
        }
    }

    /**
     * @param array<string, mixed> $fm
     */
    private function insertTvShow(array $fm): void
    {
        if (($fm['subdomain'] ?? null) !== 'tv') {
            return;
        }

        if ($fm['creator'] === null && $fm['imdb_id'] === null) {
            return;
        }

        $this->db->execute(
            <<<'SQL'
                INSERT OR REPLACE INTO tv_shows
                    (doc_id, creator, year_start, year_end, genre, total_seasons, seasons_watched, rating, poster_url, imdb_id)
                VALUES
                    (:doc_id, :creator, :year_start, :year_end, :genre, :total_seasons, :seasons_watched, :rating, :poster_url, :imdb_id)
            SQL,
            [
                ':doc_id' => $fm['id'],
                ':creator' => $fm['creator'],
                ':year_start' => $fm['year_start'],
                ':year_end' => $fm['year_end'],
                ':genre' => $fm['genre'],
                ':total_seasons' => $fm['total_seasons'],
                ':seasons_watched' => $fm['seasons_watched'],
                ':rating' => $fm['rating'],
                ':poster_url' => $fm['poster_url'],
                ':imdb_id' => $fm['imdb_id'],
            ],
        );
    }

    /**
     * @param array<string, mixed> $fm
     */
    private function insertGame(array $fm): void
    {
        if (($fm['subdomain'] ?? null) !== 'games') {
            return;
        }

        if ($fm['developer'] === null) {
            return;
        }

        $this->db->execute(
            <<<'SQL'
                INSERT OR REPLACE INTO games
                    (doc_id, developer, publisher, year, genre, platform, hours_played, rating, cover_url)
                VALUES
                    (:doc_id, :developer, :publisher, :year, :genre, :platform, :hours_played, :rating, :cover_url)
            SQL,
            [
                ':doc_id' => $fm['id'],
                ':developer' => $fm['developer'],
                ':publisher' => $fm['publisher'],
                ':year' => $fm['year'],
                ':genre' => $fm['genre'],
                ':platform' => $fm['platform'],
                ':hours_played' => $fm['hours_played'],
                ':rating' => $fm['rating'],
                ':cover_url' => $fm['cover_url'],
            ],
        );
    }

    /**
     * @param array<string, mixed> $fm
     */
    private function insertPlaySessions(array $fm): void
    {
        $sessions = $fm['play_sessions'] ?? [];

        if (!is_array($sessions) || $sessions === []) {
            return;
        }

        foreach ($sessions as $session) {
            if (is_array($session)) {
                $this->db->execute(
                    'INSERT INTO play_sessions (doc_id, date_played, hours) VALUES (:doc_id, :date_played, :hours)',
                    [
                        ':doc_id' => $fm['id'],
                        ':date_played' => $this->formatDatetime($session['date'] ?? null),
                        ':hours' => $session['hours'] ?? null,
                    ],
                );
            } else {
                $this->db->execute(
                    'INSERT INTO play_sessions (doc_id, date_played) VALUES (:doc_id, :date_played)',
                    [
                        ':doc_id' => $fm['id'],
                        ':date_played' => $this->formatDatetime($session),
                    ],
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $fm
     */
    private function insertSources(array $fm): void
    {
        $sources = $fm['sources'] ?? [];

        if (!is_array($sources)) {
            return;
        }

        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }

            $this->db->execute(
                'INSERT INTO sources (doc_id, url, title, accessed_date) VALUES (:doc_id, :url, :title, :accessed_date)',
                [
                    ':doc_id' => $fm['id'],
                    ':url' => $source['url'] ?? null,
                    ':title' => $source['title'] ?? null,
                    ':accessed_date' => $this->formatDatetime($source['accessed'] ?? null),
                ],
            );
        }
    }

    /**
     * @param array<string, mixed> $fm
     */
    private function insertTodos(array $fm): void
    {
        $todos = $fm['todos'] ?? [];

        if (!is_array($todos)) {
            return;
        }

        foreach ($todos as $todo) {
            if (!is_array($todo)) {
                continue;
            }

            $content = $todo['task'] ?? null;

            if ($content === null || $content === '') {
                continue;
            }

            $this->db->execute(
                <<<'SQL'
                    INSERT INTO todos (doc_id, content, due_date, status, priority)
                    VALUES (:doc_id, :content, :due_date, :status, :priority)
                SQL,
                [
                    ':doc_id' => $fm['id'],
                    ':content' => (string) $content,
                    ':due_date' => $this->formatDatetime($todo['due'] ?? null),
                    ':status' => $todo['status'] ?? 'open',
                    ':priority' => $todo['priority'] ?? 'p3-low',
                ],
            );
        }
    }

    /**
     * @param array<string, mixed> $fm
     */
    private function insertExternalRefs(array $fm): void
    {
        $refs = $fm['external_refs'] ?? [];
        if (!is_array($refs)) {
            return;
        }
        foreach ($refs as $ref) {
            if (!is_array($ref) || empty($ref['url'])) {
                continue;
            }
            $this->db->execute(
                'INSERT INTO external_refs (doc_id, url, label) VALUES (:doc_id, :url, :label)',
                [':doc_id' => $fm['id'], ':url' => $ref['url'], ':label' => $ref['label'] ?? null],
            );
        }
    }

    private function countOrphans(): int
    {
        return (int) $this->db->fetchValue(
            <<<'SQL'
                SELECT COUNT(*) FROM documents d
                WHERE d.status NOT IN ('closed', 'migrated', 'built')
                  AND d.created_at < datetime('now', '-7 days')
                  AND NOT EXISTS (
                      SELECT 1 FROM links l
                      WHERE l.source_id = d.id OR l.target_id = d.id
                  )
            SQL,
        );
    }

    /**
     * Convert a value that may be a DateTimeInterface, string, or null to a string.
     * Symfony YAML parser converts ISO date strings to DateTime objects.
     */
    private function formatDatetime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('c');
        }

        if (is_int($value)) {
            return date('c', $value);
        }

        return (string) $value;
    }
}
