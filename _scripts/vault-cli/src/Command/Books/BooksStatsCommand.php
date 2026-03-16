<?php

declare(strict_types=1);

namespace Vault\Command\Books;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'books:stats', description: 'Show book reading statistics')]
final class BooksStatsCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->renderRatingBreakdown($output);
        $this->renderThisYear($output);
        $this->renderMostReread($output);

        return Command::SUCCESS;
    }

    private function renderRatingBreakdown(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>Rating Breakdown (All Time)</info>');

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT rating, COUNT(*) AS count
                FROM books
                WHERE rating IS NOT NULL
                GROUP BY rating
                ORDER BY rating
            SQL,
        );

        if ($rows === []) {
            $output->writeln('<comment>  No rated books found.</comment>');

            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Rating', 'Count']);

        foreach ($rows as $row) {
            $table->addRow([$row['rating'], (string) $row['count']]);
        }

        $table->render();
    }

    private function renderThisYear(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>This Year</info>');

        $yearStart = date('Y') . '-01-01';

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT b.rating, COUNT(*) AS count
                FROM reads r
                JOIN books b ON r.doc_id = b.doc_id
                WHERE r.date_read >= :year_start
                GROUP BY b.rating
                ORDER BY b.rating
            SQL,
            [':year_start' => $yearStart],
        );

        if ($rows === []) {
            $output->writeln('<comment>  No books read this year.</comment>');

            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Rating', 'Count']);

        $total = 0;

        foreach ($rows as $row) {
            $table->addRow([$row['rating'] ?? 'Unrated', (string) $row['count']]);
            $total += (int) $row['count'];
        }

        $table->render();

        $output->writeln(sprintf('  <info>Total read this year: %d</info>', $total));
    }

    private function renderMostReread(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>Most Re-read</info>');

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT d.title, b.author, COUNT(*) AS times_read
                FROM reads r
                JOIN books b ON r.doc_id = b.doc_id
                JOIN documents d ON r.doc_id = d.id
                GROUP BY r.doc_id
                HAVING COUNT(*) > 1
                ORDER BY times_read DESC
            SQL,
        );

        if ($rows === []) {
            $output->writeln('<comment>  No re-read books found.</comment>');

            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Author', 'Times Read']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['title'],
                $row['author'] ?? '',
                (string) $row['times_read'],
            ]);
        }

        $table->render();
    }
}
