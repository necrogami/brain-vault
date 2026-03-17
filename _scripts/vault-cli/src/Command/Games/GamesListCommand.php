<?php

declare(strict_types=1);

namespace Vault\Command\Games;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'games:list', description: 'List all games sorted by developer')]
final class GamesListCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT title, meta->>'$.developer' AS developer, meta->>'$.platform' AS platform,
                       CAST(meta->>'$.hours_played' AS REAL) AS hours_played, rating
                FROM documents
                WHERE subdomain = 'games'
                ORDER BY developer, title
            SQL,
        );

        if ($rows === []) {
            $output->writeln('<comment>No games found.</comment>');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Developer', 'Platform', 'Hours', 'Rating']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['title'],
                $row['developer'] ?? '',
                $row['platform'] ?? '',
                $row['hours_played'] !== null ? (string) $row['hours_played'] : '',
                $row['rating'] ?? '',
            ]);
        }

        $table->render();

        $output->writeln(sprintf('<info>%d game(s) total.</info>', count($rows)));

        return Command::SUCCESS;
    }
}
