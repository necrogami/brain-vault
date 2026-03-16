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

#[AsCommand(name: 'metrics', description: 'Vault flow metrics — creation, conversion, velocity, and trends')]
final class MetricsCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('period', null, InputOption::VALUE_REQUIRED, 'Number of days to analyze', '30')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Analyze all time');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $allTime = $input->getOption('all');
        $days = (int) $input->getOption('period');
        $cutoff = $allTime ? '1970-01-01' : date('Y-m-d', strtotime("-{$days} days"));
        $label = $allTime ? 'all time' : "last {$days} days";

        $output->writeln('');
        $output->writeln("VAULT METRICS — {$label}");
        $output->writeln('');

        $this->renderFlow($output, $cutoff);
        $this->renderFunnel($output);
        $this->renderVelocity($output);
        $this->renderDomainFocus($output, $cutoff);
        $this->renderTagTrends($output, $cutoff);
        $this->renderBooks($output, $cutoff);

        return Command::SUCCESS;
    }

    private function renderFlow(OutputInterface $output, string $cutoff): void
    {
        $created = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM documents WHERE created_at >= :cutoff',
            [':cutoff' => $cutoff],
        );
        $closed = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM documents WHERE status IN ('closed', 'migrated', 'built') AND modified_at >= :cutoff",
            [':cutoff' => $cutoff],
        );
        $built = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM documents WHERE status = 'built' AND modified_at >= :cutoff",
            [':cutoff' => $cutoff],
        );

        $output->writeln('  FLOW');
        $output->writeln("  Created: {$created} | Closed: {$closed} | Built: {$built}");
        $output->writeln('');
    }

    private function renderFunnel(OutputInterface $output): void
    {
        $output->writeln('  FUNNEL (all-time counts)');

        $rows = $this->db->fetchAll(
            'SELECT status, COUNT(*) as count FROM documents GROUP BY status ORDER BY count DESC',
        );

        $total = array_sum(array_column($rows, 'count'));

        $table = new Table($output);
        $table->setHeaders(['Status', 'Count', '%']);

        foreach ($rows as $row) {
            $pct = $total > 0 ? round(($row['count'] / $total) * 100, 1) : 0;
            $table->addRow([$row['status'], $row['count'], "{$pct}%"]);
        }

        $table->render();
        $output->writeln('');
    }

    private function renderVelocity(OutputInterface $output): void
    {
        $output->writeln('  VELOCITY (avg days in status)');

        $rows = $this->db->fetchAll(
            "SELECT status, ROUND(AVG(julianday(modified_at) - julianday(created_at)), 1) AS avg_days FROM documents WHERE status NOT IN ('seed') GROUP BY status ORDER BY avg_days DESC",
        );

        if ($rows === []) {
            $output->writeln('  No data yet.');
            $output->writeln('');
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Status', 'Avg Days']);

        foreach ($rows as $row) {
            $table->addRow([$row['status'], $row['avg_days']]);
        }

        $table->render();
        $output->writeln('');
    }

    private function renderDomainFocus(OutputInterface $output, string $cutoff): void
    {
        $output->writeln('  DOMAIN FOCUS');

        $rows = $this->db->fetchAll(
            'SELECT domain, COUNT(*) as count FROM documents WHERE created_at >= :cutoff GROUP BY domain ORDER BY count DESC',
            [':cutoff' => $cutoff],
        );

        if ($rows === []) {
            $output->writeln('  No activity in period.');
            $output->writeln('');
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Domain', 'Ideas Created']);

        foreach ($rows as $row) {
            $table->addRow([$row['domain'], $row['count']]);
        }

        $table->render();
        $output->writeln('');
    }

    private function renderTagTrends(OutputInterface $output, string $cutoff): void
    {
        $output->writeln('  TAG TRENDS (top 10)');

        $rows = $this->db->fetchAll(
            'SELECT t.tag, COUNT(*) as count FROM tags t JOIN documents d ON t.doc_id = d.id WHERE d.created_at >= :cutoff GROUP BY t.tag ORDER BY count DESC LIMIT 10',
            [':cutoff' => $cutoff],
        );

        if ($rows === []) {
            $output->writeln('  No tags in period.');
            $output->writeln('');
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Tag', 'Count']);

        foreach ($rows as $row) {
            $table->addRow([$row['tag'], $row['count']]);
        }

        $table->render();
        $output->writeln('');
    }

    private function renderBooks(OutputInterface $output, string $cutoff): void
    {
        $output->writeln('  BOOKS');

        $booksRead = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM reads WHERE date_read >= :cutoff',
            [':cutoff' => $cutoff],
        );

        $output->writeln("  Books read in period: {$booksRead}");

        $ratings = $this->db->fetchAll(
            'SELECT b.rating, COUNT(*) as count FROM reads r JOIN books b ON r.doc_id = b.doc_id WHERE r.date_read >= :cutoff AND b.rating IS NOT NULL GROUP BY b.rating ORDER BY b.rating',
            [':cutoff' => $cutoff],
        );

        if ($ratings !== []) {
            $parts = array_map(fn($r) => "{$r['rating']}: {$r['count']}", $ratings);
            $output->writeln('  Ratings: ' . implode(' | ', $parts));
        }

        $output->writeln('');
    }
}
