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

#[AsCommand(name: 'suggest-links', description: 'Find potential cross-domain connections based on shared tags')]
final class SuggestLinksCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('id', null, InputOption::VALUE_REQUIRED, 'Only show suggestions for this document');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetId = $input->getOption('id');
        $output->writeln('');
        $output->writeln('SUGGESTED LINKS — cross-domain connections');
        $output->writeln('');

        $params = [];
        $idFilter = '';
        if ($targetId !== null) {
            $idFilter = 'AND (d1.id = :target_id OR d2.id = :target_id)';
            $params[':target_id'] = $targetId;
        }

        $rows = $this->db->fetchAll(
            "SELECT d1.id AS id1, d1.title AS title1, d1.domain AS domain1,
                    d2.id AS id2, d2.title AS title2, d2.domain AS domain2,
                    GROUP_CONCAT(t1.tag, ', ') AS shared_tags,
                    COUNT(t1.tag) AS tag_count
             FROM tags t1
             JOIN tags t2 ON t1.tag = t2.tag AND t1.doc_id < t2.doc_id
             JOIN documents d1 ON t1.doc_id = d1.id
             JOIN documents d2 ON t2.doc_id = d2.id
             LEFT JOIN links l ON (l.source_id = d1.id AND l.target_id = d2.id)
                 OR (l.source_id = d2.id AND l.target_id = d1.id)
             WHERE d1.domain != d2.domain
               AND l.source_id IS NULL
               AND d1.status NOT IN ('closed', 'migrated', 'built')
               AND d2.status NOT IN ('closed', 'migrated', 'built')
               {$idFilter}
             GROUP BY d1.id, d2.id
             HAVING COUNT(t1.tag) >= 2
             ORDER BY tag_count DESC
             LIMIT 20",
            $params,
        );

        if ($rows === []) {
            $output->writeln('  No suggestions found.');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Document A', 'Domain', 'Document B', 'Domain', 'Shared Tags']);
        foreach ($rows as $row) {
            $table->addRow([$row['title1'], $row['domain1'], $row['title2'], $row['domain2'], $row['shared_tags']]);
        }
        $table->render();
        $output->writeln(sprintf('%d suggestion(s) found.', count($rows)));

        return Command::SUCCESS;
    }
}
