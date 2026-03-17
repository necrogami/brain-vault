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

#[AsCommand(name: 'movies:rating', description: 'List movies filtered by rating tier')]
final class MoviesRatingCommand extends Command
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
                SELECT title, meta->>'$.director' AS director,
                       CAST(meta->>'$.year' AS INTEGER) AS year, status
                FROM documents
                WHERE subdomain = 'movies'
                  AND rating = :rating
                ORDER BY director, title
            SQL,
            [':rating' => $tier],
        );

        if ($rows === []) {
            $output->writeln(sprintf('<comment>No movies with rating "%s".</comment>', $tier));

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Director', 'Year', 'Status']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['title'],
                $row['director'] ?? '',
                $row['year'] !== null ? (string) $row['year'] : '',
                $row['status'],
            ]);
        }

        $table->render();

        $output->writeln(sprintf('<info>%d movie(s) rated %s.</info>', count($rows), $tier));

        return Command::SUCCESS;
    }
}
