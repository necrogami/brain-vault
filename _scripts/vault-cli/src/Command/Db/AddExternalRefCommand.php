<?php

declare(strict_types=1);

namespace Vault\Command\Db;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'db:add-external-ref', description: 'Add an external reference to a document')]
final class AddExternalRefCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Document ID')
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'External URL')
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'Label for the reference');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');

        $this->db->execute(
            'INSERT INTO external_refs (doc_id, url, label) VALUES (:doc_id, :url, :label)',
            [
                ':doc_id' => $id,
                ':url' => $input->getOption('url'),
                ':label' => $input->getOption('label'),
            ],
        );

        $output->writeln("ok: added external ref to '{$id}'");

        return Command::SUCCESS;
    }
}
