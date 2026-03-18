<?php

declare(strict_types=1);

namespace Vault;

/**
 * Indexes a single parsed document into the SQLite database.
 *
 * Accepts normalized frontmatter (as returned by FrontmatterParser::parse())
 * and writes/replaces all associated rows: documents, tags, links, sources,
 * todos, external_refs, and media_events.
 */
final class DocumentIndexer
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

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Index a single document: delete old records then insert fresh.
     *
     * @param array<string, mixed> $fm           Normalized frontmatter
     * @param string               $relativePath Path relative to project root
     */
    public function index(array $fm, string $relativePath): void
    {
        $id = $fm['id'];

        $this->deleteExisting($id);
        $this->insertDocument($fm, $relativePath);
        $this->insertTags($fm);
        $this->insertLinks($fm);
        $this->insertMediaEvents($fm);
        $this->insertSources($fm);
        $this->insertTodos($fm);
        $this->insertExternalRefs($fm);
    }

    public function deleteExisting(string $id): void
    {
        $tables = ['tags', 'links', 'sources', 'todos', 'external_refs', 'media_events'];

        foreach ($tables as $table) {
            $col = $table === 'links' ? 'source_id' : 'doc_id';
            $this->db->execute("DELETE FROM {$table} WHERE {$col} = :id", [':id' => $id]);
        }

        $this->db->execute('DELETE FROM documents WHERE id = :id', [':id' => $id]);
    }

    /**
     * @param array<string, mixed> $fm
     */
    private function insertDocument(array $fm, string $relativePath): void
    {
        $meta = $fm['meta'] ?? [];
        $metaJson = json_encode($meta, JSON_THROW_ON_ERROR);

        $this->db->execute(
            <<<'SQL'
                INSERT OR REPLACE INTO documents
                    (id, title, domain, subdomain, status, priority, confidence, effort, summary, file_path, created_at, modified_at, revisit_date, close_reason, meta)
                VALUES
                    (:id, :title, :domain, :subdomain, :status, :priority, :confidence, :effort, :summary, :file_path, :created_at, :modified_at, :revisit_date, :close_reason, :meta)
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
                ':meta' => $metaJson,
            ],
        );
    }

    /**
     * @param array<string, mixed> $fm
     */
    private function insertTags(array $fm): void
    {
        foreach ($fm['tags'] ?? [] as $tag) {
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
    private function insertMediaEvents(array $fm): void
    {
        foreach ($fm['events'] ?? [] as $event) {
            $eventDate = $this->formatDatetime($event['date'] ?? null);

            if ($eventDate === null) {
                continue;
            }

            $eventMeta = isset($event['meta']) ? json_encode($event['meta']) : '{}';

            $this->db->execute(
                'INSERT INTO media_events (doc_id, event_type, event_date, meta) VALUES (:doc_id, :type, :date, :meta)',
                [
                    ':doc_id' => $fm['id'],
                    ':type' => $event['type'],
                    ':date' => $eventDate,
                    ':meta' => $eventMeta,
                ],
            );
        }
    }

    /**
     * @param array<string, mixed> $fm
     */
    private function insertSources(array $fm): void
    {
        foreach ($fm['sources'] ?? [] as $source) {
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
        foreach ($fm['todos'] ?? [] as $todo) {
            if (!is_array($todo)) {
                continue;
            }

            $content = $todo['task'] ?? null;

            if ($content === null || $content === '') {
                continue;
            }

            $this->db->execute(
                'INSERT INTO todos (doc_id, content, due_date, status, priority) VALUES (:doc_id, :content, :due_date, :status, :priority)',
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
        foreach ($fm['external_refs'] ?? [] as $ref) {
            if (!is_array($ref) || empty($ref['url'])) {
                continue;
            }

            $this->db->execute(
                'INSERT INTO external_refs (doc_id, url, label) VALUES (:doc_id, :url, :label)',
                [':doc_id' => $fm['id'], ':url' => $ref['url'], ':label' => $ref['label'] ?? null],
            );
        }
    }

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
