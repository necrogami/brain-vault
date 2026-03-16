<?php

declare(strict_types=1);

use Vault\Command\Books\BooksAuthorCommand;

it('filters books by author name with partial match', function (): void {
    $db = freshDb();

    seedBook($db, ['title' => 'Dune', 'id' => 'book-dune'], ['author' => 'Frank Herbert', 'rating' => 'S']);
    seedBook($db, ['title' => 'Children of Dune', 'id' => 'book-cod'], ['author' => 'Frank Herbert', 'rating' => 'B']);
    seedBook($db, ['title' => 'Neuromancer', 'id' => 'book-neuro'], ['author' => 'William Gibson', 'rating' => 'A']);

    $result = runCommand(new BooksAuthorCommand($db), ['name' => 'Herbert']);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Dune')
        ->and($result['output'])->toContain('Children of Dune')
        ->and($result['output'])->not->toContain('Neuromancer')
        ->and($result['output'])->toContain('2 book(s) found.');
});

it('shows no results for unknown author', function (): void {
    $db = freshDb();

    seedBook($db, ['title' => 'Dune', 'id' => 'book-dune'], ['author' => 'Frank Herbert']);

    $result = runCommand(new BooksAuthorCommand($db), ['name' => 'Tolkien']);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('No books found for author matching "Tolkien".');
});
