<?php

declare(strict_types=1);

namespace Vault\Command\Books;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'books:list', description: 'List all books grouped by author')]
final class BooksListCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT title, meta->>'$.author' AS author, rating, status,
                       CAST(meta->>'$.series_order' AS REAL) AS series_order
                FROM documents
                WHERE subdomain = 'books' AND COALESCE(meta->>'$.type', 'book') != 'series'
                ORDER BY author, series_order
            SQL,
        );

        if ($rows === []) {
            $output->writeln('<comment>No books found.</comment>');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Author', 'Rating', 'Status', 'Order']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['title'],
                $row['author'] ?? '',
                $row['rating'] ?? '',
                $row['status'],
                $row['series_order'] !== null ? (string) $row['series_order'] : '',
            ]);
        }

        $table->render();

        $output->writeln(sprintf('<info>%d book(s) total.</info>', count($rows)));

        return Command::SUCCESS;
    }
}
