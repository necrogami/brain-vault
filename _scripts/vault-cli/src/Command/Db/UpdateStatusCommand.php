<?php

declare(strict_types=1);

namespace Vault\Command\Db;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'db:update-status', description: 'Update a document status')]
final class UpdateStatusCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Document ID')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'New status')
            ->addOption('modified', null, InputOption::VALUE_REQUIRED, 'Modified timestamp (defaults to current UTC)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $status = $input->getOption('status');
        $modified = $input->getOption('modified') ?? gmdate('Y-m-d\TH:i:s');

        $this->db->execute(
            'UPDATE documents SET status = :status, modified_at = :modified WHERE id = :id',
            [
                ':status' => $status,
                ':modified' => $modified,
                ':id' => $id,
            ],
        );

        $output->writeln("ok: '{$id}' status → {$status}");

        return Command::SUCCESS;
    }
}
