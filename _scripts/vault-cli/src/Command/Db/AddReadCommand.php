<?php

declare(strict_types=1);

namespace Vault\Command\Db;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'db:add-read', description: 'Log a read date for a book')]
final class AddReadCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Document ID for the book')
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Date the book was read (YYYY-MM-DD)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $date = $input->getOption('date');

        $this->db->execute(
            'INSERT INTO reads (doc_id, date_read) VALUES (:doc_id, :date_read)',
            [
                ':doc_id' => $id,
                ':date_read' => $date,
            ],
        );

        $output->writeln("ok: logged read for '{$id}' on {$date}");

        return Command::SUCCESS;
    }
}
