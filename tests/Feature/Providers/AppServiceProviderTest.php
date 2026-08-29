<?php

namespace Tests\Feature\Providers;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AppServiceProvider installs two custom SQLite functions - regexp and an accent-insensitive
 * like - whenever a SQLite connection is established. The suite itself runs on MySQL, so those
 * closures are never reached by ordinary tests; these open a throwaway in-memory SQLite
 * connection so the registration path actually executes.
 *
 * The like override folds accents through \Normalizer, which resolves via
 * symfony/polyfill-intl-normalizer when ext-intl is absent, so it works on any supported box.
 */
class AppServiceProviderTest extends TestCase
{
    private const CONNECTION = 'sqlite_appserviceprovider_test';

    /**
     * Opens an isolated in-memory SQLite connection, which fires ConnectionEstablished and
     * so triggers the provider's function registration.
     */
    private function sqliteConnection(): \Illuminate\Database\Connection
    {
        config(['database.connections.' . self::CONNECTION => [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]]);

        DB::purge(self::CONNECTION);

        return DB::connection(self::CONNECTION);
    }

    public function tearDown(): void
    {
        DB::purge(self::CONNECTION);

        parent::tearDown();
    }

    public function testRegexpFunctionIsRegisteredOnSqliteConnections(): void
    {
        $connection = $this->sqliteConnection();

        $matched = $connection->selectOne("SELECT 'Jesus wept' REGEXP 'wept' AS hit");

        $this->assertSame(1, (int) $matched->hit);
    }

    public function testRegexpFunctionIsCaseInsensitive(): void
    {
        $connection = $this->sqliteConnection();

        $matched = $connection->selectOne("SELECT 'Jesus Wept' REGEXP 'wept' AS hit");

        $this->assertSame(1, (int) $matched->hit);
    }

    public function testRegexpFunctionReturnsFalseWhenThePatternDoesNotMatch(): void
    {
        $connection = $this->sqliteConnection();

        $matched = $connection->selectOne("SELECT 'Jesus wept' REGEXP 'rejoiced' AS hit");

        $this->assertSame(0, (int) $matched->hit);
    }

    /**
     * A NULL column must not match, rather than raising or matching the empty string -
     * SqlSearch relies on this when a verse column is absent.
     */
    public function testRegexpFunctionReturnsFalseForNull(): void
    {
        $connection = $this->sqliteConnection();

        $matched = $connection->selectOne("SELECT NULL REGEXP 'anything' AS hit");

        $this->assertSame(0, (int) $matched->hit);
    }

    /**
     * PDO::sqliteCreateFunction() is deprecated in PHP 8.5 and replaced by
     * Pdo\Sqlite::createFunction(); the helper picks whichever the runtime offers. Either way
     * it must leave a working function behind.
     */
    public function testSqliteCreateFunctionHelperRegistersACallableFunction(): void
    {
        $pdo      = new \PDO('sqlite::memory:');
        $provider = new AppServiceProvider($this->app);

        $method = new \ReflectionMethod(AppServiceProvider::class, 'sqliteCreateFunction');
        $method->invoke($provider, $pdo, 'bss_test_double', fn ($value) => $value * 2, 1);

        $this->assertSame(84, (int) $pdo->query('SELECT bss_test_double(42)')->fetchColumn());
    }

    public function testRegisterAddsNoBindings(): void
    {
        $provider = new AppServiceProvider($this->app);

        $this->assertNull($provider->register());
    }

    public function testLikeOverrideIsCaseInsensitive(): void
    {
        $connection = $this->sqliteConnection();

        $matched = $connection->selectOne("SELECT 'Jesus wept' LIKE '%WEPT%' AS hit");

        $this->assertSame(1, (int) $matched->hit);
    }

    /**
     * The point of the override: SQLite's built-in LIKE is accent-sensitive, so a search for
     * "Jesus" would miss an accented text. This mirrors MySQL's utf8mb4_unicode_ci behaviour.
     */
    public function testLikeOverrideIgnoresAccents(): void
    {
        $connection = $this->sqliteConnection();

        $matched = $connection->selectOne("SELECT 'Jésus' LIKE '%jesus%' AS hit");

        $this->assertSame(1, (int) $matched->hit);
    }

    public function testLikeOverrideTreatsUnderscoreAsASingleCharacterWildcard(): void
    {
        $connection = $this->sqliteConnection();

        $matched = $connection->selectOne("SELECT 'wept' LIKE 'we_t' AS hit");

        $this->assertSame(1, (int) $matched->hit);
    }

    public function testLikeOverrideDoesNotMatchADifferentWord(): void
    {
        $connection = $this->sqliteConnection();

        $matched = $connection->selectOne("SELECT 'Jesus wept' LIKE '%rejoiced%' AS hit");

        $this->assertSame(0, (int) $matched->hit);
    }

    public function testLikeOverrideReturnsFalseForNull(): void
    {
        $connection = $this->sqliteConnection();

        $matched = $connection->selectOne("SELECT NULL LIKE '%anything%' AS hit");

        $this->assertSame(0, (int) $matched->hit);
    }

    /**
     * Compiled patterns are cached per pattern string; reusing one must not corrupt the cache
     * or leak a previous result.
     */
    public function testLikeOverrideReusesItsCompiledPatternCache(): void
    {
        $connection = $this->sqliteConnection();

        $first  = $connection->selectOne("SELECT 'Jésus' LIKE '%jesus%' AS hit");
        $second = $connection->selectOne("SELECT 'Judas' LIKE '%jesus%' AS hit");
        $third  = $connection->selectOne("SELECT 'JESUS' LIKE '%jesus%' AS hit");

        $this->assertSame(1, (int) $first->hit);
        $this->assertSame(0, (int) $second->hit);
        $this->assertSame(1, (int) $third->hit);
    }
}
