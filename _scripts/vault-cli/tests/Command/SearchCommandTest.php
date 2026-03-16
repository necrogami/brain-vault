<?php

declare(strict_types=1);

use Vault\Command\SearchCommand;

it('finds documents by full-text search', function (): void {
    $db = freshDb();

    seedDocument($db, [
        'id' => 'fts-doc-1',
        'title' => 'Machine Learning Research',
        'summary' => 'Deep dive into neural networks',
        'domain' => 'tech',
        'subdomain' => 'research',
    ]);
    seedDocument($db, [
        'id' => 'fts-doc-2',
        'title' => 'Cooking Recipes',
        'summary' => 'Italian pasta collection',
        'domain' => 'personal',
        'subdomain' => 'fleeting',
    ]);

    $result = runCommand(new SearchCommand($db), ['query' => 'neural']);

    expect($result['output'])->toContain('Machine Learning Research')
        ->and($result['output'])->toContain('fts-doc-1')
        ->and($result['output'])->not->toContain('Cooking Recipes');
});

it('finds documents by tag match', function (): void {
    $db = freshDb();

    $docId = seedDocument($db, [
        'id' => 'tagged-doc',
        'title' => 'Tagged Document',
    ]);
    $db->execute(
        'INSERT INTO tags (doc_id, tag) VALUES (:doc_id, :tag)',
        [':doc_id' => $docId, ':tag' => 'robotics'],
    );

    $result = runCommand(new SearchCommand($db), ['query' => 'robotics']);

    expect($result['output'])->toContain('Tag Search Results')
        ->and($result['output'])->toContain('Tagged Document')
        ->and($result['output'])->toContain('robotics');
});

it('shows no results for unmatched queries', function (): void {
    $db = freshDb();

    seedDocument($db, ['id' => 'some-doc', 'title' => 'Existing Document']);

    $result = runCommand(new SearchCommand($db), ['query' => 'zzzyyyxxx']);

    expect($result['output'])->toContain('No full-text matches found.')
        ->and($result['output'])->toContain('No tag matches found.');
});

it('requires query argument', function (): void {
    $db = freshDb();

    $result = runCommand(new SearchCommand($db), []);

    expect($result['code'])->not->toBe(0);
});
