<?php

declare(strict_types=1);

namespace Vault\Command\Db;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'db:add-play-session', description: 'Log a play session for a game')]
final class AddPlaySessionCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Document ID for the game')
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Date played (YYYY-MM-DD)')
            ->addOption('hours', null, InputOption::VALUE_REQUIRED, 'Hours played in this session');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $date = $input->getOption('date');
        $hours = $input->getOption('hours');

        $this->db->execute(
            'INSERT INTO play_sessions (doc_id, date_played, hours) VALUES (:doc_id, :date_played, :hours)',
            [
                ':doc_id' => $id,
                ':date_played' => $date,
                ':hours' => $hours,
            ],
        );

        $hoursMsg = $hours !== null ? " ({$hours}h)" : '';
        $output->writeln("ok: logged play session for '{$id}' on {$date}{$hoursMsg}");

        return Command::SUCCESS;
    }
}
