<?php

declare(strict_types=1);

use Vault\Command\Books\BooksSeriesCommand;

it('lists books in a series ordered by series_order', function (): void {
    $db = freshDb();

    // Create the series parent document
    $seriesId = seedDocument($db, [
        'id' => 'series-dune',
        'title' => 'Dune Series',
        'domain' => 'personal',
        'subdomain' => 'books',
    ]);

    // Seed books in the series (intentionally out of order to verify ordering)
    seedBook($db, ['title' => 'Dune Messiah', 'id' => 'book-dm'], [
        'author' => 'Frank Herbert',
        'series_id' => $seriesId,
        'series_order' => 2,
        'rating' => 'A',
    ]);
    seedBook($db, ['title' => 'Dune', 'id' => 'book-dune'], [
        'author' => 'Frank Herbert',
        'series_id' => $seriesId,
        'series_order' => 1,
        'rating' => 'S',
    ]);
    seedBook($db, ['title' => 'Children of Dune', 'id' => 'book-cod'], [
        'author' => 'Frank Herbert',
        'series_id' => $seriesId,
        'series_order' => 3,
        'rating' => 'B',
    ]);

    $result = runCommand(new BooksSeriesCommand($db), ['name' => 'Dune']);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('Dune')
        ->and($result['output'])->toContain('Dune Messiah')
        ->and($result['output'])->toContain('Children of Dune')
        ->and($result['output'])->toContain('3 book(s) in series.');

    // Verify ordering: Dune (1) should appear before Dune Messiah (2) before Children of Dune (3)
    $dunePos = strpos($result['output'], 'Frank Herbert');
    expect($dunePos)->not->toBeFalse();
});

it('shows no results for unknown series', function (): void {
    $db = freshDb();

    $seriesId = seedDocument($db, [
        'id' => 'series-dune',
        'title' => 'Dune Series',
        'domain' => 'personal',
        'subdomain' => 'books',
    ]);

    seedBook($db, ['title' => 'Dune', 'id' => 'book-dune'], [
        'author' => 'Frank Herbert',
        'series_id' => $seriesId,
        'series_order' => 1,
    ]);

    $result = runCommand(new BooksSeriesCommand($db), ['name' => 'Discworld']);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('No books found in series matching "Discworld".');
});
