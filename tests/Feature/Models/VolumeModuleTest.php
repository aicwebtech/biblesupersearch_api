<?php

namespace Tests\Feature\Models;

use Tests\TestCase;
use App\Models\Volume;
use App\Models\VolumeTypes\Strongs;
use App\Models\VolumeContent\ContentBase;
use App\Models\VolumeContent\StrongsContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

/**
 * Volume module files: export, Export Meta, revert, update, install from a file, and creating
 * volumes from files.  Fixtures are unofficial Strong's volumes; their rows, tables and module
 * files are removed afterwards.
 */
class VolumeModuleTest extends TestCase
{
    /** @var Volume[] */
    private array $volumes = [];

    /** @var string[] */
    private array $files = [];

    /**
     * Rows that exercise the escaping: newlines, a pipe, a backslash, a zero
     */
    private const ROWS = [
        ['number' => 'H1', 'root_word' => "\u{05D0}\u{05D1}", 'transliteration' => "'a\u{0302}b", 'pronunciation' => 'awb',
            'definition' => 'A primitive word; <i>father</i>', 'short_definition' => 'father', 'is_special' => 0],
        ['number' => 'H8680', 'root_word' => NULL, 'transliteration' => NULL, 'pronunciation' => NULL,
            'definition' => "<b>Stem:</b> Aphel <br>\n<b>Mood:</b> Imperative <br>", 'short_definition' => NULL, 'is_special' => 1],
        ['number' => 'G2', 'root_word' => 'a|b', 'transliteration' => 'back\\slash', 'pronunciation' => '0',
            'definition' => "line one\r\nline two", 'short_definition' => '', 'is_special' => 0],
    ];

    protected function makeVolume(bool $rows = TRUE): Strongs
    {
        $suffix = bin2hex(random_bytes(3));

        $Volume = new Strongs();
        $Volume->module    = 'vol_module_' . $suffix;
        $Volume->name      = 'Module Fixture ' . $suffix;
        $Volume->shortname = 'ModFix ' . $suffix;
        $Volume->language  = 'en';
        $Volume->year      = '1890';

        // Registered before save(): tearDown's own lists only hold volumes that got this far
        $module_file = $Volume->getModuleFilePath();
        $this->deferCleanup(function() use ($Volume, $module_file) {
            $this->removeVolumeModuleFixture('strongs', $Volume->module);
            is_file($module_file) && unlink($module_file);
        });

        $Volume->save();
        $Volume->install(FALSE, TRUE);

        if($rows) {
            DB::table($Volume->content()->getTable())->insert(self::ROWS);
        }

        $this->files[] = $Volume->getModuleFilePath();

        return $this->volumes[] = $Volume;
    }

    public function tearDown(): void
    {
        foreach($this->volumes as $Volume) {
            $Volume->content()->uninstall();
            Volume::where('type', $Volume->type)->where('module', $Volume->module)->forceDelete();
        }

        foreach($this->files as $file) {
            is_file($file) && unlink($file);
        }

        $this->volumes = $this->files = [];

        parent::tearDown();
    }

    /** @return array{0: array, 1: string} info.json and contents.txt of a module file */
    protected function readZip(string $path): array
    {
        $Zip = new ZipArchive();
        $this->assertTrue($Zip->open($path) === TRUE, 'Cannot open ' . $path);

        $info     = json_decode($Zip->getFromName('info.json'), TRUE);
        $contents = $Zip->getFromName('contents.txt');
        $Zip->close();

        return [$info, $contents];
    }

    protected function contentRows(Volume $Volume): array
    {
        $Content = $Volume->content(TRUE);

        return DB::table($Content->getTable())->orderBy('number')->get(StrongsContent::EXPORT_FIELDS)
            ->map(fn($row) => (array) $row)->all();
    }

    /**
     * installStaged() tables left for a content table
     *
     * @return string[]
     */
    protected function stagingTables(string $table): array
    {
        return $this->tablesWithPrefix(StrongsContent::stagingTablePrefix($table));
    }

