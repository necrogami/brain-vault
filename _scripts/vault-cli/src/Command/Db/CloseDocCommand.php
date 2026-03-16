<?php

declare(strict_types=1);

namespace Vault\Command\Db;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'db:close-doc', description: 'Close a document with a reason')]
final class CloseDocCommand extends Command
{
    private const array VALID_CLOSE_STATUSES = ['closed', 'migrated', 'built'];

    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Document ID')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'End status (closed, migrated, or built)')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Reason for closing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $status = $input->getOption('status');
        $reason = $input->getOption('reason');

        if (!in_array($status, self::VALID_CLOSE_STATUSES, true)) {
            $output->writeln(sprintf(
                '<error>Invalid close status "%s". Must be one of: %s</error>',
                $status,
                implode(', ', self::VALID_CLOSE_STATUSES),
            ));

            return Command::FAILURE;
        }

        $modified = gmdate('Y-m-d\TH:i:s');

        $this->db->execute(
            'UPDATE documents SET status = :status, close_reason = :reason, modified_at = :modified WHERE id = :id',
            [
                ':id' => $id,
                ':status' => $status,
                ':reason' => $reason,
                ':modified' => $modified,
            ],
        );

        $output->writeln("ok: '{$id}' → {$status} (reason: {$reason})");

        return Command::SUCCESS;
    }
}
