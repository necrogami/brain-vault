<?php

declare(strict_types=1);

namespace Vault\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'integrity', description: 'Run integrity checks on the vault index')]
final class IntegrityCommand extends Command
{
    public function __construct(
        private readonly Database $db,
        private readonly string $projectRoot,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<comment>Vault Integrity Check</comment>');
        $output->writeln(str_repeat('-', 40));

        $hasErrors = false;

        $hasErrors = $this->checkDocumentFileCount($output) || $hasErrors;
        $hasErrors = $this->checkBrokenLinks($output) || $hasErrors;
        $this->listDomainSubdomainPairs($output);
        $this->showJournalMode($output);

        $output->writeln('');
        if ($hasErrors) {
            $output->writeln('<error>Integrity issues detected. Consider running "vault rebuild".</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>All checks passed.</info>');
        return Command::SUCCESS;
    }

    /**
     * @return bool True if an issue was detected.
     */
    private function checkDocumentFileCount(OutputInterface $output): bool
    {
        $dbCount = (int) $this->db->fetchValue('SELECT COUNT(*) FROM documents');

        $vaultDir = $this->projectRoot . '/vault';
        $fileCount = 0;

        if (is_dir($vaultDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($vaultDir, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if ($file->isFile() && $file->getExtension() === 'md') {
                    $fileCount++;
                }
            }
        }

        if ($dbCount === $fileCount) {
            $output->writeln(
                "\u{2713} Document count: DB ({$dbCount}) matches filesystem ({$fileCount})",
            );
            return false;
        }

        $output->writeln(
            "\u{2717} Document count mismatch: DB has {$dbCount}, filesystem has {$fileCount}",
        );
        return true;
    }

    /**
     * @return bool True if broken links were found.
     */
    private function checkBrokenLinks(OutputInterface $output): bool
    {
        $brokenLinks = $this->db->fetchAll(
            <<<'SQL'
                SELECT l.source_id, l.target_id, l.link_type
                FROM links l
                WHERE NOT EXISTS (
                    SELECT 1 FROM documents d WHERE d.id = l.target_id
                )
            SQL,
        );

        if ($brokenLinks === []) {
            $output->writeln("\u{2713} No broken links found");
            return false;
        }

        $output->writeln(sprintf("\u{2717} %d broken link(s) found:", count($brokenLinks)));
        foreach ($brokenLinks as $link) {
            $output->writeln(
                "    {$link['source_id']} --[{$link['link_type']}]--> {$link['target_id']} (missing)",
            );
        }

        return true;
    }

    private function listDomainSubdomainPairs(OutputInterface $output): void
    {
        $pairs = $this->db->fetchAll(
            <<<'SQL'
                SELECT DISTINCT domain, subdomain
                FROM documents
                ORDER BY domain ASC, subdomain ASC
            SQL,
        );

        $output->writeln('');
        $output->writeln(sprintf(
            '<comment>Domain/Subdomain pairs in DB (%d) -- verify against _registry/domains.yml:</comment>',
            count($pairs),
        ));

        foreach ($pairs as $pair) {
            $output->writeln("    {$pair['domain']}/{$pair['subdomain']}");
        }
    }

    private function showJournalMode(OutputInterface $output): void
    {
        $mode = (string) $this->db->fetchValue('PRAGMA journal_mode');
        $output->writeln('');
        $output->writeln("SQLite journal mode: {$mode}");
    }
}
