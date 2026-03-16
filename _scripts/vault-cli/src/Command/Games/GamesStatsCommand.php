<?php

declare(strict_types=1);

namespace Vault\Command\Games;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'games:stats', description: 'Show game collection statistics')]
final class GamesStatsCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->renderRatingBreakdown($output);
        $this->renderPlatformBreakdown($output);
        $this->renderTotalHours($output);
        $this->renderMostPlayed($output);

        return Command::SUCCESS;
    }

    private function renderRatingBreakdown(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>Rating Breakdown</info>');

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT rating, COUNT(*) AS count
                FROM games
                WHERE rating IS NOT NULL
                GROUP BY rating
                ORDER BY rating
            SQL,
        );

        if ($rows === []) {
            $output->writeln('<comment>  No rated games found.</comment>');

            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Rating', 'Count']);

        foreach ($rows as $row) {
            $table->addRow([$row['rating'], (string) $row['count']]);
        }

        $table->render();
    }

    private function renderPlatformBreakdown(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>Platform Breakdown</info>');

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT platform, COUNT(*) AS count
                FROM games
                WHERE platform IS NOT NULL
                GROUP BY platform
                ORDER BY count DESC
            SQL,
        );

        if ($rows === []) {
            $output->writeln('<comment>  No platform data found.</comment>');

            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Platform', 'Count']);

        foreach ($rows as $row) {
            $table->addRow([$row['platform'], (string) $row['count']]);
        }

        $table->render();
    }

    private function renderTotalHours(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>Total Hours Played</info>');

        $row = $this->db->fetchOne(
            <<<'SQL'
                SELECT COALESCE(SUM(hours_played), 0) AS total_hours,
                       COUNT(*) AS games_with_hours
                FROM games
                WHERE hours_played IS NOT NULL
            SQL,
        );

        if ($row === null || (float) $row['total_hours'] === 0.0) {
            $output->writeln('<comment>  No hours tracked.</comment>');

            return;
        }

        $output->writeln(sprintf(
            '  %.1f hours across %d game(s)',
            (float) $row['total_hours'],
            (int) $row['games_with_hours'],
        ));
    }

    private function renderMostPlayed(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>Most Played</info>');

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT d.title, g.developer, g.hours_played
                FROM games g
                JOIN documents d ON g.doc_id = d.id
                WHERE g.hours_played IS NOT NULL AND g.hours_played > 0
                ORDER BY g.hours_played DESC
                LIMIT 10
            SQL,
        );

        if ($rows === []) {
            $output->writeln('<comment>  No play time data found.</comment>');

            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Developer', 'Hours']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['title'],
                $row['developer'] ?? '',
                (string) $row['hours_played'],
            ]);
        }

        $table->render();
    }
}
