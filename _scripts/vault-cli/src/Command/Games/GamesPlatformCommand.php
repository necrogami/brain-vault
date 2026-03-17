<?php

declare(strict_types=1);

namespace Vault\Command\Games;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'games:platform', description: 'List games by platform')]
final class GamesPlatformCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Platform name (partial match)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $pattern = '%' . $name . '%';

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT title, meta->>'$.developer' AS developer, meta->>'$.platform' AS platform,
                       CAST(meta->>'$.hours_played' AS REAL) AS hours_played, rating, status
                FROM documents
                WHERE subdomain = 'games' AND meta->>'$.platform' LIKE :platform
                ORDER BY developer, title
            SQL,
            [':platform' => $pattern],
        );

        if ($rows === []) {
            $output->writeln(sprintf('<comment>No games found for platform matching "%s".</comment>', $name));

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Developer', 'Hours', 'Rating']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['title'],
                $row['developer'] ?? '',
                $row['hours_played'] !== null ? (string) $row['hours_played'] : '',
                $row['rating'] ?? '',
            ]);
        }

        $table->render();

        $output->writeln(sprintf('<info>%d game(s) found.</info>', count($rows)));

        return Command::SUCCESS;
    }
}
