<?php

declare(strict_types=1);

namespace Vault;

use Symfony\Component\Yaml\Yaml;

final class FrontmatterParser
{
    /**
     * Entity-specific field names collected into meta for backward compatibility
     * with flat YAML format.
     */
    private const array ENTITY_FIELDS = [
        // Book
        'author', 'series', 'series_order', 'rating', 'cover_url',
        'serial', 'platform', 'serial_url', 'current_chapter', 'chapters_available',
        // Movie
        'director', 'year', 'genre', 'runtime_min', 'poster_url', 'imdb_id',
        // TV
        'creator', 'year_start', 'year_end', 'total_seasons', 'seasons_watched',
        // Game
        'developer', 'publisher', 'hours_played',
    ];

    /**
     * Parse YAML frontmatter from a markdown file.
     *
     * @return array{frontmatter: array<string, mixed>, body: string}|null
     */
    public function parse(string $filePath): ?array
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        // Match YAML between --- delimiters
        if (!preg_match('/\A---\n(.*?)\n---\n?(.*)\z/s', $content, $matches)) {
            return null;
        }

        $yaml = Yaml::parse($matches[1]);
        if (!is_array($yaml)) {
            return null;
        }

        return [
            'frontmatter' => $this->normalize($yaml),
            'body' => $matches[2],
        ];
    }

    /**
     * Normalize frontmatter values with sensible defaults.
     */
    private function normalize(array $data): array
    {
        $core = [
            'id' => $data['id'] ?? null,
            'title' => $data['title'] ?? null,
            'domain' => $data['domain'] ?? null,
            'subdomain' => $data['subdomain'] ?? null,
            'status' => $data['status'] ?? 'seed',
            'created' => $data['created'] ?? null,
            'modified' => $data['modified'] ?? null,
            'tags' => $data['tags'] ?? [],
            'priority' => $data['priority'] ?? 'p3-low',
            'confidence' => $data['confidence'] ?? 'speculative',
            'effort' => $data['effort'] ?? null,
            'summary' => $data['summary'] ?? null,
            'revisit_date' => $data['revisit_date'] ?? null,
            'links' => $data['links'] ?? [],
            'sources' => $data['sources'] ?? [],
            'todos' => $data['todos'] ?? [],
            'close_reason' => $data['close_reason'] ?? null,
            'external_refs' => $data['external_refs'] ?? [],
        ];

        // Meta: use meta key if present, otherwise collect flat entity fields
        if (isset($data['meta']) && is_array($data['meta'])) {
            $core['meta'] = $data['meta'];
        } else {
            $meta = [];
            foreach (self::ENTITY_FIELDS as $field) {
                if (array_key_exists($field, $data) && $data[$field] !== null) {
                    $meta[$field] = $data[$field];
                }
            }
            $core['meta'] = $meta;
        }

        // Events: normalize reads/watches/play_sessions into unified list
        $events = [];
        foreach ($data['reads'] ?? [] as $date) {
            $events[] = ['type' => 'read', 'date' => $date];
        }
        foreach ($data['watches'] ?? [] as $date) {
            $events[] = ['type' => 'watch', 'date' => $date];
        }
        foreach ($data['play_sessions'] ?? [] as $session) {
            if (is_array($session)) {
                $event = ['type' => 'play_session', 'date' => $session['date'] ?? null];
                if (isset($session['hours'])) {
                    $event['meta'] = ['hours' => $session['hours']];
                }
                $events[] = $event;
            } else {
                $events[] = ['type' => 'play_session', 'date' => $session];
            }
        }
        // Also support new format: events key directly
        foreach ($data['events'] ?? [] as $event) {
            $events[] = $event;
        }
        $core['events'] = $events;

        return $core;
    }
}
