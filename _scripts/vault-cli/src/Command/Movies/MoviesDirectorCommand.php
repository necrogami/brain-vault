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

#[AsCommand(name: 'movies:director', description: 'List movies by a specific director')]
final class MoviesDirectorCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Director name (partial match)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $pattern = '%' . $name . '%';

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT title, meta->>'$.director' AS director,
                       CAST(meta->>'$.year' AS INTEGER) AS year, rating, status
                FROM documents
                WHERE subdomain = 'movies'
                  AND meta->>'$.director' LIKE :name
                ORDER BY director, title
            SQL,
            [':name' => $pattern],
        );

        if ($rows === []) {
            $output->writeln(sprintf('<comment>No movies found for director matching "%s".</comment>', $name));

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Year', 'Rating', 'Status']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['title'],
                $row['year'] !== null ? (string) $row['year'] : '',
                $row['rating'] ?? '',
                $row['status'],
            ]);
        }

        $table->render();

        $output->writeln(sprintf('<info>%d movie(s) found.</info>', count($rows)));

        return Command::SUCCESS;
    }
}
