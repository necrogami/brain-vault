<?php

declare(strict_types=1);

namespace Vault\Command\Db;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\Database;

#[AsCommand(name: 'db:add-link', description: 'Add or replace a link between two documents')]
final class AddLinkCommand extends Command
{
    public function __construct(private readonly Database $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Source document ID')
            ->addOption('target', null, InputOption::VALUE_REQUIRED, 'Target document ID')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Link type (related, parent, children, etc.)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = $input->getOption('source');
        $target = $input->getOption('target');
        $type = $input->getOption('type');

        $this->db->execute(
            <<<'SQL'
                INSERT OR REPLACE INTO links (source_id, target_id, link_type)
                VALUES (:source_id, :target_id, :link_type)
            SQL,
            [
                ':source_id' => $source,
                ':target_id' => $target,
                ':link_type' => $type,
            ],
        );

        $output->writeln("ok: link {$source} --[{$type}]--> {$target}");

        return Command::SUCCESS;
    }
}
