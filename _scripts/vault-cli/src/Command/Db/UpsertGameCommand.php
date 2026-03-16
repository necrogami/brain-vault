<?php

declare(strict_types=1);

namespace Vault\Command\Db;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'db:upsert-game', description: 'Insert or replace a game record')]
final class UpsertGameCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Document ID for the game')
            ->addOption('developer', null, InputOption::VALUE_REQUIRED, 'Game developer')
            ->addOption('publisher', null, InputOption::VALUE_REQUIRED, 'Game publisher')
            ->addOption('year', null, InputOption::VALUE_REQUIRED, 'Release year')
            ->addOption('genre', null, InputOption::VALUE_REQUIRED, 'Primary genre')
            ->addOption('platform', null, InputOption::VALUE_REQUIRED, 'Platform played on')
            ->addOption('hours-played', null, InputOption::VALUE_REQUIRED, 'Total hours played')
            ->addOption('rating', null, InputOption::VALUE_REQUIRED, 'Rating value')
            ->addOption('cover-url', null, InputOption::VALUE_REQUIRED, 'URL to cover image');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');

        $this->db->execute(
            <<<'SQL'
                INSERT OR REPLACE INTO games (doc_id, developer, publisher, year, genre, platform, hours_played, rating, cover_url)
                VALUES (:doc_id, :developer, :publisher, :year, :genre, :platform, :hours_played, :rating, :cover_url)
            SQL,
            [
                ':doc_id' => $id,
                ':developer' => $input->getOption('developer'),
                ':publisher' => $input->getOption('publisher'),
                ':year' => $input->getOption('year'),
                ':genre' => $input->getOption('genre'),
                ':platform' => $input->getOption('platform'),
                ':hours_played' => $input->getOption('hours-played'),
                ':rating' => $input->getOption('rating'),
                ':cover_url' => $input->getOption('cover-url'),
            ],
        );

        $output->writeln("ok: upserted game '{$id}'");

        return Command::SUCCESS;
    }
}
