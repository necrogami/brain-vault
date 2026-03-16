<?php

declare(strict_types=1);

use Vault\FrontmatterParser;

it('parses valid frontmatter with all fields', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'vault_fm_') . '.md';
    register_shutdown_function(fn () => @unlink($file));

    file_put_contents($file, <<<'MD'
---
id: "20260315-100000-test-idea"
title: "Test Idea"
domain: tech
subdomain: fleeting
status: growing
created: 2026-03-15T10:00:00
modified: 2026-03-15T12:00:00
tags: [ai, testing]
priority: p2-medium
confidence: medium
effort: small
summary: "A test idea for parsing"
revisit_date: 2026-04-01
links:
  parent: []
  children: []
  related: ["other-doc"]
sources:
  - url: "https://example.com"
    title: "Example"
    accessed: 2026-03-15
todos:
  - task: "Write tests"
    due: 2026-03-20
    priority: p1-high
    status: open
---
Body content goes here.
MD);

    $parser = new FrontmatterParser();
    $result = $parser->parse($file);

    expect($result)->not->toBeNull()
        ->and($result['frontmatter']['id'])->toBe('20260315-100000-test-idea')
        ->and($result['frontmatter']['title'])->toBe('Test Idea')
        ->and($result['frontmatter']['domain'])->toBe('tech')
        ->and($result['frontmatter']['subdomain'])->toBe('fleeting')
        ->and($result['frontmatter']['status'])->toBe('growing')
        ->and($result['frontmatter']['tags'])->toBe(['ai', 'testing'])
        ->and($result['frontmatter']['priority'])->toBe('p2-medium')
        ->and($result['frontmatter']['confidence'])->toBe('medium')
        ->and($result['frontmatter']['effort'])->toBe('small')
        ->and($result['frontmatter']['summary'])->toBe('A test idea for parsing')
        ->and($result['frontmatter']['links']['related'])->toBe(['other-doc'])
        ->and($result['frontmatter']['sources'])->toHaveCount(1)
        ->and($result['frontmatter']['todos'])->toHaveCount(1)
        ->and($result['body'])->toContain('Body content goes here.');
});

it('returns null for files without frontmatter', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'vault_fm_') . '.md';
    register_shutdown_function(fn () => @unlink($file));

    file_put_contents($file, "Just plain markdown content.\nNo frontmatter here.");

    $parser = new FrontmatterParser();
    $result = $parser->parse($file);

    expect($result)->toBeNull();
});

it('returns null for non-existent files', function (): void {
    $parser = new FrontmatterParser();
    $result = $parser->parse('/tmp/nonexistent_vault_file_' . bin2hex(random_bytes(8)) . '.md');

    expect($result)->toBeNull();
});

it('normalizes missing fields with defaults', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'vault_fm_') . '.md';
    register_shutdown_function(fn () => @unlink($file));

    file_put_contents($file, <<<'MD'
---
id: "20260315-minimal"
title: "Minimal Doc"
domain: tech
subdomain: fleeting
---
Minimal body.
MD);

    $parser = new FrontmatterParser();
    $result = $parser->parse($file);

    expect($result)->not->toBeNull()
        ->and($result['frontmatter']['status'])->toBe('seed')
        ->and($result['frontmatter']['priority'])->toBe('p3-low')
        ->and($result['frontmatter']['confidence'])->toBe('speculative')
        ->and($result['frontmatter']['effort'])->toBeNull()
        ->and($result['frontmatter']['summary'])->toBeNull()
        ->and($result['frontmatter']['revisit_date'])->toBeNull()
        ->and($result['frontmatter']['tags'])->toBe([])
        ->and($result['frontmatter']['links'])->toBe([])
        ->and($result['frontmatter']['sources'])->toBe([])
        ->and($result['frontmatter']['todos'])->toBe([]);
});

it('handles book-specific fields', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'vault_fm_') . '.md';
    register_shutdown_function(fn () => @unlink($file));

    file_put_contents($file, <<<'MD'
---
id: "20260315-book-test"
title: "A Great Book"
domain: personal
subdomain: books
author: "Jane Author"
rating: A
reads:
  - date: 2026-01-15
  - date: 2026-03-10
series: "The Series Name"
series_order: 2
cover_url: "https://example.com/cover.jpg"
---
Book notes here.
MD);

    $parser = new FrontmatterParser();
    $result = $parser->parse($file);

    expect($result)->not->toBeNull()
        ->and($result['frontmatter']['author'])->toBe('Jane Author')
        ->and($result['frontmatter']['rating'])->toBe('A')
        ->and($result['frontmatter']['reads'])->toHaveCount(2)
        ->and($result['frontmatter']['series'])->toBe('The Series Name')
        ->and($result['frontmatter']['series_order'])->toBe(2)
        ->and($result['frontmatter']['cover_url'])->toBe('https://example.com/cover.jpg');
});

