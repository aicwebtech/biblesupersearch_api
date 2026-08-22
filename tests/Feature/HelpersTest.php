<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

use App\Helpers;

class HelpersTest extends TestCase
{
    /**
     * SQLite does not report SQLITE_MAX_VARIABLE_NUMBER to the driver, so the ceiling is taken
     * from the engine version: 3.32.0 raised the default from 999 to 32766.
     */
    public function testGetMaxBoundVariablesForSqlite() 
    {
        config(['database.connections.bound_variable_probe' => [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]]);

        $version  = \DB::connection('bound_variable_probe')->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        $expected = version_compare($version, '3.32.0', '>=') ? 32766 : 999;

        $this->assertEquals($expected, Helpers::getMaxBoundVariables('bound_variable_probe'));

        \DB::disconnect('bound_variable_probe');
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
        config(['database.connections.bound_variable_probe' => [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]]);

        $max = Helpers::getMaxBoundVariables('bound_variable_probe');

        foreach([1, 4, 7, 11, 40] as $columns) {
            $chunk = Helpers::getInsertChunkSize($columns, 'bound_variable_probe');

            $this->assertEquals(min(1000, intdiv($max, $columns)), $chunk, $columns . ' columns');
            $this->assertLessThanOrEqual($max, $chunk * $columns, $columns . ' columns exceeds the ceiling');
        }

        // A column count no batch can satisfy still yields a usable single-row batch.
        $this->assertEquals(1, Helpers::getInsertChunkSize($max + 1, 'bound_variable_probe'));

        // The requested maximum is a ceiling, not a target.
        $this->assertEquals(25, Helpers::getInsertChunkSize(4, 'bound_variable_probe', 25));

        \DB::disconnect('bound_variable_probe');
    }

    /**
     * A batch of this size really does execute: proof that the derived ceiling is usable and
     * not just arithmetic. 4 columns matches the SQLite renderer's verses table.
     */
    public function testGetInsertChunkSizeProducesAnExecutableBatch() 
    {
        config(['database.connections.bound_variable_probe' => [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]]);

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
}
