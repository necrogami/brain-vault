<?php

declare(strict_types=1);

namespace Vault\Command\Movies;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'movies:stats', description: 'Show movie watching statistics')]
final class MoviesStatsCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->renderRatingBreakdown($output);
        $this->renderThisYear($output);
        $this->renderMostRewatched($output);

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
                WHERE subdomain = 'movies'
                  AND rating IS NOT NULL
                GROUP BY rating
                ORDER BY rating
            SQL,
        );

        if ($rows === []) {
            $output->writeln('<comment>  No rated movies found.</comment>');

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
                WHERE me.event_type = 'watch'
                  AND me.event_date >= :year_start
                GROUP BY d.rating
                ORDER BY d.rating
            SQL,
            [':year_start' => $yearStart],
        );

        if ($rows === []) {
            $output->writeln('<comment>  No movies watched this year.</comment>');

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

        $output->writeln(sprintf('  <info>Total watched this year: %d</info>', $total));
    }

    private function renderMostRewatched(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>Most Re-watched</info>');

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT d.title, d.meta->>'$.director' AS director,
                       COUNT(*) AS times_watched
                FROM media_events me
                JOIN documents d ON me.doc_id = d.id
                WHERE me.event_type = 'watch'
                GROUP BY me.doc_id
                HAVING COUNT(*) > 1
                ORDER BY times_watched DESC
            SQL,
        );

        if ($rows === []) {
            $output->writeln('<comment>  No re-watched movies found.</comment>');

            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Director', 'Times Watched']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['title'],
                $row['director'] ?? '',
                (string) $row['times_watched'],
            ]);
        }

        $table->render();
    }
}
