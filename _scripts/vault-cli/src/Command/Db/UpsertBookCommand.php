<?php

declare(strict_types=1);

namespace Vault\Command\Db;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'db:upsert-book', description: 'Insert or replace a book record')]
final class UpsertBookCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Document ID for the book')
            ->addOption('author', null, InputOption::VALUE_REQUIRED, 'Book author')
            ->addOption('series-id', null, InputOption::VALUE_REQUIRED, 'Series document ID')
            ->addOption('series-order', null, InputOption::VALUE_REQUIRED, 'Order within the series')
            ->addOption('rating', null, InputOption::VALUE_REQUIRED, 'Rating value')
            ->addOption('cover-url', null, InputOption::VALUE_REQUIRED, 'URL to cover image');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');

        $this->db->execute(
            <<<'SQL'
                INSERT OR REPLACE INTO books (doc_id, author, series_id, series_order, rating, cover_url)
                VALUES (:doc_id, :author, :series_id, :series_order, :rating, :cover_url)
            SQL,
            [
                ':doc_id' => $id,
                ':author' => $input->getOption('author'),
                ':series_id' => $input->getOption('series-id'),
                ':series_order' => $input->getOption('series-order'),
                ':rating' => $input->getOption('rating'),
                ':cover_url' => $input->getOption('cover-url'),
            ],
        );

        $output->writeln("ok: upserted book '{$id}'");

        return Command::SUCCESS;
    }
}
