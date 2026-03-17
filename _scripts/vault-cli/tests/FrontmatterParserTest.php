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
        ->and($result['frontmatter']['meta'])->toBe([])
        ->and($result['frontmatter']['events'])->toBe([])
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
        ->and($result['frontmatter']['todos'])->toBe([])
        ->and($result['frontmatter']['meta'])->toBe([])
        ->and($result['frontmatter']['events'])->toBe([]);
});

it('parses meta key from YAML directly', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'vault_fm_') . '.md';
    register_shutdown_function(fn () => @unlink($file));

    file_put_contents($file, <<<'MD'
---
id: "20260317-meta-test"
title: "Meta Test"
domain: entertainment
subdomain: books
meta:
  author: "Test Author"
  rating: S
  series_order: 1
---
Content.
MD);

    $parser = new FrontmatterParser();
    $result = $parser->parse($file);

    expect($result['frontmatter']['meta'])->toBe([
        'author' => 'Test Author',
        'rating' => 'S',
        'series_order' => 1,
    ]);
});

it('collects flat entity fields into meta for backward compatibility', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'vault_fm_') . '.md';
    register_shutdown_function(fn () => @unlink($file));

    file_put_contents($file, <<<'MD'
---
id: "20260317-compat-test"
title: "Old Format Book"
domain: entertainment
subdomain: books
author: "Old Author"
rating: A
cover_url: "https://example.com/cover.jpg"
serial: true
platform: "patreon"
---
Content.
MD);

    $parser = new FrontmatterParser();
    $result = $parser->parse($file);

    expect($result['frontmatter']['meta']['author'])->toBe('Old Author')
        ->and($result['frontmatter']['meta']['rating'])->toBe('A')
        ->and($result['frontmatter']['meta']['cover_url'])->toBe('https://example.com/cover.jpg')
        ->and($result['frontmatter']['meta']['serial'])->toBeTrue()
        ->and($result['frontmatter']['meta']['platform'])->toBe('patreon');
});

it('normalizes reads into events', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'vault_fm_') . '.md';
    register_shutdown_function(fn () => @unlink($file));

    file_put_contents($file, <<<'MD'
---
id: "20260317-reads-test"
title: "Book With Reads"
domain: entertainment
subdomain: books
reads:
  - "2026-01-15"
  - "2026-03-17"
---
Content.
MD);

    $parser = new FrontmatterParser();
    $result = $parser->parse($file);

    expect($result['frontmatter']['events'])->toHaveCount(2)
        ->and($result['frontmatter']['events'][0]['type'])->toBe('read')
        ->and($result['frontmatter']['events'][1]['type'])->toBe('read');
});

it('normalizes watches into events', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'vault_fm_') . '.md';
    register_shutdown_function(fn () => @unlink($file));

    file_put_contents($file, <<<'MD'
---
id: "20260317-watches-test"
title: "Movie With Watches"
domain: entertainment
subdomain: movies
watches:
  - "2026-03-17"
---
Content.
MD);

    $parser = new FrontmatterParser();
    $result = $parser->parse($file);

    expect($result['frontmatter']['events'])->toHaveCount(1)
        ->and($result['frontmatter']['events'][0]['type'])->toBe('watch');
});

it('normalizes play sessions into events with hours', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'vault_fm_') . '.md';
    register_shutdown_function(fn () => @unlink($file));

    file_put_contents($file, <<<'MD'
---
id: "20260317-play-test"
title: "Game With Sessions"
domain: entertainment
subdomain: games
play_sessions:
  - date: 2026-03-16
    hours: 3.5
  - date: 2026-03-17
    hours: 2.0
---
Content.
MD);

    $parser = new FrontmatterParser();
    $result = $parser->parse($file);

    expect($result['frontmatter']['events'])->toHaveCount(2)
        ->and($result['frontmatter']['events'][0]['type'])->toBe('play_session')
        ->and($result['frontmatter']['events'][0]['meta']['hours'])->toBe(3.5)
        ->and($result['frontmatter']['events'][1]['meta']['hours'])->toBe(2.0);
});

it('handles DateTime objects from YAML parser', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'vault_fm_') . '.md';
    register_shutdown_function(fn () => @unlink($file));

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

    $created = $result['frontmatter']['created'];
    $modified = $result['frontmatter']['modified'];

    expect($created)->not->toBeNull()
        ->and($modified)->not->toBeNull();

    if ($created instanceof DateTimeInterface) {
        expect($created->format('Y-m-d'))->toBe('2026-03-15');
    } elseif (is_int($created)) {
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
    $parser = new FrontmatterParser();
    $result = $parser->parse($file);
    expect($result['frontmatter']['close_reason'])->toBe('Merged into larger project');
    unlink($file);
});

it('parses external_refs list', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'vault_test_') . '.md';
    file_put_contents($file, "---\nid: \"test-er\"\ntitle: \"Test\"\ndomain: tech\nsubdomain: projects\nexternal_refs:\n  - url: \"https://github.com/example/repo\"\n    label: \"Repository\"\n  - url: \"https://example.com\"\n    label: \"Production\"\n---\nContent.\n");
    $parser = new FrontmatterParser();
    $result = $parser->parse($file);
    expect($result['frontmatter']['external_refs'])->toHaveCount(2)
        ->and($result['frontmatter']['external_refs'][0]['url'])->toBe('https://github.com/example/repo')
        ->and($result['frontmatter']['external_refs'][1]['label'])->toBe('Production');
    unlink($file);
});

it('defaults close_reason to null and external_refs to empty array', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'vault_test_') . '.md';
    file_put_contents($file, "---\nid: \"test-def\"\ntitle: \"No New Fields\"\ndomain: tech\nsubdomain: fleeting\n---\nContent.\n");
    $parser = new FrontmatterParser();
    $result = $parser->parse($file);
    expect($result['frontmatter']['close_reason'])->toBeNull()
        ->and($result['frontmatter']['external_refs'])->toBe([]);
    unlink($file);
});