    /**
     * Tables kept aside for a content table (ContentBase::keptTablePrefix())
     *
     * @return string[]
     */
    protected function keptTables(string $table): array
    {
        return $this->tablesWithPrefix(StrongsContent::keptTablePrefix($table));
    }

    /**
     * Tables whose names start with $prefix (before the database prefix).  Read from the
     * catalogue, as these names end in a random suffix; not Schema::getTables(), which scans
     * every schema on the server.
     *
     * @return string[]
     */
    protected function tablesWithPrefix(string $prefix): array
    {
        $prefix = DB::getTablePrefix() . $prefix;

        $rows = (DB::connection()->getDriverName() === 'sqlite')
            ? DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE ?", [$prefix . '%'])
            : DB::select('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE ?', [$prefix . '%']);

        return array_values(array_filter(
            array_map(fn($row) => (string) array_values((array) $row)[0], $rows),
            fn(string $name) => str_starts_with($name, $prefix)
        ));
    }

    /**
     * Marks the volume as an older install of its module file, so needsUpdate() reads the file
     * again and finds it newer
     */
    protected function makeOutdated(Volume $Volume): void
    {
        $Volume->module_version    = '0.0.1';
        $Volume->installed_at      = '2000-01-01 00:00:00';
        $Volume->module_checked_at = NULL;
        $Volume->save();
    }

    public function testExportFieldsMatchTheContentSchema(): void
    {
        $Volume  = $this->makeVolume(FALSE);
        $columns = array_diff(Schema::getColumnListing($Volume->content()->getTable()), ['id', 'created_at', 'updated_at']);

        $this->assertEqualsCanonicalizing(StrongsContent::EXPORT_FIELDS, array_values($columns));
    }

    public function testExportWritesInfoAndContents(): void
    {
        $Volume = $this->makeVolume();

        $this->assertTrue($Volume->export(), implode('; ', $Volume->getErrors()));
        $this->assertTrue($Volume->hasModuleFile());
        $this->assertStringStartsWith('content/strongs/unofficial/strongs_vol_module_', $Volume->getModuleFilePath(TRUE));

        [$info, $contents] = $this->readZip($Volume->getModuleFilePath());

        $this->assertEqualsCanonicalizing(
            [...Strongs::INFO_FIELDS, 'delimiter', 'fields', 'escaped'],
            array_keys($info)
        );

        $this->assertSame('|', $info['delimiter']);
        $this->assertSame(StrongsContent::EXPORT_FIELDS, $info['fields']);
        $this->assertTrue($info['escaped']);
        $this->assertSame(config('app.version'), $info['module_version']);
        $this->assertSame($Volume->name, $info['name']);

        // A column default the saved model never loaded is still exported, not as NULL
        $this->assertNotNull($info['copyright']);
        $this->assertSame(0, (int) $info['copyright']);

        foreach(['italics', 'audio_enable', 'installed', 'enabled', 'is_default', 'id', 'rank'] as $absent) {
            $this->assertArrayNotHasKey($absent, $info);
        }

        $lines = array_values(array_filter(explode("\n", $contents), fn($line) => $line !== '' && $line[0] != '#'));
        $this->assertCount(count(self::ROWS), $lines);
        $this->assertStringContainsString('<b>Stem:</b> Aphel <br>\\n<b>Mood:</b>', $contents);
        $this->assertStringContainsString('a\\|b', $contents);

        $Volume->refresh();
        $this->assertSame(config('app.version'), $Volume->module_version);
        $this->assertSame(0, (int) $Volume->needs_update);
    }

    public function testExportRefusesAnExistingFileWithoutOverwrite(): void
    {
        $Volume = $this->makeVolume();
        $this->assertTrue($Volume->export());

        $this->assertFalse($Volume->export());
        $this->assertStringContainsString('already exists', implode(' ', $Volume->getErrors()));

        $Volume->resetErrors();
        $this->assertTrue($Volume->export(TRUE));
    }

