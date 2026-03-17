<?php

declare(strict_types=1);

namespace Vault\Command\Tv;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'tv:watching', description: 'List currently watching TV shows')]
final class TvWatchingCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT title, meta->>'$.creator' AS creator,
                       CAST(meta->>'$.seasons_watched' AS INTEGER) AS seasons_watched,
                       CAST(meta->>'$.total_seasons' AS INTEGER) AS total_seasons
                FROM documents
                WHERE subdomain = 'tv' AND status = 'growing'
                ORDER BY title
            SQL,
        );

        if ($rows === []) {
            $output->writeln('<comment>No shows currently being watched.</comment>');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Creator', 'Progress']);

        foreach ($rows as $row) {
            $watched = $row['seasons_watched'] !== null ? (string) $row['seasons_watched'] : '?';
            $total = $row['total_seasons'] !== null ? (string) $row['total_seasons'] : '?';

            $table->addRow([
                $row['title'],
                $row['creator'] ?? '',
                "{$watched}/{$total}",
            ]);
        }

        $table->render();

        $output->writeln(sprintf('<info>%d show(s) currently watching.</info>', count($rows)));

        return Command::SUCCESS;
    }
}
