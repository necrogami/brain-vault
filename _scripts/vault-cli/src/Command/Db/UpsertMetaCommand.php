<?php

declare(strict_types=1);

namespace Vault\Command\Db;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'db:upsert-meta', description: 'Update entity-specific metadata (JSON) on a document')]
final class UpsertMetaCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Document ID')
            ->addOption('json', null, InputOption::VALUE_REQUIRED, 'JSON object to merge into meta');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $json = $input->getOption('json');

        $this->db->execute(
            "UPDATE documents SET meta = json_patch(meta, :json) WHERE id = :id",
            [':id' => $id, ':json' => $json],
        );

        $output->writeln("ok: updated meta on '{$id}'");

        return Command::SUCCESS;
    }
}
