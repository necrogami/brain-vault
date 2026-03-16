<?php

declare(strict_types=1);

namespace Vault\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'search', description: 'Full-text and tag search across the vault')]
final class SearchCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('query', InputArgument::REQUIRED, 'Search query string');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $query = $input->getArgument('query');

        $this->renderFtsResults($output, $query);
        $this->renderTagResults($output, $query);

        return Command::SUCCESS;
    }

    private function renderFtsResults(OutputInterface $output, string $query): void
    {
        $output->writeln('');
        $output->writeln('<comment>Full-Text Search Results</comment>');

        $results = $this->db->fetchAll(
            <<<'SQL'
                SELECT d.id, d.title, d.status, d.domain, d.subdomain, d.summary
                FROM documents_fts fts
                JOIN documents d ON fts.id = d.id
                WHERE documents_fts MATCH :query
                ORDER BY rank
            SQL,
            [':query' => $query],
        );

        if ($results === []) {
            $output->writeln('<info>No full-text matches found.</info>');
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Title', 'Status', 'Path', 'Summary']);

        foreach ($results as $row) {
            $path = "{$row['domain']}/{$row['subdomain']}";
            $summary = $row['summary'] !== null
                ? mb_substr($row['summary'], 0, 60) . (mb_strlen($row['summary'] ?? '') > 60 ? '...' : '')
                : '-';

            $table->addRow([
                $row['id'],
                $row['title'],
                $row['status'],
                $path,
                $summary,
            ]);
        }

        $table->render();
        $output->writeln(sprintf('<info>%d match(es) found.</info>', count($results)));
    }

    private function renderTagResults(OutputInterface $output, string $query): void
    {
        $output->writeln('');
        $output->writeln('<comment>Tag Search Results</comment>');

        $pattern = '%' . mb_strtolower($query) . '%';

        $results = $this->db->fetchAll(
            <<<'SQL'
                SELECT d.id, d.title, d.status, d.domain, d.subdomain, t.tag
                FROM tags t
                JOIN documents d ON t.doc_id = d.id
                WHERE t.tag LIKE :pattern
                ORDER BY t.tag ASC, d.title ASC
            SQL,
            [':pattern' => $pattern],
        );

        if ($results === []) {
            $output->writeln('<info>No tag matches found.</info>');
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Title', 'Status', 'Path', 'Tag']);

        foreach ($results as $row) {
            $path = "{$row['domain']}/{$row['subdomain']}";

            $table->addRow([
                $row['id'],
                $row['title'],
                $row['status'],
                $path,
                $row['tag'],
            ]);
        }

        $table->render();
        $output->writeln(sprintf('<info>%d match(es) found.</info>', count($results)));
    }
}