    public function testExportRefusesAnUninstalledVolume(): void
    {
        $Volume = $this->makeVolume(FALSE);
        $Volume->uninstall();

        $this->assertFalse($Volume->export());
        $this->assertFalse($Volume->hasModuleFile());
    }

    /**
     * Export, uninstall, install from the file: the content comes back as it was, apart from
     * trimming, empty becoming NULL, and the definitions' blank space removed on install
     * (StrongsContent::cleanDefinition()).
     */
    public function testContentRoundTripsThroughTheModuleFile(): void
    {
        $Volume = $this->makeVolume();
        $before = $this->contentRows($Volume);

        $this->assertTrue($Volume->export());
        $Volume->uninstall();
        $this->assertFalse(Schema::hasTable($Volume->content()->getTable()));

        $this->assertTrue($Volume->install(), implode('; ', $Volume->getErrors()));
        $after = $this->contentRows($Volume);

        $expected = array_map(function($row) {
            foreach($row as $field => $value) {
                $value = ($value === NULL) ? NULL : trim((string) $value);
                $row[$field] = ($value === '') ? NULL : $value;
            }

            foreach(['definition', 'short_definition'] as $field) {
                $row[$field] = StrongsContent::cleanDefinition($row[$field]);
            }

            $row['is_special'] = (int) $row['is_special'];
            return $row;
        }, $before);

        $after = array_map(function($row) {
            $row['is_special'] = (int) $row['is_special'];
            return $row;
        }, $after);

        $this->assertSame($expected, $after);
    }

    /**
     * contents.txt is read a line at a time: \r\n and bare \r line endings end a row as \n does
     */
    public function testInstallReadsAnyLineEnding(): void
    {
        $Volume = $this->makeVolume();
        $before = $this->contentRows($Volume);
        $this->assertTrue($Volume->export());

        $path = $Volume->getModuleFilePath();
        [, $contents] = $this->readZip($path);

        foreach(["\r\n", "\r"] as $ending) {
            $Zip = new ZipArchive();
            $Zip->open($path);
            $Zip->addFromString('contents.txt', str_replace("\n", $ending, $contents));
            $Zip->close();

            $Volume->uninstall();
            $this->assertTrue($Volume->install(), implode('; ', $Volume->getErrors()));
            $this->assertSame(
                array_column($before, 'number'),
                array_column($this->contentRows($Volume), 'number'),
                json_encode($ending)
            );
        }
    }

    /**
     * A newer module file that passes moduleFileIsInstallable() but then fails to install (a
     * duplicate number here) puts the old volume and its content back.
     */
    public function testUpdateModuleRestoresTheVolumeWhenTheReinstallFails(): void
    {
        $Volume = $this->makeVolume();
        $this->assertTrue($Volume->export());

        $path  = $Volume->getModuleFilePath();
        $table = $Volume->content()->getTable();
        [, $contents] = $this->readZip($path);
        $lines = explode("\n", rtrim($contents, "\n"));

        $Zip = new ZipArchive();
        $Zip->open($path);
        $Zip->addFromString('contents.txt', $contents . end($lines) . "\n");
        $Zip->close();
        clearstatcache(TRUE, $path);

        $Volume->module_version = '0.0.1';
        $Volume->installed_at   = '2000-01-01 00:00:00';
        $Volume->needs_update   = 1;
        $Volume->save();
        $before = $this->contentRows($Volume);

        $this->assertTrue($Volume->moduleFileIsInstallable());
        $this->assertFalse($Volume->updateModule());
        $this->assertNotEmpty($Volume->getErrors());

        $Volume->refresh();
        $this->assertSame(1, (int) $Volume->installed, 'Volume left uninstalled');
        $this->assertSame(1, (int) $Volume->enabled, 'Volume left disabled');
        $this->assertSame('0.0.1', $Volume->module_version);
        $this->assertSame($before, $this->contentRows($Volume), 'Content not restored');

        $Content = $Volume->content();
        $this->assertFalse(Schema::hasTable($Content::backupTableName($table)), 'Backup table kept');
        $this->assertSame([], $this->stagingTables($table), 'Staging table kept');
    }

