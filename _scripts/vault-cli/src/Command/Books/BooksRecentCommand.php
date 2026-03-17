<?php

declare(strict_types=1);

namespace Vault\Command\Books;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'books:recent', description: 'Show recently read books')]
final class BooksRecentCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('n', InputArgument::OPTIONAL, 'Number of books to show', '10');
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
                SELECT d.title, d.meta->>'$.author' AS author, d.rating,
                       me.event_date AS date_read
                FROM media_events me
                JOIN documents d ON me.doc_id = d.id
                WHERE me.event_type = 'read'
                ORDER BY me.event_date DESC
                LIMIT :limit
            SQL,
            [':limit' => $limit],
        );

        if ($rows === []) {
            $output->writeln('<comment>No read history found.</comment>');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Author', 'Rating', 'Date Read']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['title'],
                $row['author'] ?? '',
                $row['rating'] ?? '',
                $row['date_read'],
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
