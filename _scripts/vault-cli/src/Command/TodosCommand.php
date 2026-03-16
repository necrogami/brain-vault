<?php

declare(strict_types=1);

namespace Vault\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'todos', description: 'List TODOs with priority, status, and context')]
final class TodosCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('all', 'a', InputOption::VALUE_NONE, 'Include done and cancelled TODOs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $showAll = $input->getOption('all');

        if ($showAll) {
            $todos = $this->db->fetchAll(
                <<<'SQL'
                    SELECT t.priority, t.content, t.due_date, t.status, t.doc_id, d.title
                    FROM todos t
                    LEFT JOIN documents d ON t.doc_id = d.id
                    ORDER BY
                        CASE t.status WHEN 'open' THEN 0 WHEN 'done' THEN 1 ELSE 2 END,
                        t.priority ASC,
                        t.due_date ASC
                SQL,
            );
        } else {
            $todos = $this->db->fetchAll(
                <<<'SQL'
                    SELECT t.priority, t.content, t.due_date, t.status, t.doc_id, d.title
                    FROM todos t
                    LEFT JOIN documents d ON t.doc_id = d.id
                    WHERE t.status = :status
                    ORDER BY t.priority ASC, t.due_date ASC
                SQL,
                [':status' => 'open'],
            );
        }

        if ($todos === []) {
            $output->writeln('<info>No TODOs found.</info>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Priority', 'Content', 'Due', 'Status', 'Context']);

        foreach ($todos as $todo) {
            $context = $todo['title'] ?? '(standalone)';
            $due = $todo['due_date'] ?? '-';

            $table->addRow([
                $todo['priority'],
                $todo['content'],
                $due,
                $todo['status'],
                $context,
            ]);
        }

        $table->render();

        $output->writeln('');
        $output->writeln(sprintf('<info>%d TODO(s) listed.</info>', count($todos)));

        return Command::SUCCESS;
    }
}