    /**
     * The file's settings clash with another volume (its name here): the update puts the old
     * content back too, rather than leaving new content under the old module_version
     */
    public function testUpdateModuleRestoresTheVolumeWhenTheSettingsClash(): void
    {
        $Volume = $this->makeVolume();
        $Other  = $this->makeVolume(FALSE);
        $this->assertTrue($Volume->export());

        $path = $Volume->getModuleFilePath();
        [$info, $contents] = $this->readZip($path);
        $info['name'] = $Other->name;

        $Zip = new ZipArchive();
        $Zip->open($path);
        $Zip->addFromString('info.json', json_encode($info));
        $Zip->addFromString('contents.txt', $contents . "H9999|||||Only in the new file|0\n");
        $Zip->close();
        clearstatcache(TRUE, $path);

        $Volume->module_version = '0.0.1';
        $Volume->installed_at   = '2000-01-01 00:00:00';
        $Volume->needs_update   = 1;
        $Volume->save();
        $name   = $Volume->name;
        $before = $this->contentRows($Volume);

        $this->assertFalse($Volume->updateModule());
        $this->assertStringContainsString('clash', implode(' ', $Volume->getErrors()));

        $Volume->refresh();
        $this->assertSame($name, $Volume->name);
        $this->assertSame('0.0.1', $Volume->module_version);
        $this->assertSame(1, (int) $Volume->installed);
        $this->assertSame(1, (int) $Volume->enabled);
        $this->assertSame($before, $this->contentRows($Volume), 'The new content was kept');

        $Content = $Volume->content();
        $this->assertFalse(Schema::hasTable($Content::backupTableName($Content->getTable())), 'Backup table kept');
        $this->assertSame([], $this->stagingTables($Content->getTable()), 'Staging table kept');
    }

    /**
     * The new content is built beside the live table: while it loads, the volume is still
     * installed and enabled and the API still reads the old content - the default dictionary is
     * never missing during an Update.  A second Update works too: the swapped-in table's index
     * names do not collide with the next staging table's (SQLite needs them unique database-wide).
     */
    public function testUpdateModuleKeepsTheVolumeInstalledWhileTheNewContentLoads(): void
    {
        $Volume = $this->makeVolume();
        $this->assertTrue($Volume->export());

        $table = $Volume->content()->getTable();
        DB::table($table)->where('number', 'H1')->delete();

        $seen = [];

        $Volume->Content = new class extends StrongsContent {
            public ?\Closure $observe = NULL;

            protected function importContentStream(mixed $stream, mixed $info): int
            {
                ($this->observe)();
                return parent::importContentStream($stream, $info);
            }
        };

        $Volume->Content->setVolume($Volume);
        $Volume->Content->observe = function() use ($Volume, $table, &$seen) {
            $Stored = Volume::find($Volume->id);
            $seen[] = [(int) $Stored->installed, (int) $Stored->enabled, DB::table($table)->count()];
        };

        foreach([1, 2] as $update) {
            $this->makeOutdated($Volume);

            $this->assertTrue($Volume->updateModule(), 'Update ' . $update . ': ' . implode('; ', $Volume->getErrors()));
            $this->assertSame(count(self::ROWS), DB::table($table)->count(), 'Update ' . $update);
            $this->assertNotEmpty(Schema::getIndexes($table), 'Update ' . $update . ': the new table has no indexes');
            $this->assertSame([], $this->stagingTables($table), 'Update ' . $update . ': staging table kept');
            $this->assertSame([], $this->keptTables($table), 'Update ' . $update . ': old table kept');
        }

        // While the first update loaded, the old content (H1 deleted) was still live
        $this->assertSame([[1, 1, count(self::ROWS) - 1], [1, 1, count(self::ROWS)]], $seen);

        $Volume->refresh();
        $this->assertSame(config('app.version'), $Volume->module_version);
        $this->assertNotEquals('2000-01-01 00:00:00', (string) $Volume->installed_at);
    }

