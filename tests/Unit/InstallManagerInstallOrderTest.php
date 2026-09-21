<?php

namespace Tests\Unit;

use App\InstallManager;
use PHPUnit\Framework\TestCase;

/**
 * The order of install() is load bearing, and nothing else can assert it: running the method
 * means running key:generate and migrate --force against a live database, so the ordering is
 * read out of the source instead.
 *
 * Everything that can time out - installing the default Bible under the 5 minute limit, then
 * building the book list tables - has to happen before the administrator exists. A failure
 * there must leave no account behind, because the user count guard refuses to run against a
 * database that has one, and there is no way to remove it from the browser.
 */
class InstallManagerInstallOrderTest extends TestCase
{
    /**
     * @return string the body of App\InstallManager::install()
     */
    protected function installSource(): string
    {
        $method = new \ReflectionMethod(InstallManager::class, 'install');

        $lines = file($method->getFileName());

        return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }

    public function testTheAdministratorIsCreatedAfterEverythingThatCanTimeOut(): void
    {
        $source = $this->installSource();

        // Qualified, so the reference to it in the comment above key:generate is not what
        // gets found.
        $administrator = strpos($source, 'static::createAdministratorAndMarkInstalled(');
        $bible         = strpos($source, '$Bible->install(');
        $book_lists    = strpos($source, 'createTableAndMigrateFromCsv(');

        $this->assertIsInt($administrator, 'install() has to finish through createAdministratorAndMarkInstalled()');
        $this->assertIsInt($bible, 'install() has to install the default Bible');
        $this->assertIsInt($book_lists, 'install() has to build the book lists');

        $this->assertGreaterThan($bible, $administrator, 'a Bible install that times out must not leave an administrator behind');
        $this->assertGreaterThan($book_lists, $administrator, 'a failed book list build must not leave an administrator behind');
    }

    /**
     * The guard that reads the user count has to sit above key:generate: rotating APP_KEY is
     * itself destructive on a live site whose app.installed value has gone missing.
     */
    public function testTheUserCountGuardRunsBeforeTheApplicationKeyIsRotated(): void
    {
        $source = $this->installSource();

        $guard = strpos($source, '$user_count = static::existingUserCount();');
        $key   = strpos($source, "'key:generate'");

        $this->assertIsInt($guard);
        $this->assertIsInt($key);

        $this->assertLessThan($key, $guard, 'the key must not be rotated before the database is known to be fresh');
    }
}
