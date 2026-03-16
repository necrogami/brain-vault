<?php

declare(strict_types=1);

namespace Vault\Command\Db;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'db:add-todo', description: 'Add a TODO item')]
final class AddTodoCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('content', null, InputOption::VALUE_REQUIRED, 'TODO content/description')
            ->addOption('doc-id', null, InputOption::VALUE_REQUIRED, 'Document ID to attach the TODO to')
            ->addOption('due', null, InputOption::VALUE_REQUIRED, 'Due date (YYYY-MM-DD)')
            ->addOption('priority', null, InputOption::VALUE_REQUIRED, 'Priority level', 'p3-low')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'TODO status', 'open');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->db->execute(
            <<<'SQL'
                INSERT INTO todos (doc_id, content, due_date, priority, status)
                VALUES (:doc_id, :content, :due_date, :priority, :status)
            SQL,
            [
                ':doc_id' => $input->getOption('doc-id'),
                ':content' => $input->getOption('content'),
                ':due_date' => $input->getOption('due'),
                ':priority' => $input->getOption('priority'),
                ':status' => $input->getOption('status'),
            ],
        );

        $output->writeln('ok: added todo');

        return Command::SUCCESS;
    }
}
