<?php

declare(strict_types=1);

use Vault\Command\Db\UpsertBookCommand;

it('inserts a book record', function (): void {
    $db = freshDb();
    $id = seedDocument($db, [
        'id' => 'book-test',
        'domain' => 'personal',
        'subdomain' => 'books',
        'title' => 'Test Book',
    ]);

    $result = runCommand(new UpsertBookCommand($db), [
        '--id' => $id,
        '--author' => 'Test Author',
        '--rating' => 'A',
    ]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain("ok: upserted book '{$id}'");

    $row = $db->fetchOne('SELECT * FROM books WHERE doc_id = :id', [':id' => $id]);

    expect($row)->not->toBeNull()
        ->and($row['author'])->toBe('Test Author')
        ->and($row['rating'])->toBe('A');
});

it('handles optional fields like series-id and cover-url', function (): void {
    $db = freshDb();

    // Create the series parent
    $seriesId = seedDocument($db, [
        'id' => 'series-test',
        'title' => 'Test Series',
        'domain' => 'personal',
        'subdomain' => 'books',
    ]);

    $bookId = seedDocument($db, [
        'id' => 'book-with-series',
        'title' => 'Book In Series',
        'domain' => 'personal',
        'subdomain' => 'books',
        'file_path' => 'vault/personal/books/2026/03/book-in-series.md',
    ]);

    $result = runCommand(new UpsertBookCommand($db), [
        '--id' => $bookId,
        '--author' => 'Series Author',
        '--series-id' => $seriesId,
        '--series-order' => '3',
        '--rating' => 'S',
        '--cover-url' => 'https://example.com/cover.jpg',
    ]);

    expect($result['code'])->toBe(0);

    $row = $db->fetchOne('SELECT * FROM books WHERE doc_id = :id', [':id' => $bookId]);

    expect($row['series_id'])->toBe($seriesId)
        ->and((int) $row['series_order'])->toBe(3)
        ->and($row['rating'])->toBe('S')
        ->and($row['cover_url'])->toBe('https://example.com/cover.jpg');
});
