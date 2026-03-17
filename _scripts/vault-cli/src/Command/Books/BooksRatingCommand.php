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

#[AsCommand(name: 'books:rating', description: 'List books filtered by rating tier')]
final class BooksRatingCommand extends Command
{
    private const array VALID_TIERS = ['S', 'A', 'B', 'C', 'D', 'E', 'F', 'DNF'];

    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'tier',
            InputArgument::REQUIRED,
            'Rating tier (' . implode('/', self::VALID_TIERS) . ')',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tier = strtoupper($input->getArgument('tier'));

        if (!in_array($tier, self::VALID_TIERS, true)) {
            $output->writeln(sprintf(
                '<error>Invalid tier "%s". Valid tiers: %s</error>',
                $tier,
                implode(', ', self::VALID_TIERS),
            ));

            return Command::FAILURE;
        }

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT title, meta->>'$.author' AS author, status
                FROM documents
                WHERE subdomain = 'books' AND UPPER(rating) = :tier
                ORDER BY author, title
            SQL,
            [':tier' => $tier],
        );

        if ($rows === []) {
            $output->writeln(sprintf('<comment>No books with rating "%s".</comment>', $tier));

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Author', 'Status']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['title'],
                $row['author'] ?? '',
                $row['status'],
            ]);
        }

        $table->render();

        $output->writeln(sprintf('<info>%d book(s) rated %s.</info>', count($rows), $tier));

        return Command::SUCCESS;
    }
}
