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

#[AsCommand(name: 'books:series', description: 'List books in a series')]
final class BooksSeriesCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Series name (partial match)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $pattern = '%' . $name . '%';

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT d.title, b.series_order, b.author, b.rating, d.status
                FROM books b
                JOIN documents d ON b.doc_id = d.id
                JOIN documents sd ON b.series_id = sd.id
                WHERE sd.title LIKE :pattern
                ORDER BY b.series_order
            SQL,
            [':pattern' => $pattern],
        );

        if ($rows === []) {
            $output->writeln(sprintf('<comment>No books found in series matching "%s".</comment>', $name));

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Order', 'Author', 'Rating', 'Status']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['title'],
                $row['series_order'] !== null ? (string) $row['series_order'] : '',
                $row['author'] ?? '',
                $row['rating'] ?? '',
                $row['status'],
            ]);
        }

        $table->render();

        $output->writeln(sprintf('<info>%d book(s) in series.</info>', count($rows)));

        return Command::SUCCESS;
    }
}
