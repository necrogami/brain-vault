<?php

declare(strict_types=1);

namespace Vault\Command\Db;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'db:upsert-tv', description: 'Insert or replace a TV show record')]
final class UpsertTvShowCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Document ID for the TV show')
            ->addOption('creator', null, InputOption::VALUE_REQUIRED, 'Show creator/showrunner')
            ->addOption('year-start', null, InputOption::VALUE_REQUIRED, 'Year first aired')
            ->addOption('year-end', null, InputOption::VALUE_REQUIRED, 'Year ended (null if ongoing)')
            ->addOption('genre', null, InputOption::VALUE_REQUIRED, 'Primary genre')
            ->addOption('total-seasons', null, InputOption::VALUE_REQUIRED, 'Total number of seasons')
            ->addOption('seasons-watched', null, InputOption::VALUE_REQUIRED, 'Number of seasons watched')
            ->addOption('rating', null, InputOption::VALUE_REQUIRED, 'Rating value')
            ->addOption('poster-url', null, InputOption::VALUE_REQUIRED, 'URL to poster image')
            ->addOption('imdb-id', null, InputOption::VALUE_REQUIRED, 'IMDb ID for external lookups');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');

        $this->db->execute(
            <<<'SQL'
                INSERT OR REPLACE INTO tv_shows
                    (doc_id, creator, year_start, year_end, genre, total_seasons,
                     seasons_watched, rating, poster_url, imdb_id)
                VALUES
                    (:doc_id, :creator, :year_start, :year_end, :genre, :total_seasons,
                     :seasons_watched, :rating, :poster_url, :imdb_id)
            SQL,
            [
                ':doc_id' => $id,
                ':creator' => $input->getOption('creator'),
                ':year_start' => $input->getOption('year-start'),
                ':year_end' => $input->getOption('year-end'),
                ':genre' => $input->getOption('genre'),
                ':total_seasons' => $input->getOption('total-seasons'),
                ':seasons_watched' => $input->getOption('seasons-watched'),
                ':rating' => $input->getOption('rating'),
                ':poster_url' => $input->getOption('poster-url'),
                ':imdb_id' => $input->getOption('imdb-id'),
            ],
        );

        $output->writeln("ok: upserted tv show '{$id}'");

        return Command::SUCCESS;
    }
}
