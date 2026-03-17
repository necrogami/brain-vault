<?php

declare(strict_types=1);

namespace Vault\Command\Db;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'db:upsert-doc', description: 'Insert or replace a document in the database')]
final class UpsertDocCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Document ID')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Document title')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Domain')
            ->addOption('subdomain', null, InputOption::VALUE_REQUIRED, 'Subdomain')
            ->addOption('file-path', null, InputOption::VALUE_REQUIRED, 'File path relative to vault root')
            ->addOption('created', null, InputOption::VALUE_REQUIRED, 'Created timestamp')
            ->addOption('modified', null, InputOption::VALUE_REQUIRED, 'Modified timestamp')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Document status', 'seed')
            ->addOption('priority', null, InputOption::VALUE_REQUIRED, 'Priority level', 'p3-low')
            ->addOption('confidence', null, InputOption::VALUE_REQUIRED, 'Confidence level', 'speculative')
            ->addOption('effort', null, InputOption::VALUE_REQUIRED, 'Effort estimate')
            ->addOption('summary', null, InputOption::VALUE_REQUIRED, 'One-line summary')
            ->addOption('revisit-date', null, InputOption::VALUE_REQUIRED, 'Date to revisit')
            ->addOption('close-reason', null, InputOption::VALUE_REQUIRED, 'Reason for closing')
            ->addOption('meta', null, InputOption::VALUE_REQUIRED, 'Entity-specific JSON metadata', '{}');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');

        $this->db->execute(
            <<<'SQL'
                INSERT OR REPLACE INTO documents
                    (id, title, domain, subdomain, status, priority, confidence, effort, summary, file_path, created_at, modified_at, revisit_date, close_reason, meta)
                VALUES
                    (:id, :title, :domain, :subdomain, :status, :priority, :confidence, :effort, :summary, :file_path, :created_at, :modified_at, :revisit_date, :close_reason, :meta)
            SQL,
            [
                ':id' => $id,
                ':title' => $input->getOption('title'),
                ':domain' => $input->getOption('domain'),
                ':subdomain' => $input->getOption('subdomain'),
                ':status' => $input->getOption('status'),
                ':priority' => $input->getOption('priority'),
                ':confidence' => $input->getOption('confidence'),
                ':effort' => $input->getOption('effort'),
                ':summary' => $input->getOption('summary'),
                ':file_path' => $input->getOption('file-path'),
                ':created_at' => $input->getOption('created'),
                ':modified_at' => $input->getOption('modified'),
                ':revisit_date' => $input->getOption('revisit-date'),
                ':close_reason' => $input->getOption('close-reason'),
                ':meta' => $input->getOption('meta'),
            ],
        );

        $output->writeln("ok: upserted document '{$id}'");

        return Command::SUCCESS;
    }
}
