<?php

declare(strict_types=1);

use Vault\Command\Books\BooksRatingCommand;

it('filters books by rating tier', function (): void {
    $db = freshDb();

    seedBook($db, ['title' => 'Dune', 'id' => 'book-dune'], ['author' => 'Frank Herbert', 'rating' => 'S']);
    seedBook($db, ['title' => 'Neuromancer', 'id' => 'book-neuro'], ['author' => 'William Gibson', 'rating' => 'A']);
    seedBook($db, ['title' => 'Snow Crash', 'id' => 'book-snow'], ['author' => 'Neal Stephenson', 'rating' => 'A']);
    seedBook($db, ['title' => 'Average Book', 'id' => 'book-avg'], ['author' => 'Some Author', 'rating' => 'C']);

    $result = runCommand(new BooksRatingCommand($db), ['tier' => 'A']);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Neuromancer')
        ->and($result['output'])->toContain('Snow Crash')
        ->and($result['output'])->not->toContain('Dune')
        ->and($result['output'])->not->toContain('Average Book')
        ->and($result['output'])->toContain('2 book(s) rated A.');
});

it('rejects invalid rating tier', function (): void {
    $db = freshDb();

    $result = runCommand(new BooksRatingCommand($db), ['tier' => 'Z']);

    expect($result['code'])->toBe(1)
        ->and($result['output'])->toContain('Invalid tier "Z"')
        ->and($result['output'])->toContain('Valid tiers:');
});
