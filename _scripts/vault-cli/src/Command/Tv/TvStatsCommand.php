<?php

declare(strict_types=1);

namespace Vault\Command\Tv;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'tv:stats', description: 'Show TV show watching statistics')]
final class TvStatsCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->renderRatingBreakdown($output);
        $this->renderStatusCounts($output);

        return Command::SUCCESS;
    }

    private function renderRatingBreakdown(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>Rating Breakdown</info>');

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT rating, COUNT(*) AS count
                FROM tv_shows
                WHERE rating IS NOT NULL
                GROUP BY rating
                ORDER BY rating
            SQL,
        );

        if ($rows === []) {
            $output->writeln('<comment>  No rated shows found.</comment>');

            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Rating', 'Count']);

        foreach ($rows as $row) {
            $table->addRow([$row['rating'], (string) $row['count']]);
        }

        $table->render();
    }

    private function renderStatusCounts(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>Status Overview</info>');

        $watching = $this->db->fetchValue(
            <<<'SQL'
                SELECT COUNT(*)
                FROM tv_shows t
                JOIN documents d ON t.doc_id = d.id
                WHERE d.status = 'growing'
            SQL,
        );

        $completed = $this->db->fetchValue(
            <<<'SQL'
                SELECT COUNT(*)
                FROM tv_shows t
                JOIN documents d ON t.doc_id = d.id
                WHERE d.status = 'mature'
            SQL,
        );

        $dropped = $this->db->fetchValue(
            <<<'SQL'
                SELECT COUNT(*)
                FROM tv_shows t
                JOIN documents d ON t.doc_id = d.id
                WHERE d.status = 'closed'
            SQL,
        );

        $total = $this->db->fetchValue(
            <<<'SQL'
                SELECT COUNT(*) FROM tv_shows
            SQL,
        );

        $output->writeln(sprintf('  Currently watching: <info>%d</info>', (int) $watching));
        $output->writeln(sprintf('  Completed:          <info>%d</info>', (int) $completed));
        $output->writeln(sprintf('  Dropped:            <info>%d</info>', (int) $dropped));
        $output->writeln(sprintf('  Total:              <info>%d</info>', (int) $total));
        $output->writeln('');
    }
}
