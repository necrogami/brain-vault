<?php

declare(strict_types=1);

namespace Vault;

use Symfony\Component\Console\Application as BaseApplication;

final class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('vault', '1.0.0');

        $projectRoot = $this->resolveProjectRoot();
        $dbPath = $projectRoot . '/_index/vault.db';

        // Init command always available — it creates the DB
        $this->addCommand(new Command\InitCommand($projectRoot));

        // All other commands require the DB to exist
        if (file_exists($dbPath)) {
            $db = new Database($dbPath);
            $this->registerCommands($db, $projectRoot);
        }
    }

    private function resolveProjectRoot(): string
    {
        // Walk up from script location to find the project root (contains _index/)
        $dir = dirname(__DIR__);
        while ($dir !== '/') {
            if (is_dir($dir . '/_index')) {
                return $dir;
            }
            $dir = dirname($dir);
        }

        // Fallback: assume two levels up from vault-cli/
        return dirname(__DIR__, 2);
    }

    private function registerCommands(Database $db, string $projectRoot): void
    {
        // Read commands
        $this->addCommand(new Command\BriefingCommand($db));
        $this->addCommand(new Command\TodosCommand($db));
        $this->addCommand(new Command\SearchCommand($db));
        $this->addCommand(new Command\StatsCommand($db));
        $this->addCommand(new Command\RecentCommand($db));
        $this->addCommand(new Command\OrphansCommand($db));
        $this->addCommand(new Command\IntegrityCommand($db, $projectRoot));
        $this->addCommand(new Command\RebuildCommand($db, $projectRoot));
        $this->addCommand(new Command\ReviewCommand($db));
        $this->addCommand(new Command\MetricsCommand($db));
        $this->addCommand(new Command\SuggestLinksCommand($db));

        // Book read commands
        $this->addCommand(new Command\Books\BooksListCommand($db));
        $this->addCommand(new Command\Books\BooksAuthorCommand($db));
        $this->addCommand(new Command\Books\BooksSeriesCommand($db));
        $this->addCommand(new Command\Books\BooksRatingCommand($db));
        $this->addCommand(new Command\Books\BooksRecentCommand($db));
        $this->addCommand(new Command\Books\BooksStatsCommand($db));

        // Write commands
        $this->addCommand(new Command\Db\UpsertDocCommand($db));
        $this->addCommand(new Command\Db\SetTagsCommand($db));
        $this->addCommand(new Command\Db\AddLinkCommand($db));
        $this->addCommand(new Command\Db\UpsertBookCommand($db));
        $this->addCommand(new Command\Db\AddReadCommand($db));
        $this->addCommand(new Command\Db\UpdateStatusCommand($db));
        $this->addCommand(new Command\Db\AddSourceCommand($db));
        $this->addCommand(new Command\Db\AddTodoCommand($db));
        $this->addCommand(new Command\Db\AddExternalRefCommand($db));
        $this->addCommand(new Command\Db\CloseDocCommand($db));
    }
}
