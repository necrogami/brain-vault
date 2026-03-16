<?php

declare(strict_types=1);

namespace Vault\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'review', description: 'Weekly review — surfaces ideas needing attention based on staleness rules')]
final class ReviewCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = date('Y-m-d');
        $output->writeln('');
        $output->writeln('══════════════════════════════════════════');
        $output->writeln("  WEEKLY REVIEW — {$today}");
        $output->writeln('══════════════════════════════════════════');

        $this->renderSection($output, 'STALE SEEDS (>14 days)', 'seed', 14, 'Triage: grow, merge, or close');
        $this->renderSection($output, 'STALLED GROWING (>21 days)', 'growing', 21, 'Still active?');
        $this->renderSection($output, 'DORMANT CHECK-IN (>90 days)', 'dormant', 90, 'Reactivate or close?');
        $this->renderSection($output, 'TBR AGING (>60 days)', 'tbr', 60, 'Still want to read?');
        $this->renderManualRevisits($output, $today);
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function renderSection(OutputInterface $output, string $title, string $status, int $days, string $action): void
    {
        $cutoff = date('Y-m-d\TH:i:s', strtotime("-{$days} days"));
        $rows = $this->db->fetchAll(
            "SELECT id, title, domain || '/' || subdomain AS path, CAST(julianday('now') - julianday(modified_at) AS INTEGER) AS days_stale FROM documents WHERE status = :status AND modified_at < :cutoff ORDER BY modified_at ASC",
            [':status' => $status, ':cutoff' => $cutoff],
        );

        $count = count($rows);
        $output->writeln('');
        $output->writeln("  {$title} ({$count})");
        $output->writeln('  ' . str_repeat("\u{2500}", 40));

        if ($count === 0) {
            $output->writeln('  All clear.');

            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Path', 'Days Stale', 'Action']);

        foreach ($rows as $row) {
            $table->addRow([$row['title'], $row['path'], $row['days_stale'], $action]);
        }

        $table->render();
    }

    private function renderManualRevisits(OutputInterface $output, string $today): void
    {
        $rows = $this->db->fetchAll(
            "SELECT id, title, domain || '/' || subdomain AS path, revisit_date FROM documents WHERE revisit_date IS NOT NULL AND revisit_date <= :today AND status NOT IN ('closed', 'migrated', 'built') ORDER BY revisit_date ASC",
            [':today' => $today],
        );

        $count = count($rows);
        $output->writeln('');
        $output->writeln("  MANUAL REVISITS DUE ({$count})");
        $output->writeln('  ' . str_repeat("\u{2500}", 40));

        if ($count === 0) {
            $output->writeln('  None scheduled.');

            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Title', 'Path', 'Revisit Date']);

        foreach ($rows as $row) {
            $table->addRow([$row['title'], $row['path'], $row['revisit_date']]);
        }

        $table->render();
    }
}
