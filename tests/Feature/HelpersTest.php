<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

use App\Helpers;

class HelpersTest extends TestCase
{
    /**
     * SQLITE_MAX_VARIABLE_NUMBER is compile-time configurable, so the build's own reported
     * value wins over the default for its version.
     */
    public function testGetMaxBoundVariablesForSqlite() 
    {
        $this->_defineSqliteProbeConnection();

        $compiled = NULL;

        foreach(Helpers::getSqliteCompileOptions('bound_variable_probe') as $option) {
            if(preg_match('/^MAX_VARIABLE_NUMBER=([0-9]+)$/', $option, $match)) {
                $compiled = (int) $match[1];
            }
        }

        if($compiled === NULL) {
            // A build that leaves the option at its default does not list it, so the documented
            // default for the engine version is the only thing left to go on.
            $version  = \DB::connection('bound_variable_probe')->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
            $expected = version_compare($version, '3.32.0', '>=') ? 32766 : 999;
        }
        else {
            $expected = $compiled;
        }

        $this->assertEquals($expected, Helpers::getMaxBoundVariables('bound_variable_probe'));

        \DB::disconnect('bound_variable_probe');
    }

    /**
     * The reported ceiling must be the real one: a statement binding exactly that many
     * variables has to execute, which the version-derived default alone cannot guarantee on a
     * build compiled away from it.
     */
    public function testGetMaxBoundVariablesMatchesWhatTheBuildActuallyAccepts() 
    {
        $this->_defineSqliteProbeConnection();

        $max = Helpers::getMaxBoundVariables('bound_variable_probe');

        \Schema::connection('bound_variable_probe')->create('ceiling_probe', function($table) {
            $table->integer('a');
            $table->integer('b');
        });

        // Two columns per row, so this binds the ceiling exactly. Capped so the assertion stays
        // cheap on builds that report a very large ceiling.
        $rows_at_ceiling = min(intdiv($max, 2), 25000);
        $rows = array_fill(0, $rows_at_ceiling, ['a' => 1, 'b' => 2]);

        \DB::connection('bound_variable_probe')->table('ceiling_probe')->insert($rows);

        $this->assertEquals($rows_at_ceiling, \DB::connection('bound_variable_probe')->table('ceiling_probe')->count());

        \DB::disconnect('bound_variable_probe');
    }

    public function testGetSqliteCompileOptionsReturnsEmptyForNonSqlite() 
    {
        if(\DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('Default connection is SQLite');
        }

        $this->assertEquals([], Helpers::getSqliteCompileOptions());
    }

    public function testGetMaxBoundVariablesForNonSqliteDrivers() 
    {
        if(\DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('Default connection is SQLite; covered by the SQLite case');
        }

        $this->assertEquals(65535, Helpers::getMaxBoundVariables());
    }

    /**
     * A batch must stay inside the connection's own bound-variable ceiling, and must never
     * exceed the requested maximum or collapse to zero rows.
     */
    public function testGetInsertChunkSizeStaysWithinTheConnectionCeiling() 
    {
        $this->_defineSqliteProbeConnection();

        $max = Helpers::getMaxBoundVariables('bound_variable_probe');

        foreach([1, 4, 7, 11, 40] as $columns) {
            $chunk = Helpers::getInsertChunkSize($columns, 'bound_variable_probe');

            $this->assertEquals(min(1000, intdiv($max, $columns)), $chunk, $columns . ' columns');
            $this->assertLessThanOrEqual($max, $chunk * $columns, $columns . ' columns exceeds the ceiling');
        }

        // The requested maximum is a ceiling, not a target.
        $this->assertEquals(25, Helpers::getInsertChunkSize(4, 'bound_variable_probe', 25));

        \DB::disconnect('bound_variable_probe');
    }

    /**
     * A row that cannot fit the ceiling on its own has no valid batch size - not even one row -
     * so the caller has to hear about it rather than get a number guaranteed to fail.
     */
    public function testGetInsertChunkSizeRejectsARowWiderThanTheCeiling() 
    {
        $this->_defineSqliteProbeConnection();

        $max = Helpers::getMaxBoundVariables('bound_variable_probe');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('more than this connection permits in one statement');

        Helpers::getInsertChunkSize($max + 1, 'bound_variable_probe');
    }

