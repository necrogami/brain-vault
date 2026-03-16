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

#[AsCommand(name: 'books:author', description: 'List books by a specific author')]
final class BooksAuthorCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Author name (partial match)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $pattern = '%' . $name . '%';

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT d.title, b.rating, d.status
                FROM books b
                JOIN documents d ON b.doc_id = d.id
                WHERE b.author LIKE :pattern
                ORDER BY b.series_order
            SQL,
            [':pattern' => $pattern],
        );

        if ($rows === []) {
            $output->writeln(sprintf('<comment>No books found for author matching "%s".</comment>', $name));

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Rating', 'Status']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['title'],
                $row['rating'] ?? '',
                $row['status'],
            ]);
        }

        $table->render();

        $output->writeln(sprintf('<info>%d book(s) found.</info>', count($rows)));

        return Command::SUCCESS;
    }
}
