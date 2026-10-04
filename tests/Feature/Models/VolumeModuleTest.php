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
     * trimming and empty becoming NULL.
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

            $row['is_special'] = (int) $row['is_special'];
            return $row;
        }, $before);

        $after = array_map(function($row) {
            $row['is_special'] = (int) $row['is_special'];
            return $row;
        }, $after);

        $this->assertSame($expected, $after);
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

    public function testNeedsUpdateAndUpdateModule(): void
    {
        $Volume = $this->makeVolume();
        $this->assertTrue($Volume->export());
        $this->assertFalse($Volume->needsUpdate());

        // An older install of the same module
        $Volume->module_version = '0.0.1';
        $Volume->installed_at   = '2000-01-01 00:00:00';
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
}
