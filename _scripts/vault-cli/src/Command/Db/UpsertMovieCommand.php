<?php

declare(strict_types=1);

namespace Vault\Command\Db;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'db:upsert-movie', description: 'Insert or replace a movie record')]
final class UpsertMovieCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Document ID for the movie')
            ->addOption('director', null, InputOption::VALUE_REQUIRED, 'Movie director')
            ->addOption('year', null, InputOption::VALUE_REQUIRED, 'Release year')
            ->addOption('genre', null, InputOption::VALUE_REQUIRED, 'Primary genre')
            ->addOption('runtime-min', null, InputOption::VALUE_REQUIRED, 'Runtime in minutes')
            ->addOption('rating', null, InputOption::VALUE_REQUIRED, 'Rating value')
            ->addOption('poster-url', null, InputOption::VALUE_REQUIRED, 'URL to poster image')
            ->addOption('imdb-id', null, InputOption::VALUE_REQUIRED, 'IMDb ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');

        $this->db->execute(
            <<<'SQL'
                INSERT OR REPLACE INTO movies (doc_id, director, year, genre, runtime_min, rating, poster_url, imdb_id)
                VALUES (:doc_id, :director, :year, :genre, :runtime_min, :rating, :poster_url, :imdb_id)
            SQL,
            [
                ':doc_id' => $id,
                ':director' => $input->getOption('director'),
                ':year' => $input->getOption('year'),
                ':genre' => $input->getOption('genre'),
                ':runtime_min' => $input->getOption('runtime-min'),
                ':rating' => $input->getOption('rating'),
                ':poster_url' => $input->getOption('poster-url'),
                ':imdb_id' => $input->getOption('imdb-id'),
            ],
        );

        $output->writeln("ok: upserted movie '{$id}'");

        return Command::SUCCESS;
    }
}
