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

        // Movie read commands
        $this->addCommand(new Command\Movies\MoviesListCommand($db));
        $this->addCommand(new Command\Movies\MoviesDirectorCommand($db));
        $this->addCommand(new Command\Movies\MoviesRatingCommand($db));
        $this->addCommand(new Command\Movies\MoviesRecentCommand($db));
        $this->addCommand(new Command\Movies\MoviesStatsCommand($db));

        // TV show read commands
        $this->addCommand(new Command\Tv\TvListCommand($db));
        $this->addCommand(new Command\Tv\TvWatchingCommand($db));
        $this->addCommand(new Command\Tv\TvRatingCommand($db));
        $this->addCommand(new Command\Tv\TvStatsCommand($db));

        // Game read commands
        $this->addCommand(new Command\Games\GamesListCommand($db));
        $this->addCommand(new Command\Games\GamesPlayingCommand($db));
        $this->addCommand(new Command\Games\GamesBacklogCommand($db));
        $this->addCommand(new Command\Games\GamesRatingCommand($db));
        $this->addCommand(new Command\Games\GamesPlatformCommand($db));
        $this->addCommand(new Command\Games\GamesStatsCommand($db));

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
        $this->addCommand(new Command\Db\UpsertMovieCommand($db));
        $this->addCommand(new Command\Db\AddWatchCommand($db));
        $this->addCommand(new Command\Db\UpsertTvShowCommand($db));
        $this->addCommand(new Command\Db\UpsertGameCommand($db));
        $this->addCommand(new Command\Db\AddPlaySessionCommand($db));
    }
}
