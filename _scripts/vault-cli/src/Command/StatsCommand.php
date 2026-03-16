<?php

declare(strict_types=1);

namespace Vault\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'stats', description: 'Vault statistics: status, domain, priority, tags, and todos')]
final class StatsCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->renderStatusBreakdown($output);
        $this->renderDomainBreakdown($output);
        $this->renderPriorityBreakdown($output);
        $this->renderTopTags($output);
        $this->renderOpenTodosByPriority($output);

        return Command::SUCCESS;
    }

    private function renderStatusBreakdown(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<comment>Status Breakdown</comment>');

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT status, COUNT(*) AS count
                FROM documents
                GROUP BY status
                ORDER BY count DESC
            SQL,
        );

        $table = new Table($output);
        $table->setHeaders(['Status', 'Count']);

        foreach ($rows as $row) {
            $table->addRow([$row['status'], $row['count']]);
        }

        $table->render();
    }

    private function renderDomainBreakdown(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<comment>Domain / Subdomain Breakdown</comment>');

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT domain, subdomain, COUNT(*) AS count
                FROM documents
                GROUP BY domain, subdomain
                ORDER BY domain ASC, count DESC
            SQL,
        );

        $table = new Table($output);
        $table->setHeaders(['Domain', 'Subdomain', 'Count']);

        foreach ($rows as $row) {
            $table->addRow([$row['domain'], $row['subdomain'], $row['count']]);
        }

        $table->render();
    }

    private function renderPriorityBreakdown(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<comment>Priority Breakdown</comment>');

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT priority, COUNT(*) AS count
                FROM documents
                GROUP BY priority
                ORDER BY priority ASC
            SQL,
        );

        $table = new Table($output);
        $table->setHeaders(['Priority', 'Count']);

        foreach ($rows as $row) {
            $table->addRow([$row['priority'], $row['count']]);
        }

        $table->render();
    }

    private function renderTopTags(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<comment>Top 20 Tags</comment>');

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT tag, COUNT(*) AS count
                FROM tags
                GROUP BY tag
                ORDER BY count DESC
                LIMIT 20
            SQL,
        );

        if ($rows === []) {
            $output->writeln('<info>No tags found.</info>');
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Tag', 'Count']);

        foreach ($rows as $row) {
            $table->addRow([$row['tag'], $row['count']]);
        }

        $table->render();
    }

    private function renderOpenTodosByPriority(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<comment>Open TODOs by Priority</comment>');

        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT priority, COUNT(*) AS count
                FROM todos
                WHERE status = :status
                GROUP BY priority
                ORDER BY priority ASC
            SQL,
            [':status' => 'open'],
        );

        if ($rows === []) {
            $output->writeln('<info>No open TODOs.</info>');
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Priority', 'Count']);

        foreach ($rows as $row) {
            $table->addRow([$row['priority'], $row['count']]);
        }

        $table->render();
    }
}
