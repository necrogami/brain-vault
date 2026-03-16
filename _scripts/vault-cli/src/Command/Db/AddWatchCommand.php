<?php

declare(strict_types=1);

namespace Vault\Command\Db;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'db:add-watch', description: 'Log a watch date for a movie')]
final class AddWatchCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Document ID for the movie')
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Date the movie was watched (YYYY-MM-DD)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $date = $input->getOption('date');

        $this->db->execute(
            'INSERT INTO watches (doc_id, date_watched) VALUES (:doc_id, :date_watched)',
            [
                ':doc_id' => $id,
                ':date_watched' => $date,
            ],
        );

        $output->writeln("ok: logged watch for '{$id}' on {$date}");

        return Command::SUCCESS;
    }
}
