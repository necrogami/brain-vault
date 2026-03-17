<?php

declare(strict_types=1);

namespace Vault\Command\Movies;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'movies:list', description: 'List all movies sorted by year')]
final class MoviesListCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT title, meta->>'$.director' AS director,
                       CAST(meta->>'$.year' AS INTEGER) AS year,
                       rating, status
                FROM documents
                WHERE subdomain = 'movies'
                ORDER BY director, title
            SQL,
        );

        if ($rows === []) {
            $output->writeln('<comment>No movies found.</comment>');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Director', 'Year', 'Rating', 'Status']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['title'],
                $row['director'] ?? '',
                $row['year'] !== null ? (string) $row['year'] : '',
                $row['rating'] ?? '',
                $row['status'],
            ]);
        }

        $table->render();

        $output->writeln(sprintf('<info>%d movie(s) total.</info>', count($rows)));

        return Command::SUCCESS;
    }
}
