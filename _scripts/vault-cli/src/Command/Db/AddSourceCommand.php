<?php

declare(strict_types=1);

namespace Vault\Command\Db;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'db:add-source', description: 'Add a source reference to a document')]
final class AddSourceCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Document ID')
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'Source URL')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Source title')
            ->addOption('accessed', null, InputOption::VALUE_REQUIRED, 'Date accessed (YYYY-MM-DD)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');

        $this->db->execute(
            <<<'SQL'
                INSERT INTO sources (doc_id, url, title, accessed_date)
                VALUES (:doc_id, :url, :title, :accessed_date)
            SQL,
            [
                ':doc_id' => $id,
                ':url' => $input->getOption('url'),
                ':title' => $input->getOption('title'),
                ':accessed_date' => $input->getOption('accessed'),
            ],
        );

        $output->writeln("ok: added source to '{$id}'");

        return Command::SUCCESS;
    }
}
