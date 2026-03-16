<?php

declare(strict_types=1);

use Vault\Command\Books\BooksRecentCommand;

it('shows recently read books', function (): void {
    $db = freshDb();

    $id1 = seedBook($db, ['title' => 'Dune', 'id' => 'book-dune'], ['author' => 'Frank Herbert', 'rating' => 'S']);
    $id2 = seedBook($db, ['title' => 'Neuromancer', 'id' => 'book-neuro'], ['author' => 'William Gibson', 'rating' => 'A']);

    $db->execute('INSERT INTO reads (doc_id, date_read) VALUES (:id, :date)', [':id' => $id1, ':date' => '2026-03-10']);
    $db->execute('INSERT INTO reads (doc_id, date_read) VALUES (:id, :date)', [':id' => $id2, ':date' => '2026-03-12']);

    $result = runCommand(new BooksRecentCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Dune')
        ->and($result['output'])->toContain('Frank Herbert')
        ->and($result['output'])->toContain('Neuromancer')
        ->and($result['output'])->toContain('William Gibson')
        ->and($result['output'])->toContain('2026-03-12');
});

it('respects limit argument', function (): void {
    $db = freshDb();

    $id1 = seedBook($db, ['title' => 'Book One', 'id' => 'book-one'], ['author' => 'Author A', 'rating' => 'A']);
    $id2 = seedBook($db, ['title' => 'Book Two', 'id' => 'book-two'], ['author' => 'Author B', 'rating' => 'B']);
    $id3 = seedBook($db, ['title' => 'Book Three', 'id' => 'book-three'], ['author' => 'Author C', 'rating' => 'C']);

    $db->execute('INSERT INTO reads (doc_id, date_read) VALUES (:id, :date)', [':id' => $id1, ':date' => '2026-03-01']);
    $db->execute('INSERT INTO reads (doc_id, date_read) VALUES (:id, :date)', [':id' => $id2, ':date' => '2026-03-05']);
    $db->execute('INSERT INTO reads (doc_id, date_read) VALUES (:id, :date)', [':id' => $id3, ':date' => '2026-03-10']);

    $result = runCommand(new BooksRecentCommand($db), ['n' => '2']);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Book Three')
        ->and($result['output'])->toContain('Book Two')
        ->and($result['output'])->not->toContain('Book One');
});

it('shows empty state when no reads exist', function (): void {
    $db = freshDb();

    seedBook($db, ['title' => 'Dune', 'id' => 'book-dune'], ['author' => 'Frank Herbert']);

    $result = runCommand(new BooksRecentCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('No read history found.');
});
