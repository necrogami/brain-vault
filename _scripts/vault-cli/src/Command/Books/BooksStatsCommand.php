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
                FROM documents
                WHERE subdomain = 'books' AND rating IS NOT NULL AND COALESCE(meta->>'$.type', 'book') != 'series'
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
                SELECT d.rating, COUNT(*) AS count
                FROM media_events me
                JOIN documents d ON me.doc_id = d.id
                WHERE me.event_type = 'read' AND me.event_date >= :year_start
                GROUP BY d.rating
                ORDER BY d.rating
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
                SELECT d.title, d.meta->>'$.author' AS author, COUNT(*) AS times_read
                FROM media_events me
                JOIN documents d ON me.doc_id = d.id
                WHERE me.event_type = 'read'
                GROUP BY me.doc_id
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