    /**
     * The new content's table cannot be put in place: the old one stays live and the volume is
     * left as it was
     */
    public function testUpdateModuleKeepsTheOldContentWhenTheSwapFails(): void
    {
        $Volume = $this->makeVolume();
        $this->assertTrue($Volume->export());
        $this->makeOutdated($Volume);

        $table  = $Volume->content()->getTable();
        $before = $this->contentRows($Volume);

        $Volume->Content = new class extends StrongsContent {
            public function swapIn(string $staging): ?string
            {
                throw new \RuntimeException('Rename refused');
            }
        };
        $Volume->Content->setVolume($Volume);

        $this->assertFalse($Volume->updateModule());
        $this->assertStringContainsString('Rename refused', implode(' ', $Volume->getErrors()));

        $Volume->Content = NULL;
        $Volume->refresh();
        $this->assertSame(1, (int) $Volume->installed);
        $this->assertSame(1, (int) $Volume->enabled);
        $this->assertSame('0.0.1', $Volume->module_version);
        $this->assertSame($before, $this->contentRows($Volume));
        $this->assertSame([], $this->stagingTables($table), 'Staging table kept');
        $this->assertSame([], $this->keptTables($table), 'Old table kept');
    }

    /**
     * A table an earlier failure left for the admin - the old content of a failed Update, or a
     * backup whose restore failed - is never overwritten: an Update retires the live table under
     * a name of its own, and a new backup moves the old one aside
     */
    public function testAKeptTableIsNeverOverwritten(): void
    {
        $Volume = $this->makeVolume();
        $this->assertTrue($Volume->export());

        $Content = $Volume->content();
        $table   = $Content->getTable();
        $backup  = StrongsContent::backupTableName($table);

        // A backup left from a replacement whose restore failed, holding the only copy
        DB::statement('CREATE TABLE ' . DB::getQueryGrammar()->wrapTable($backup) . ' AS SELECT * FROM ' . DB::getQueryGrammar()->wrapTable($table));
        DB::table($backup)->where('number', 'H1')->delete();
        $only_copy = DB::table($backup)->count();

        try {
            // An Update leaves it alone
            $this->makeOutdated($Volume);
            $this->assertTrue($Volume->updateModule(), implode('; ', $Volume->getErrors()));
            $this->assertSame($only_copy, DB::table($backup)->count(), 'The kept backup was touched by an Update');

            // A new backup moves it aside rather than dropping it
            $this->assertSame($backup, $Content->backup());
            $kept = $this->keptTables($table);
            $this->assertCount(1, $kept);
            $this->assertSame($only_copy, DB::table(substr($kept[0], strlen(DB::getTablePrefix())))->count());
            $this->assertSame(count(self::ROWS), DB::table($backup)->count());
        }
        finally {
            Schema::dropIfExists($backup);

            foreach($this->keptTables($table) as $name) {
                Schema::dropIfExists(substr($name, strlen(DB::getTablePrefix())));
            }
        }
    }

    /**
     * The insert batch is sized from the row as inserted: an older module file listing only five
     * fields still inserts seven columns a row, as processInsertRow() adds short_definition and
     * is_special.  Sized from the file, a batch overran SQLite's 999 bound variables.
     */
    public function testInsertBatchIsSizedFromTheInsertedRow(): void
    {
        $Volume = $this->makeVolume();
        $this->assertTrue($Volume->export());

        $path = $Volume->getModuleFilePath();
        [$info, $contents] = $this->readZip($path);

        $fields = ['number', 'root_word', 'transliteration', 'pronunciation', 'definition'];
        $lines  = [];

        foreach(self::ROWS as $row) {
            $lines[] = StrongsContent::encodeRow(array_map(fn($field) => $row[$field], $fields));
        }

        $info['fields'] = $fields;

        $Zip = new ZipArchive();
        $Zip->open($path);
        $Zip->addFromString('info.json', json_encode($info));
        $Zip->addFromString('contents.txt', implode("\n", $lines) . "\n");
        $Zip->close();

        $Volume->uninstall();

        $Volume->Content = new class extends StrongsContent {
            public array $sized = [];

            protected function insertBatchSize(int $columns): int
            {
                $this->sized[] = $columns;
                return parent::insertBatchSize($columns);
            }
        };
        $Volume->Content->setVolume($Volume);

        $this->assertTrue($Volume->install(), implode('; ', $Volume->getErrors()));
        $this->assertSame([count(StrongsContent::EXPORT_FIELDS)], $Volume->Content->sized);
        $this->assertSame(count(self::ROWS), DB::table($Volume->Content->getTable())->count());

        $Volume->Content = NULL;
    }

