<?php

declare(strict_types=1);

namespace Vault\Command\Tv;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'tv:rating', description: 'List TV shows filtered by rating tier')]
final class TvRatingCommand extends Command
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
                SELECT d.title, t.creator, t.year_start, d.status
                FROM tv_shows t
                JOIN documents d ON t.doc_id = d.id
                WHERE UPPER(t.rating) = :tier
                ORDER BY t.year_start DESC, d.title
            SQL,
            [':tier' => $tier],
        );

        if ($rows === []) {
            $output->writeln(sprintf('<comment>No TV shows with rating "%s".</comment>', $tier));

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Creator', 'Year', 'Status']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['title'],
                $row['creator'] ?? '',
                $row['year_start'] !== null ? (string) $row['year_start'] : '',
                $row['status'],
            ]);
        }

        $table->render();

        $output->writeln(sprintf('<info>%d show(s) rated %s.</info>', count($rows), $tier));

        return Command::SUCCESS;
    }
}
