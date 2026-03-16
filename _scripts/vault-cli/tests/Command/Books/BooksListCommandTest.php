<?php

declare(strict_types=1);

use Vault\Command\Books\BooksListCommand;

it('lists all books with title, author, and rating', function (): void {
    $db = freshDb();

    seedBook($db, ['title' => 'Dune', 'id' => 'book-dune'], ['author' => 'Frank Herbert', 'rating' => 'S']);
    seedBook($db, ['title' => 'Neuromancer', 'id' => 'book-neuro'], ['author' => 'William Gibson', 'rating' => 'A']);

    $result = runCommand(new BooksListCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Dune')
        ->and($result['output'])->toContain('Frank Herbert')
        ->and($result['output'])->toContain('S')
        ->and($result['output'])->toContain('Neuromancer')
        ->and($result['output'])->toContain('William Gibson')
        ->and($result['output'])->toContain('A')
        ->and($result['output'])->toContain('2 book(s) total.');
});

it('shows empty state when no books exist', function (): void {
    $db = freshDb();

    $result = runCommand(new BooksListCommand($db));

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('No books found.');
});