    /**
     * The backup holds every row and column of the content.  On MySQL it is made without
     * CREATE TABLE ... SELECT, which a server enforcing GTID consistency refuses.
     */
    public function testBackupCopiesTheContent(): void
    {
        $Volume  = $this->makeVolume();
        $Content = $Volume->content();
        $before  = $this->contentRows($Volume);

        $queries = [];
        DB::listen(function($Query) use (&$queries) {
            $queries[] = $Query->sql;
        });

        $backup = $Content->backup();

        try {
            $this->assertNotNull($backup);
            $this->assertSame(
                $before,
                DB::table($backup)->orderBy('number')->get(StrongsContent::EXPORT_FIELDS)->map(fn($row) => (array) $row)->all()
            );

            if(in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], TRUE)) {
                $this->assertSame([], array_values(array_filter($queries, fn($sql) => preg_match('/CREATE TABLE.*\bSELECT\b/is', $sql))));
            }
        }
        finally {
            Schema::dropIfExists($backup);
        }
    }

    /** Without a module file, install leaves an empty table, as before */
    public function testInstallWithoutAModuleFileIsStructureOnly(): void
    {
        $Volume = $this->makeVolume(FALSE);

        $this->assertFalse($Volume->hasModuleFile());
        $this->assertSame(0, DB::table($Volume->content()->getTable())->count());
    }

    public function testExportMetaRewritesOnlyInfo(): void
    {
        $Volume = $this->makeVolume();
        $this->assertTrue($Volume->export());

        [, $contents] = $this->readZip($Volume->getModuleFilePath());

        $Volume->name = $Volume->name . ' Renamed';
        $Volume->save();

        $this->assertTrue($Volume->updateMetaInfo(), implode('; ', $Volume->getErrors()));

        [$info, $contents_after] = $this->readZip($Volume->getModuleFilePath());

        $this->assertSame($Volume->name, $info['name']);
        $this->assertSame($contents, $contents_after);

        // Nothing left to change is not an error
        $this->assertTrue($Volume->updateMetaInfo());
        $this->assertFalse($Volume->hasErrors());
    }

    /**
     * Export Meta on an uninstalled volume gives it no install time: it has none until it is
     * installed
     */
    public function testExportMetaLeavesAnUninstalledVolumeWithoutAnInstallTime(): void
    {
        $Volume = $this->makeVolume();
        $this->assertTrue($Volume->export());
        $this->assertTrue($Volume->uninstall());

        $Volume->name = $Volume->name . ' Renamed';
        $Volume->save();

        $this->assertTrue($Volume->updateMetaInfo(), implode('; ', $Volume->getErrors()));

        $Volume->refresh();
        $this->assertSame(0, (int) $Volume->installed);
        $this->assertNull($Volume->installed_at);
        $this->assertSame($Volume->name, $this->readZip($Volume->getModuleFilePath())[0]['name']);
    }

    public function testExportMetaWithoutAFile(): void
    {
        $Volume = $this->makeVolume();

        $this->assertFalse($Volume->updateMetaInfo());

        $Volume->resetErrors();
        $this->assertTrue($Volume->updateMetaInfo(TRUE));
        $this->assertTrue($Volume->hasModuleFile());
    }

    /** Revert takes the settings, never the identity, from the file */
    public function testRevertRestoresSettingsOnly(): void
    {
        $Volume = $this->makeVolume();
        $name   = $Volume->name;
        $this->assertTrue($Volume->export());

        // A file claiming to be something else
        $Zip = new ZipArchive();
        $Zip->open($Volume->getModuleFilePath());
        $info = json_decode($Zip->getFromName('info.json'), TRUE);
        $info['module']   = 'someone_else';
        $info['type']     = 'other';
        $info['official'] = 1;
        $Zip->addFromString('info.json', json_encode($info));
        $Zip->close();

        $Volume->name = $name . ' Edited';
        $Volume->save();

        $this->assertTrue($Volume->revertMetaInfo(), implode('; ', $Volume->getErrors()));

        $Volume->refresh();
        $this->assertSame($name, $Volume->name);
        $this->assertStringStartsWith('vol_module_', $Volume->module);
        $this->assertSame('strongs', $Volume->type);
        $this->assertSame(0, (int) $Volume->official);
    }

    public function testPopulateCreatesVolumesFromFiles(): void
    {
        $Volume = $this->makeVolume();
        $this->assertTrue($Volume->export());

        $module = $Volume->module;
        $name   = $Volume->name;

        // The record goes; the file stays
        $Volume->content()->uninstall();
        $Volume->forceDelete();
        $this->assertNull(Strongs::findByModule($module));

        Volume::populateVolumesTable();

        $Created = Strongs::findByModule($module);
        $this->assertNotNull($Created, 'No volume created from the module file');
        $this->volumes[] = $Created;

        $this->assertSame($name, $Created->name);
        $this->assertSame(0, (int) $Created->official);
        $this->assertSame(0, (int) $Created->installed);
        $this->assertSame(config('app.version'), $Created->module_version);

        // Existing records are left alone
        Volume::populateVolumesTable();
        $this->assertSame(1, Volume::where('type', 'strongs')->where('module', $module)->count());

        // Installing it reads the content from the file
        $this->assertTrue($Created->install());
        $this->assertSame(count(self::ROWS), DB::table($Created->content()->getTable())->count());
    }

    /** A file whose name clashes with an existing volume is skipped, not an error */
    public function testPopulateSkipsAClashingFile(): void
    {
        $Volume = $this->makeVolume();
        $this->assertTrue($Volume->export());

        $Other = $this->makeVolume(FALSE);

        // The file now names the other volume's name
        $Zip = new ZipArchive();
        $Zip->open($Volume->getModuleFilePath());
        $info = json_decode($Zip->getFromName('info.json'), TRUE);
        $info['name'] = $Other->name;
        $Zip->addFromString('info.json', json_encode($info));
        $Zip->close();

        $module = $Volume->module;
        $Volume->content()->uninstall();
        $Volume->forceDelete();

        Volume::populateVolumesTable();

        $this->assertNull(Strongs::findByModule($module));
    }

    /**
     * A newer module file that would not install is refused before the volume is uninstalled:
     * the old content stays in place.
     */
    public function testUpdateModuleRefusesAFileThatWouldNotInstall(): void
    {
        $Volume = $this->makeVolume();
        $this->assertTrue($Volume->export());

        $path  = $Volume->getModuleFilePath();
        $table = $Volume->content()->getTable();

        $breakages = [
            'no contents.txt' => function(ZipArchive $Zip) {
                $Zip->deleteName('contents.txt');
            },
            'unreadable info.json' => function(ZipArchive $Zip) {
                $Zip->addFromString('info.json', '{not json');
            },
        ];

        foreach($breakages as $label => $break) {
            $this->assertTrue($Volume->export(TRUE));

            $Zip = new ZipArchive();
            $Zip->open($path);
            $break($Zip);
            $Zip->close();
            clearstatcache(TRUE, $path);

            // Make it look newer than the install
            $Volume->module_version = '0.0.1';
            $Volume->installed_at   = '2000-01-01 00:00:00';
            $Volume->needs_update   = 1;
            $Volume->save();
            $Volume->resetErrors();

            $this->assertFalse($Volume->updateModule(), $label);
            $this->assertNotEmpty($Volume->getErrors(), $label);

            $Volume->refresh();
            $this->assertSame(1, (int) $Volume->installed, $label . ': volume was uninstalled');
            $this->assertSame(1, (int) $Volume->enabled, $label);
            $this->assertSame(count(self::ROWS), DB::table($table)->count(), $label . ': content lost');
        }
    }

    public function testModuleFileIsInstallable(): void
    {
        $Volume = $this->makeVolume();

        $this->assertFalse($Volume->moduleFileIsInstallable(), 'No file yet');

        $Volume->resetErrors();
        $this->assertTrue($Volume->export());
        $this->assertTrue($Volume->moduleFileIsInstallable());
    }

    public function testNeedsUpdateAndUpdateModule(): void
    {
        $Volume = $this->makeVolume();
        $this->assertTrue($Volume->export());
        $this->assertFalse($Volume->needsUpdate());

        // An older install of the same module, never checked since
        $Volume->module_version    = '0.0.1';
        $Volume->installed_at      = '2000-01-01 00:00:00';
        $Volume->module_checked_at = NULL;
        $Volume->name           = $Volume->name . ' Local';
        $Volume->save();
        DB::table($Volume->content()->getTable())->where('number', 'H1')->delete();

        $this->assertTrue($Volume->needsUpdate());

        $this->assertTrue($Volume->updateModule(), implode('; ', $Volume->getErrors()));

        $Volume->refresh();
        $this->assertSame(1, (int) $Volume->installed);
        $this->assertSame(1, (int) $Volume->enabled);
        $this->assertSame(config('app.version'), $Volume->module_version);
        $this->assertSame(0, (int) $Volume->needs_update);
        $this->assertStringEndsNotWith(' Local', $Volume->name);
        $this->assertNotNull($Volume->module_updated_at);
        $this->assertSame(count(self::ROWS), DB::table($Volume->content()->getTable())->count());

        $this->assertFalse($Volume->updateModule());
        $this->assertStringContainsString('No update needed', implode(' ', $Volume->getErrors()));
    }

    /**
     * Storing the check does not touch updated_at: statics_changed reports that to API clients
     * as a volume change
     */
    public function testNeedsUpdateDoesNotTouchUpdatedAt(): void
    {
        $Volume = $this->makeVolume();
        $this->assertTrue($Volume->export());

        $Volume->installed_at = '2000-01-01 00:00:00';
        $Volume->save();
        Volume::whereKey($Volume->id)->toBase()->update(['updated_at' => '2001-01-01 00:00:00', 'module_checked_at' => NULL]);

        $Volume = $Volume->fresh();
        $this->assertFalse($Volume->needsUpdate());
        $this->assertFalse($Volume->isDirty(), 'The stored check is left dirty');

        $Volume = $Volume->fresh();
        $this->assertNotNull($Volume->module_checked_at, 'The read was not recorded');
        $this->assertSame('2001-01-01 00:00:00', (string) $Volume->getRawOriginal('updated_at'));
    }

    /**
     * Once the module file has been read, its answer is stored: the ZIP is not opened again
     * until the file changes.
     */
    public function testNeedsUpdateReadsTheModuleFileOnlyOnceItChanges(): void
    {
        $Volume = $this->makeVolume();
        $this->assertTrue($Volume->export());

        $Volume->installed_at = '2000-01-01 00:00:00';
        $Volume->save();

        $this->assertFalse($Volume->needsUpdate());
        $this->assertNotNull($Volume->fresh()->module_checked_at, 'The read was not recorded');

        // Checked after the file was written: the stored flag is answered, wrong as it is here,
        // which shows the file was not read
        $Volume->needs_update      = 1;
        $Volume->module_checked_at = date('Y-m-d H:i:s', time() + 60);
        $Volume->save();
        $this->assertTrue($Volume->fresh()->needsUpdate());

        // The file changes after the last check: it is read again
        touch($Volume->getModuleFilePath(), time() + 120);
        clearstatcache();
        $this->assertFalse($Volume->fresh()->needsUpdate());
        $this->assertSame(0, (int) $Volume->fresh()->needs_update);
    }
}
