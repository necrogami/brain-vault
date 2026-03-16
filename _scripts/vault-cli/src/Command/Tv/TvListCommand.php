<?php

declare(strict_types=1);

namespace Vault\Command\Tv;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'tv:list', description: 'List all TV shows')]
final class TvListCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT d.title, t.creator, t.year_start, t.seasons_watched,
                       t.total_seasons, t.rating
                FROM tv_shows t
                JOIN documents d ON t.doc_id = d.id
                ORDER BY t.year_start DESC, d.title
            SQL,
        );

        if ($rows === []) {
            $output->writeln('<comment>No TV shows found.</comment>');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Creator', 'Year', 'Progress', 'Rating']);

        foreach ($rows as $row) {
            $watched = $row['seasons_watched'] !== null ? (string) $row['seasons_watched'] : '?';
            $total = $row['total_seasons'] !== null ? (string) $row['total_seasons'] : '?';

            $table->addRow([
                $row['title'],
                $row['creator'] ?? '',
                $row['year_start'] !== null ? (string) $row['year_start'] : '',
                "{$watched}/{$total}",
                $row['rating'] ?? '',
            ]);
        }

        $table->render();

        $output->writeln(sprintf('<info>%d show(s) total.</info>', count($rows)));

        return Command::SUCCESS;
    }
}