it('handles book-specific fields defaulting to null when absent', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'vault_fm_') . '.md';
    register_shutdown_function(fn () => @unlink($file));

    file_put_contents($file, <<<'MD'
---
id: "20260315-no-book"
title: "Not a Book"
domain: tech
subdomain: fleeting
---
Regular content.
MD);

    $parser = new FrontmatterParser();
    $result = $parser->parse($file);

    expect($result)->not->toBeNull()
        ->and($result['frontmatter']['author'])->toBeNull()
        ->and($result['frontmatter']['rating'])->toBeNull()
        ->and($result['frontmatter']['reads'])->toBe([])
        ->and($result['frontmatter']['series'])->toBeNull()
        ->and($result['frontmatter']['series_order'])->toBeNull()
        ->and($result['frontmatter']['cover_url'])->toBeNull();
});

it('handles DateTime objects from YAML parser', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'vault_fm_') . '.md';
    register_shutdown_function(fn () => @unlink($file));

    // Symfony YAML parser converts unquoted ISO datetime strings to DateTime objects
    file_put_contents($file, <<<'MD'
---
id: "20260315-datetime"
title: "DateTime Test"
domain: tech
subdomain: fleeting
created: 2026-03-15T10:00:00
modified: 2026-03-15T12:30:00
---
Content.
MD);

    $parser = new FrontmatterParser();
    $result = $parser->parse($file);

    expect($result)->not->toBeNull();

    // The YAML parser may return DateTime objects for unquoted datetime values
    $created = $result['frontmatter']['created'];
    $modified = $result['frontmatter']['modified'];

    // Symfony YAML parses unquoted ISO datetimes as Unix timestamps (int)
    // or as DateTime objects depending on version. Either is acceptable.
    expect($created)->not->toBeNull()
        ->and($modified)->not->toBeNull();

    if ($created instanceof DateTimeInterface) {
        expect($created->format('Y-m-d'))->toBe('2026-03-15');
    } elseif (is_int($created)) {
        // Unix timestamp — verify it converts to the right date
        expect(date('Y-m-d', $created))->toBe('2026-03-15');
    } else {
        expect(str_contains((string) $created, '2026-03-15'))->toBeTrue();
    }

    if ($modified instanceof DateTimeInterface) {
        expect($modified->format('Y-m-d'))->toBe('2026-03-15');
    } elseif (is_int($modified)) {
        expect(date('Y-m-d', $modified))->toBe('2026-03-15');
    } else {
        expect(str_contains((string) $modified, '2026-03-15'))->toBeTrue();
    }
});

it('parses close_reason field', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'vault_test_') . '.md';
    file_put_contents($file, "---\nid: \"test-cr\"\ntitle: \"Test\"\ndomain: tech\nsubdomain: fleeting\nclose_reason: \"Merged into larger project\"\n---\nContent.\n");
    $parser = new \Vault\FrontmatterParser();
    $result = $parser->parse($file);
    expect($result['frontmatter']['close_reason'])->toBe('Merged into larger project');
    unlink($file);
});

it('parses external_refs list', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'vault_test_') . '.md';
    file_put_contents($file, "---\nid: \"test-er\"\ntitle: \"Test\"\ndomain: tech\nsubdomain: projects\nexternal_refs:\n  - url: \"https://github.com/example/repo\"\n    label: \"Repository\"\n  - url: \"https://example.com\"\n    label: \"Production\"\n---\nContent.\n");
    $parser = new \Vault\FrontmatterParser();
    $result = $parser->parse($file);
    expect($result['frontmatter']['external_refs'])->toHaveCount(2)
        ->and($result['frontmatter']['external_refs'][0]['url'])->toBe('https://github.com/example/repo')
        ->and($result['frontmatter']['external_refs'][1]['label'])->toBe('Production');
    unlink($file);
});

it('defaults close_reason to null and external_refs to empty array', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'vault_test_') . '.md';
    file_put_contents($file, "---\nid: \"test-def\"\ntitle: \"No New Fields\"\ndomain: tech\nsubdomain: fleeting\n---\nContent.\n");
    $parser = new \Vault\FrontmatterParser();
    $result = $parser->parse($file);
    expect($result['frontmatter']['close_reason'])->toBeNull()
        ->and($result['frontmatter']['external_refs'])->toBe([]);
    unlink($file);
});
