<?php

declare(strict_types=1);

use Vault\Command\Books\BooksStatsCommand;

it('shows rating breakdown', function (): void {
    $db = freshDb();

    seedBook($db, ['title' => 'Book S1', 'id' => 'book-s1'], ['rating' => 'S']);
    seedBook($db, ['title' => 'Book A1', 'id' => 'book-a1'], ['rating' => 'A']);
    seedBook($db, ['title' => 'Book A2', 'id' => 'book-a2'], ['rating' => 'A']);
    seedBook($db, ['title' => 'Book B1', 'id' => 'book-b1'], ['rating' => 'B']);

    $result = runCommand(new BooksStatsCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Rating Breakdown (All Time)')
        ->and($result['output'])->toContain('S')
        ->and($result['output'])->toContain('A')
        ->and($result['output'])->toContain('B');
});

it('shows this year stats', function (): void {
    $db = freshDb();

    $id1 = seedBook($db, ['title' => 'Book One', 'id' => 'book-one'], ['rating' => 'A']);
    $id2 = seedBook($db, ['title' => 'Book Two', 'id' => 'book-two'], ['rating' => 'B']);

    $currentYear = date('Y');
    seedMediaEvent($db, $id1, 'read', "{$currentYear}-02-15");
    seedMediaEvent($db, $id2, 'read', "{$currentYear}-03-01");

    $result = runCommand(new BooksStatsCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('This Year')
        ->and($result['output'])->toContain('Total read this year: 2');
});

it('shows most re-read books', function (): void {
    $db = freshDb();

    $id1 = seedBook($db, ['title' => 'Dune', 'id' => 'book-dune'], ['author' => 'Frank Herbert', 'rating' => 'S']);
    $id2 = seedBook($db, ['title' => 'Neuromancer', 'id' => 'book-neuro'], ['author' => 'William Gibson', 'rating' => 'A']);

    // Dune read 3 times
    seedMediaEvent($db, $id1, 'read', '2024-01-10');
    seedMediaEvent($db, $id1, 'read', '2025-06-15');
    seedMediaEvent($db, $id1, 'read', '2026-03-01');

    // Neuromancer read only once (should not appear in re-read list)
    seedMediaEvent($db, $id2, 'read', '2026-02-20');

    $result = runCommand(new BooksStatsCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Most Re-read')
        ->and($result['output'])->toContain('Dune')
        ->and($result['output'])->toContain('3');
});
