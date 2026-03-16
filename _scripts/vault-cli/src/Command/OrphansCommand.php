<?php

declare(strict_types=1);

namespace Vault\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'orphans', description: 'List documents with no links that are not closed/migrated/built')]
final class OrphansCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $orphans = $this->db->fetchAll(
            <<<'SQL'
                SELECT d.id, d.title, d.file_path, d.status, d.created_at
                FROM documents d
                WHERE d.status NOT IN (:closed, :migrated, :built)
                  AND NOT EXISTS (
                      SELECT 1 FROM links l
                      WHERE l.source_id = d.id OR l.target_id = d.id
                  )
                ORDER BY d.created_at ASC
            SQL,
            [':closed' => 'closed', ':migrated' => 'migrated', ':built' => 'built'],
        );

        if ($orphans === []) {
            $output->writeln('<info>No orphaned documents found.</info>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Title', 'Path', 'Status', 'Created']);

        foreach ($orphans as $orphan) {
            $table->addRow([
                $orphan['id'],
                $orphan['title'],
                $orphan['file_path'],
                $orphan['status'],
                $orphan['created_at'],
            ]);
        }

        $table->render();

        $output->writeln('');
        $output->writeln(sprintf('<info>%d orphaned document(s) found.</info>', count($orphans)));

        return Command::SUCCESS;
    }
}
