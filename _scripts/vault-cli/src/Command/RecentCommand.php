<?php

declare(strict_types=1);

namespace Vault\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'recent', description: 'Show recently created and updated documents')]
final class RecentCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('days', InputArgument::OPTIONAL, 'Number of days to look back', '2');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = max(1, (int) $input->getArgument('days'));
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $this->renderCreated($output, $cutoff, $days);
        $this->renderUpdated($output, $cutoff, $days);

        return Command::SUCCESS;
    }

    private function renderCreated(OutputInterface $output, string $cutoff, int $days): void
    {
        $output->writeln('');
        $output->writeln(sprintf('<comment>Created (last %d day%s)</comment>', $days, $days === 1 ? '' : 's'));

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT id, title, domain, subdomain, status, created_at
                FROM documents
                WHERE created_at >= :cutoff
                ORDER BY created_at DESC
            SQL,
            [':cutoff' => $cutoff],
        );

        if ($rows === []) {
            $output->writeln('<info>No documents created in this period.</info>');
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Title', 'Path', 'Status', 'Created']);

        foreach ($rows as $row) {
            $path = "{$row['domain']}/{$row['subdomain']}";

            $table->addRow([
                $row['id'],
                $row['title'],
                $path,
                $row['status'],
                $row['created_at'],
            ]);
        }

        $table->render();
        $output->writeln(sprintf('<info>%d document(s) created.</info>', count($rows)));
    }

    private function renderUpdated(OutputInterface $output, string $cutoff, int $days): void
    {
        $output->writeln('');
        $output->writeln(sprintf('<comment>Updated (last %d day%s)</comment>', $days, $days === 1 ? '' : 's'));

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT id, title, domain, subdomain, status, modified_at
                FROM documents
                WHERE modified_at >= :cutoff
                  AND created_at < :cutoff
                ORDER BY modified_at DESC
            SQL,
            [':cutoff' => $cutoff],
        );

        if ($rows === []) {
            $output->writeln('<info>No documents updated in this period.</info>');
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Title', 'Path', 'Status', 'Modified']);

        foreach ($rows as $row) {
            $path = "{$row['domain']}/{$row['subdomain']}";

            $table->addRow([
                $row['id'],
                $row['title'],
                $path,
                $row['status'],
                $row['modified_at'],
            ]);
        }

        $table->render();
        $output->writeln(sprintf('<info>%d document(s) updated.</info>', count($rows)));
    }
}