    public function testGetInsertChunkSizeRejectsANonPositiveColumnCount() 
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Columns per row must be at least 1');

        Helpers::getInsertChunkSize(0);
    }

    /**
     * A batch of this size really does execute: proof that the derived ceiling is usable and
     * not just arithmetic. 4 columns matches the SQLite renderer's verses table.
     */
    public function testGetInsertChunkSizeProducesAnExecutableBatch() 
    {
        $this->_defineSqliteProbeConnection();

        \Schema::connection('bound_variable_probe')->create('chunk_probe', function($table) {
            $table->integer('book');
            $table->integer('chapter');
            $table->integer('verse');
            $table->text('text');
        });

        $chunk = Helpers::getInsertChunkSize(4, 'bound_variable_probe');
        $rows  = [];

        for($i = 0; $i < $chunk; $i ++) {
            $rows[] = ['book' => 1, 'chapter' => 1, 'verse' => $i + 1, 'text' => 'verse ' . $i];
        }

        \DB::connection('bound_variable_probe')->table('chunk_probe')->insert($rows);

        $this->assertEquals($chunk, \DB::connection('bound_variable_probe')->table('chunk_probe')->count());

        \DB::disconnect('bound_variable_probe');
    }

    #[DataProvider('makeDataProvider')]
    public function testMake(string $class) 
    {
        $Object = Helpers::make($class);
        $this->assertInstanceOf($class, $Object, "Could not instantiate: {$class}");
    }

    public static function makeDataProvider()
    {
        return [
            ['App\Engine'],
            ['App\Models\Bible'],
            ['App\ImportManager'],
            ['App\InstallManager'],
            ['App\Search'],
            ['App\Passage'],
        ];
    }

    /** An in-memory SQLite connection for probing the local build's limits. */
    private function _defineSqliteProbeConnection(): void
    {
        config(['database.connections.bound_variable_probe' => [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]]);
    }

    /**
     * The ceiling is a compile-time constant of the loaded SQLite build (and a fixed number for
     * every other driver), so probing it once per process is enough. It is asked for once per
     * buffer flush during an import - roughly 155 times on a 31k-verse module - and each probe
     * is a PRAGMA plus a PDO attribute read.
     */
    public function testGetMaxBoundVariablesIsProbedOncePerConnection()
    {
        $this->_defineSqliteProbeConnection();
        Helpers::clearMaxBoundVariablesCache();

        $Connection = \DB::connection('bound_variable_probe');
        $Connection->flushQueryLog();
        $Connection->enableQueryLog();

        try {
            $first   = Helpers::getMaxBoundVariables('bound_variable_probe');
            $probed  = count($Connection->getQueryLog());

            $this->assertGreaterThan(0, $probed, 'The first call did not probe the connection');

            for($i = 0; $i < 5; $i++) {
                $this->assertEquals($first, Helpers::getMaxBoundVariables('bound_variable_probe'));
            }

            $this->assertCount($probed, $Connection->getQueryLog(), 'The ceiling was re-probed on a later call');

            // getInsertChunkSize() goes through the same memo, so it costs nothing per flush.
            Helpers::getInsertChunkSize(4, 'bound_variable_probe');

            $this->assertCount($probed, $Connection->getQueryLog(), 'getInsertChunkSize() re-probed the ceiling');

            // A reconfigured connection has to be answerable again.
            Helpers::clearMaxBoundVariablesCache();
            $this->assertEquals($first, Helpers::getMaxBoundVariables('bound_variable_probe'));
            $this->assertGreaterThan($probed, count($Connection->getQueryLog()), 'The cache was not cleared');
        }
        finally {
            $Connection->disableQueryLog();
            $Connection->flushQueryLog();
            Helpers::clearMaxBoundVariablesCache();
        }
    }
}
