<?php

declare(strict_types=1);

namespace Vault\Command\Movies;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'movies:recent', description: 'Show recently watched movies')]
final class MoviesRecentCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('n', InputArgument::OPTIONAL, 'Number of movies to show', '10');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getArgument('n');

        if ($limit <= 0) {
            $output->writeln('<error>Limit must be a positive integer.</error>');

            return Command::FAILURE;
        }

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT d.title, d.meta->>'$.director' AS director,
                       d.rating, me.event_date AS date_watched
                FROM media_events me
                JOIN documents d ON me.doc_id = d.id
                WHERE me.event_type = 'watch'
                ORDER BY me.event_date DESC
                LIMIT :limit
            SQL,
            [':limit' => $limit],
        );

        if ($rows === []) {
            $output->writeln('<comment>No watch history found.</comment>');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Director', 'Rating', 'Date Watched']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['title'],
                $row['director'] ?? '',
                $row['rating'] ?? '',
                $row['date_watched'],
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
