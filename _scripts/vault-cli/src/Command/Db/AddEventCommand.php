<?php

declare(strict_types=1);

namespace Vault\Command\Db;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'db:add-event', description: 'Log a media event (read, watch, play session)')]
final class AddEventCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Document ID')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Event type (read, watch, play_session)')
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Event date (YYYY-MM-DD)')
            ->addOption('json', null, InputOption::VALUE_REQUIRED, 'Optional JSON metadata');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $type = $input->getOption('type');
        $date = $input->getOption('date');
        $json = $input->getOption('json') ?? '{}';

        $this->db->execute(
            'INSERT INTO media_events (doc_id, event_type, event_date, meta) VALUES (:doc_id, :type, :date, :meta)',
            [':doc_id' => $id, ':type' => $type, ':date' => $date, ':meta' => $json],
        );

        $output->writeln("ok: logged {$type} for '{$id}' on {$date}");

        return Command::SUCCESS;
    }
}
