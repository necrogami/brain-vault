<?php

declare(strict_types=1);

namespace Vault;

use Symfony\Component\Yaml\Yaml;

final class FrontmatterParser
{
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
        return [
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
            // Book-specific
            'author' => $data['author'] ?? null,
            'series' => $data['series'] ?? null,
            'series_order' => $data['series_order'] ?? null,
            'rating' => $data['rating'] ?? null,
            'cover_url' => $data['cover_url'] ?? null,
            'reads' => $data['reads'] ?? [],
            // Close/completion tracking
            'close_reason' => $data['close_reason'] ?? null,
            'external_refs' => $data['external_refs'] ?? [],
        ];
    }
}
