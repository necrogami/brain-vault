<?php

declare(strict_types=1);

namespace Vault\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'init', description: 'Initialize the vault — validate environment, create database, and rebuild index')]
final class InitCommand extends Command
{
    private const string MIN_PHP_VERSION = '8.3.0';

    public function __construct(
        private readonly string $projectRoot,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<info>Initializing vault...</info>');
        $output->writeln('');

        // 1. Validate environment
        $envOk = $this->validateEnvironment($output);

        if (!$envOk) {
            return Command::FAILURE;
        }

        // 2. Create database if it doesn't exist
        $dbCreated = $this->ensureDatabase($output);

        if ($dbCreated === null) {
            return Command::FAILURE;
        }

        // 3. Run rebuild to populate from vault files
        $output->writeln('');
        $output->writeln('<info>Running index rebuild...</info>');

        $dbPath = $this->projectRoot . '/_index/vault.db';
        $db = new \Vault\Database($dbPath);
        $rebuildCommand = new RebuildCommand($db, $this->projectRoot);
        $rebuildResult = $rebuildCommand->run(new ArrayInput([]), $output);

        if ($rebuildResult !== Command::SUCCESS) {
            return $rebuildResult;
        }

        // 4. Summary
        $output->writeln('');
        $output->writeln('<info>Vault initialized successfully.</info>');

        return Command::SUCCESS;
    }

    private function validateEnvironment(OutputInterface $output): bool
    {
        $allOk = true;

        // PHP version
        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>=')) {
            $output->writeln('  <info>✓</info> PHP ' . PHP_VERSION);
        } else {
            $output->writeln('  <error>✗</error> PHP ' . PHP_VERSION . ' (requires >= ' . self::MIN_PHP_VERSION . ')');
            $allOk = false;
        }

        // pdo_sqlite extension
        if (extension_loaded('pdo_sqlite')) {
            $output->writeln('  <info>✓</info> pdo_sqlite extension');
        } else {
            $output->writeln('  <error>✗</error> pdo_sqlite extension not loaded');
            $allOk = false;
        }

        // Git
        $gitVersion = trim((string) shell_exec('git --version 2>/dev/null'));

        if ($gitVersion !== '') {
            $output->writeln('  <info>✓</info> ' . $gitVersion);
        } else {
            $output->writeln('  <error>✗</error> git not found');
            $allOk = false;
        }

        // Schema file
        $schemaPath = $this->projectRoot . '/_index/schema.sql';

        if (file_exists($schemaPath)) {
            $output->writeln('  <info>✓</info> Schema file found');
        } else {
            $output->writeln('  <error>✗</error> Schema file not found at _index/schema.sql');
            $allOk = false;
        }

        // Registry file
        $registryPath = $this->projectRoot . '/_registry/domains.yml';

        if (file_exists($registryPath)) {
            $output->writeln('  <info>✓</info> Domain registry found');
        } else {
            $output->writeln('  <comment>⚠</comment> Domain registry not found at _registry/domains.yml');
        }

        return $allOk;
    }

    /**
     * @return bool|null True if created, false if already existed, null on failure
     */
    private function ensureDatabase(OutputInterface $output): ?bool
    {
        $dbPath = $this->projectRoot . '/_index/vault.db';

        if (file_exists($dbPath)) {
            $output->writeln('  <info>✓</info> Database already exists');

            return false;
        }

        $schemaPath = $this->projectRoot . '/_index/schema.sql';
        $schemaSql = file_get_contents($schemaPath);

        if ($schemaSql === false) {
            $output->writeln('  <error>✗</error> Failed to read schema file');

            return null;
        }

        // Ensure _index directory exists
        $indexDir = dirname($dbPath);

        if (!is_dir($indexDir)) {
            mkdir($indexDir, 0755, true);
        }

        // Create the database and apply schema
        try {
            $pdo = new \PDO("sqlite:{$dbPath}", options: [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec($schemaSql);
            $pdo = null;
        } catch (\Throwable $e) {
            $output->writeln('  <error>✗</error> Failed to create database: ' . $e->getMessage());

            return null;
        }

        $output->writeln('  <info>✓</info> Database created at _index/vault.db');

        return true;
    }
}
