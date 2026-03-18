<?php

declare(strict_types=1);

namespace Vault\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'briefing', description: 'Daily vault briefing with todos, stats, and health checks')]
final class BriefingCommand extends Command
{
    private const string BORDER = '════════════════════════════════════════════════════';

    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = date('Y-m-d');

        $output->writeln('');
        $output->writeln(self::BORDER);
        $output->writeln("  VAULT BRIEFING -- {$today}");
        $output->writeln(self::BORDER);

        $this->renderOverdue($output, $today);
        $this->renderDueToday($output, $today);
        $this->renderRevisitToday($output, $today);
        $this->renderRecentActivity($output);
        $this->renderVaultStats($output);

        $output->writeln('');
        $output->writeln(self::BORDER);

        return Command::SUCCESS;
    }

    private function renderOverdue(OutputInterface $output, string $today): void
    {
        $todos = $this->db->fetchAll(
            <<<'SQL'
                SELECT t.priority, t.content, t.due_date, t.doc_id, d.title
                FROM todos t
                LEFT JOIN documents d ON t.doc_id = d.id
                WHERE t.status = :status
                  AND t.due_date < :today
                ORDER BY t.due_date ASC, t.priority ASC
            SQL,
            [':status' => 'open', ':today' => $today],
        );

        $count = count($todos);
        $output->writeln('');
        $output->writeln("  OVERDUE ({$count})");
        $output->writeln('  ' . str_repeat("\u{2500}", 17));

        if ($count === 0) {
            $output->writeln('  <info>None</info>');
            return;
        }

        foreach ($todos as $todo) {
            $context = $todo['title'] ?? 'standalone';
            $output->writeln(
                "  \u{2022} [{$todo['priority']}] {$todo['content']} (due: {$todo['due_date']}) -> {$context}",
            );
        }
    }

    private function renderDueToday(OutputInterface $output, string $today): void
    {
        $todos = $this->db->fetchAll(
            <<<'SQL'
                SELECT t.priority, t.content, t.due_date, t.doc_id, d.title
                FROM todos t
                LEFT JOIN documents d ON t.doc_id = d.id
                WHERE t.status = :status
                  AND t.due_date = :today
                ORDER BY t.priority ASC
            SQL,
            [':status' => 'open', ':today' => $today],
        );

        $count = count($todos);
        $output->writeln('');
        $output->writeln("  DUE TODAY ({$count})");
        $output->writeln('  ' . str_repeat("\u{2500}", 17));

        if ($count === 0) {
            $output->writeln('  <info>None</info>');
            return;
        }

        foreach ($todos as $todo) {
            $context = $todo['title'] ?? 'standalone';
            $output->writeln("  \u{2022} [{$todo['priority']}] {$todo['content']} -> {$context}");
        }
    }

    private function renderRevisitToday(OutputInterface $output, string $today): void
    {
        $documents = $this->db->fetchAll(
            <<<'SQL'
                SELECT id, title, status, domain
                FROM documents
                WHERE revisit_date <= :today
                  AND status NOT IN (:closed, :migrated, :built)
                ORDER BY revisit_date ASC
            SQL,
            [':today' => $today, ':closed' => 'closed', ':migrated' => 'migrated', ':built' => 'built'],
        );

        $count = count($documents);
        $output->writeln('');
        $output->writeln("  REVISIT TODAY ({$count})");
        $output->writeln('  ' . str_repeat("\u{2500}", 17));

        if ($count === 0) {
            $output->writeln('  <info>None</info>');
            return;
        }

        foreach ($documents as $doc) {
            $output->writeln(
                "  \u{2022} \"{$doc['title']}\" (status: {$doc['status']}, domain: {$doc['domain']})",
            );
        }
    }

    private function renderRecentActivity(OutputInterface $output): void
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-48 hours'));

        // Per-domain breakdown of created/updated/closed
        $rows = $this->db->fetchAll(
            <<<'SQL'
                SELECT
                    domain,
                    SUM(CASE WHEN created_at >= :cutoff1 THEN 1 ELSE 0 END) AS created,
                    SUM(CASE WHEN modified_at >= :cutoff2 AND created_at < :cutoff3 THEN 1 ELSE 0 END) AS updated,
                    SUM(CASE WHEN modified_at >= :cutoff4 AND status IN ('closed', 'migrated', 'built') THEN 1 ELSE 0 END) AS closed
                FROM documents
                WHERE modified_at >= :cutoff5 OR created_at >= :cutoff6
                GROUP BY domain
                ORDER BY domain
            SQL,
            [
                ':cutoff1' => $cutoff,
                ':cutoff2' => $cutoff,
                ':cutoff3' => $cutoff,
                ':cutoff4' => $cutoff,
                ':cutoff5' => $cutoff,
                ':cutoff6' => $cutoff,
            ],
        );

        $todosDone = (int) $this->db->fetchValue(
            <<<'SQL'
                SELECT COUNT(*) FROM todos
                WHERE status = :status
                  AND created_at >= :cutoff
            SQL,
            [':status' => 'done', ':cutoff' => $cutoff],
        );

        $output->writeln('');
        $output->writeln('  RECENT ACTIVITY (last 48h)');
        $output->writeln('  ' . str_repeat("\u{2500}", 17));

        if (count($rows) === 0 && $todosDone === 0) {
            $output->writeln('  <info>None</info>');
            return;
        }

        if (count($rows) > 0) {
            // Calculate column widths
            $domainWidth = max(6, ...array_map(fn ($r) => strlen((string) $r['domain']), $rows));

            $header = sprintf(
                '  %-' . $domainWidth . 's  %7s  %7s  %6s',
                'Domain',
                'Created',
                'Updated',
                'Closed',
            );
            $output->writeln($header);
            $output->writeln('  ' . str_repeat("\u{2500}", strlen(trim($header))));

            $totals = ['created' => 0, 'updated' => 0, 'closed' => 0];

            foreach ($rows as $row) {
                $output->writeln(sprintf(
                    '  %-' . $domainWidth . 's  %7d  %7d  %6d',
                    $row['domain'],
                    (int) $row['created'],
                    (int) $row['updated'],
                    (int) $row['closed'],
                ));
                $totals['created'] += (int) $row['created'];
                $totals['updated'] += (int) $row['updated'];
                $totals['closed'] += (int) $row['closed'];
            }

            if (count($rows) > 1) {
                $output->writeln('  ' . str_repeat("\u{2500}", strlen(trim($header))));
                $output->writeln(sprintf(
                    '  %-' . $domainWidth . 's  %7d  %7d  %6d',
                    'Total',
                    $totals['created'],
                    $totals['updated'],
                    $totals['closed'],
                ));
            }
        }

        if ($todosDone > 0) {
            $output->writeln("  \u{2022} {$todosDone} TODOs completed");
        }
    }

    private function renderVaultStats(OutputInterface $output): void
    {
        $total = (int) $this->db->fetchValue('SELECT COUNT(*) FROM documents');

        $active = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM documents WHERE status IN (:seed, :growing)',
            [':seed' => 'seed', ':growing' => 'growing'],
        );

        $seeds = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM documents WHERE status = :status',
            [':status' => 'seed'],
        );

        $dormant = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM documents WHERE status = :status',
            [':status' => 'dormant'],
        );

        $closed = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM documents WHERE status = :status',
            [':status' => 'closed'],
        );

        $orphans = (int) $this->db->fetchValue(
            <<<'SQL'
                SELECT COUNT(*) FROM documents d
                WHERE d.status NOT IN (:closed, :migrated, :built)
                  AND d.created_at < datetime('now', '-7 days')
                  AND NOT EXISTS (
                      SELECT 1 FROM links l
                      WHERE l.source_id = d.id OR l.target_id = d.id
                  )
            SQL,
            [':closed' => 'closed', ':migrated' => 'migrated', ':built' => 'built'],
        );

        $output->writeln('');
        $output->writeln('  VAULT STATS');
        $output->writeln('  ' . str_repeat("\u{2500}", 17));
        $output->writeln("  \u{2022} Total: {$total} | Active: {$active} | Seeds: {$seeds} | Dormant: {$dormant} | Closed: {$closed}");
        $output->writeln("  \u{2022} Orphaned ideas (>7 days, no links): {$orphans}");
    }
}
