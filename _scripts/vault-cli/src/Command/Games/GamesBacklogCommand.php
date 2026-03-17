<?php

declare(strict_types=1);

namespace Vault\Command\Games;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'games:backlog', description: 'List games in the backlog')]
final class GamesBacklogCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT title, meta->>'$.developer' AS developer, meta->>'$.platform' AS platform, status
                FROM documents
                WHERE subdomain = 'games' AND status IN ('seed', 'tbr')
                ORDER BY title
            SQL,
        );

        if ($rows === []) {
            $output->writeln('<comment>No games in the backlog.</comment>');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Developer', 'Platform']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['title'],
                $row['developer'] ?? '',
                $row['platform'] ?? '',
            ]);
        }

        $table->render();

        $output->writeln(sprintf('<info>%d game(s) in backlog.</info>', count($rows)));

        return Command::SUCCESS;
    }
}
