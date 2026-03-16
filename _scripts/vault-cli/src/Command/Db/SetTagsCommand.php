<?php

declare(strict_types=1);

namespace Vault\Command\Db;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'db:set-tags', description: 'Set tags for a document (replaces existing tags)')]
final class SetTagsCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Document ID')
            ->addOption('tags', null, InputOption::VALUE_REQUIRED, 'Comma-separated list of tags');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        $tagsRaw = $input->getOption('tags');

        $tags = array_filter(
            array_map('trim', explode(',', $tagsRaw)),
            static fn (string $tag): bool => $tag !== '',
        );

        $this->db->execute(
            'DELETE FROM tags WHERE doc_id = :id',
            [':id' => $id],
        );

        foreach ($tags as $tag) {
            $this->db->execute(
                'INSERT INTO tags (doc_id, tag) VALUES (:doc_id, :tag)',
                [':doc_id' => $id, ':tag' => $tag],
            );
        }

        $count = count($tags);
        $output->writeln("ok: set {$count} tags on '{$id}'");

        return Command::SUCCESS;
    }
}
