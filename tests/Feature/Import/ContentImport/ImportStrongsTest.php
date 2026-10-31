<?php

namespace Tests\Feature\Import\ContentImport;

use App\ContentImportManager;
use App\Models\Copyright;
use App\Models\Volume;
use App\Models\VolumeContent\StrongsContent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Strong's dictionary import from CSV and Excel, end to end: check, import, and the failures
 * that must leave no volume, content table or upload behind.
 *
 * Every fixture (source file, volume, content table, staged upload) is created here and removed
 * in a finally block.
 */
class ImportStrongsTest extends TestCase
{
    protected string $dir;

    /**
     * Staged uploads to remove, as full paths
     *
     * @var array<int, string>
     */
    protected array $uploads = [];

    protected const HEADER = ['Number', 'Root', 'Transliteration', 'Pronunciation', 'Definition', 'Short'];

    protected const ROWS = [
        ['H0001', 'אב', '\'âb', 'awb', "Father; <i>chief</i>", 'father'],
        ['h2', 'אב', '\'ab', 'ab', "(Chaldee)\ncorresponding to H1", ''],
        ['G25', 'ἀγαπάω', 'agapaō', 'ag-ap-ah\'-o', 'To love', 'love'],
    ];

    protected const MAPPING = [
        'first_row_data' => 2,
        'col_A' => 'number',
        'col_B' => 'root_word',
        'col_C' => 'transliteration',
        'col_D' => 'pronunciation',
        'col_E' => 'definition',
        'col_F' => 'short_definition',
    ];

    public function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/bss_content_import_' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0775, TRUE);
    }

    public function tearDown(): void
    {
        foreach($this->uploads as $path) {
            is_file($path) && unlink($path);
        }

        foreach(glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }

        is_dir($this->dir) && rmdir($this->dir);

        parent::tearDown();
    }

    public static function formatProvider(): array
    {
        return [
            'csv'   => ['csv', 'csv'],
            'excel' => ['excel', 'xlsx'],
        ];
    }

    #[DataProvider('formatProvider')]
    public function testCheckThenImportCreatesAnInstalledVolume(string $importer, string $extension): void
    {
        $module = 'zz_import_' . bin2hex(random_bytes(3));
        $File   = $this->makeUpload($extension, array_merge([static::HEADER], static::ROWS));

        try {
            $Manager = new ContentImportManager();
            $checked = $Manager->checkImportFile($this->checkData($importer, $File));

            $this->assertTrue($checked, implode(PHP_EOL, (array) $Manager->getErrors()));
            $this->assertSame('strongs', $Manager->parsed_attributes['type']);
            $this->assertFileExists(Volume::getModulePath('strongs') . 'uploads/' . $Manager->sanitized_filename);

            $Manager = $this->runImport($importer, $Manager->sanitized_filename, $module);
            $this->assertFalse($Manager->hasErrors(), implode(PHP_EOL, (array) $Manager->getErrors()));
            $this->assertSame(3, $Manager->rows_imported);

            $Volume = Volume::findByTypeAndModule('strongs', $module);
            $this->assertNotNull($Volume);
            $this->assertSame(1, (int) $Volume->installed);
            $this->assertSame(1, (int) $Volume->enabled);
            $this->assertSame(0, (int) $Volume->official);
            $this->assertSame($importer, $Volume->importer);

            $rows = $Volume->content()->newQuery()->orderBy('id')->get()->keyBy('number');

            $this->assertSame(['H1', 'H2', 'G25'], $rows->keys()->all());
            $this->assertSame('Father; <i>chief</i>', $rows['H1']->definition);
            $this->assertSame('(Chaldee)<br />corresponding to H1', $rows['H2']->definition);
            $this->assertNull($rows['H2']->short_definition);
            $this->assertSame('ἀγαπάω', $rows['G25']->root_word);
            $this->assertSame([0], $rows->pluck('is_special')->map(fn($v) => (int) $v)->unique()->values()->all());
        }
        finally {
            $this->removeVolume($module);
        }
    }

    public function testNumberCanBeMappedFromPrefixAndIntegerColumns(): void
    {
        $module = 'zz_import_' . bin2hex(random_bytes(3));
        $File   = $this->makeUpload('csv', [
            ['Prefix', 'Number', 'Definition'],
            ['H', '0007', 'Seven'],
            ['G', '12', 'Twelve'],
        ]);

        $mapping = ['first_row_data' => 2, 'col_A' => 'number_hg', 'col_B' => 'number_int', 'col_C' => 'definition'];

        try {
            $Manager = new ContentImportManager();
            $this->assertTrue($Manager->checkImportFile($this->checkData('csv', $File, $mapping)), implode(PHP_EOL, (array) $Manager->getErrors()));

            $Manager = $this->runImport('csv', $Manager->sanitized_filename, $module, $mapping);
            $this->assertFalse($Manager->hasErrors(), implode(PHP_EOL, (array) $Manager->getErrors()));

            $numbers = Volume::findByTypeAndModule('strongs', $module)->content()->newQuery()->orderBy('id')->pluck('number')->all();
            $this->assertSame(['H7', 'G12'], $numbers);
        }
        finally {
            $this->removeVolume($module);
        }
    }

    /**
     * A UTF-8 BOM ahead of a quoted first value: the BOM is skipped before the row is parsed, so
     * the enclosure is not read as part of the value
     */
    public function testCsvWithABomBeforeAQuotedFirstRow(): void
    {
        $module = 'zz_import_' . bin2hex(random_bytes(3));
        $File   = $this->makeRawUpload('csv', "\xEF\xBB\xBF\"H1\",\"Father\"\n\"G25\",\"To love\"\n");

        $mapping = ['first_row_data' => 1, 'col_A' => 'number', 'col_B' => 'definition'];

        try {
            $Manager = new ContentImportManager();
            $this->assertTrue($Manager->checkImportFile($this->checkData('csv', $File, $mapping)), implode(PHP_EOL, (array) $Manager->getErrors()));

            $Manager = $this->runImport('csv', $Manager->sanitized_filename, $module, $mapping);
            $this->assertFalse($Manager->hasErrors(), implode(PHP_EOL, (array) $Manager->getErrors()));

            $numbers = Volume::findByTypeAndModule('strongs', $module)->content()->newQuery()->orderBy('id')->pluck('number')->all();
            $this->assertSame(['H1', 'G25'], $numbers);
        }
        finally {
            $this->removeVolume($module);
        }
    }

    /**
     * A quoted value ending in a backslash ends at its closing quote, rather than the backslash
     * escaping that quote and the value running on into the next record.  A doubled quote is
     * still a literal quote.
     */
    public function testCsvWithAValueEndingInABackslash(): void
    {
        $module = 'zz_import_' . bin2hex(random_bytes(3));
        $File   = $this->makeRawUpload('csv', "\"H1\",\"See C:\\\"\n\"G25\",\"To \"\"love\"\"\"\n");

        $mapping = ['first_row_data' => 1, 'col_A' => 'number', 'col_B' => 'definition'];

        try {
            $Manager = new ContentImportManager();
            $this->assertTrue($Manager->checkImportFile($this->checkData('csv', $File, $mapping)), implode(PHP_EOL, (array) $Manager->getErrors()));

            $Manager = $this->runImport('csv', $Manager->sanitized_filename, $module, $mapping);
            $this->assertFalse($Manager->hasErrors(), implode(PHP_EOL, (array) $Manager->getErrors()));

            $rows = Volume::findByTypeAndModule('strongs', $module)->content()->newQuery()->orderBy('id')->pluck('definition', 'number')->all();
            $this->assertSame(['H1', 'G25'], array_keys($rows));
            $this->assertStringContainsString('See C:\\', $rows['H1']);
            $this->assertStringContainsString('To "love"', $rows['G25']);
        }
        finally {
            $this->removeVolume($module);
        }
    }

    /**
     * With skipping on, invalid rows (here: no number, and a duplicate) are left out and listed,
     * and the check and import go ahead with the rest
     */
    public function testSkippingInvalidRowsImportsTheRest(): void
    {
        $module  = 'zz_import_' . bin2hex(random_bytes(3));
        $rows    = array_merge([static::HEADER], static::ROWS, [
            ['', '', '', '', 'No number', ''],
            ['G25', '', '', '', 'Duplicate', ''],
            ['H7', '', '', '', 'Entry 7', ''],
        ]);
        $mapping = static::MAPPING + ['skip_invalid_rows' => 'true'];
        $File    = $this->makeUpload('csv', $rows);

        try {
            $Manager = new ContentImportManager();
            $this->assertTrue($Manager->checkImportFile($this->checkData('csv', $File, $mapping)), implode(PHP_EOL, (array) $Manager->getErrors()));
            $this->assertCount(1, $Manager->notices);
            $this->assertStringContainsString('2 invalid row(s) skipped in the rows checked', $Manager->notices[0]);
            $this->assertStringContainsString('Row 5', $Manager->notices[0]);
            $this->assertStringContainsString('Row 6: Duplicate number \'G25\'', $Manager->notices[0]);

            $Manager = $this->runImport('csv', $Manager->sanitized_filename, $module, $mapping);
            $this->assertFalse($Manager->hasErrors(), implode(PHP_EOL, (array) $Manager->getErrors()));
            $this->assertSame(4, $Manager->rows_imported);
            $this->assertStringContainsString('2 invalid row(s) skipped:', $Manager->notices[0]);

            $Content = Volume::findByTypeAndModule('strongs', $module)->content();
            $this->assertSame(['H1', 'H2', 'G25', 'H7'], $Content->newQuery()->orderBy('id')->pluck('number')->all());
            $this->assertSame('To love', $Content->newQuery()->where('number', 'G25')->value('definition'), 'The first G25 was kept');
        }
        finally {
            $this->removeVolume($module);
        }
    }

    /**
     * Skipping is off unless asked for: the same file fails, as it always has
     */
    public function testWithoutSkippingAnInvalidRowFailsTheCheck(): void
    {
        $rows = array_merge([static::HEADER], static::ROWS, [['G25', '', '', '', 'Duplicate', '']]);
        $File = $this->makeUpload('csv', $rows);

        $Manager = new ContentImportManager();
        $this->assertFalse($Manager->checkImportFile($this->checkData('csv', $File, static::MAPPING + ['skip_invalid_rows' => 'false'])));
        $this->assertStringContainsString('Row 5: Duplicate number \'G25\'', implode(' ', $Manager->getErrors()));
        $this->assertSame([], $Manager->notices);
    }

    public function testSkippingEveryRowStillFindsNoContent(): void
    {
        $File = $this->makeUpload('csv', [static::HEADER, ['', '', '', '', 'No number', '']]);

        $Manager = new ContentImportManager();
        $this->assertFalse($Manager->checkImportFile($this->checkData('csv', $File, static::MAPPING + ['skip_invalid_rows' => 'true'])));
        $this->assertStringContainsString('No content found in this file with the given settings (1 invalid row(s) skipped)', implode(' ', $Manager->getErrors()));
    }

    public function testCheckFailsWithoutARequiredMapping(): void
    {
        $File    = $this->makeUpload('csv', array_merge([static::HEADER], static::ROWS));
        $mapping = static::MAPPING;
        unset($mapping['col_A']);

        $Manager = new ContentImportManager();
        $Manager->test_mode = TRUE;

        $this->assertFalse($Manager->checkImportFile($this->checkData('csv', $File, $mapping)));
        $this->assertStringContainsString('Please specify a column for ' . StrongsContent::getImportFields()['number'], implode(' ', $Manager->getErrors()));
    }

    public function testCheckRejectsTheWrongExtension(): void
    {
        $File = $this->makeUpload('csv', array_merge([static::HEADER], static::ROWS));

        $Manager = new ContentImportManager();
        $Manager->test_mode = TRUE;

        $this->assertFalse($Manager->checkImportFile($this->checkData('excel', $File)));
        $this->assertStringContainsString('.xls, .xlsx', implode(' ', $Manager->getErrors()));
    }

    public function testCheckRejectsAnImporterTheTypeDoesNotSupport(): void
    {
        $File = $this->makeUpload('csv', array_merge([static::HEADER], static::ROWS));

        $Manager = new ContentImportManager();
        $Manager->test_mode = TRUE;

        $this->assertFalse($Manager->checkImportFile(['importer' => 'nope'] + $this->checkData('csv', $File)));
        $this->assertFalse((new ContentImportManager())->checkImportFile(['content_type' => 'nope'] + $this->checkData('csv', $File)));
    }

    public function testCheckReportsInvalidAndDuplicateNumbers(): void
    {
        $File = $this->makeUpload('csv', [
            static::HEADER,
            ['H1', '', '', '', 'One', ''],
            ['X9', '', '', '', 'Bad', ''],
        ]);

        $Manager = new ContentImportManager();
        $Manager->test_mode = TRUE;
        $this->assertFalse($Manager->checkImportFile($this->checkData('csv', $File)));
        $this->assertStringContainsString('Row 3: Strong\'s number is invalid', implode(' ', $Manager->getErrors()));

        $File = $this->makeUpload('csv', [
            static::HEADER,
            ['H1', '', '', '', 'One', ''],
            ['H0001', '', '', '', 'Again', ''],
        ]);

        $Manager = new ContentImportManager();
        $Manager->test_mode = TRUE;
        $this->assertFalse($Manager->checkImportFile($this->checkData('csv', $File)));
        $this->assertStringContainsString('Row 3: Duplicate number \'H1\', also on row 2', implode(' ', $Manager->getErrors()));
    }

    public function testCheckRejectsAFileWithNoContent(): void
    {
        $File = $this->makeUpload('csv', [static::HEADER]);

        $Manager = new ContentImportManager();
        $Manager->test_mode = TRUE;

        $this->assertFalse($Manager->checkImportFile($this->checkData('csv', $File)));
        $this->assertStringContainsString('No content found', implode(' ', $Manager->getErrors()));
    }

    /**
     * A formula is imported as the result saved with the file, as Excel and LibreOffice do
     */
    public function testExcelFormulasImportTheirSavedResult(): void
    {
        $module = 'zz_import_' . bin2hex(random_bytes(3));
        $File   = $this->makeUpload('xlsx', [
            static::HEADER,
            ['=CONCAT("H","0001")', '', '', '', '=CONCAT("Fa","ther")', '=LOWER("FATHER")'],
            ['G25', '', '', '', 'To love', '=FALSE()'],
        ]);

        try {
            $Manager = new ContentImportManager();
            $this->assertTrue($Manager->checkImportFile($this->checkData('excel', $File)), implode(PHP_EOL, (array) $Manager->getErrors()));

            $Manager = $this->runImport('excel', $Manager->sanitized_filename, $module);
            $this->assertFalse($Manager->hasErrors(), implode(PHP_EOL, (array) $Manager->getErrors()));

            $rows = Volume::findByTypeAndModule('strongs', $module)->content()->newQuery()->get()->keyBy('number');

            $this->assertSame(['H1', 'G25'], $rows->keys()->all());
            $this->assertSame('Father', $rows['H1']->definition);
            $this->assertSame('father', $rows['H1']->short_definition);
            $this->assertSame('0', $rows['G25']->short_definition);
        }
        finally {
            $this->removeVolume($module);
        }
    }

    /**
     * A file written without calculating its formulas has no result to import
     */
    public function testCheckRejectsAnExcelFormulaWithNoSavedResult(): void
    {
        $File = $this->makeUpload('xlsx', [
            static::HEADER,
            ['H1', '', '', '', '=CONCAT("a","b")', ''],
        ], FALSE);

        $Manager = new ContentImportManager();
        $Manager->test_mode = TRUE;

        $this->assertFalse($Manager->checkImportFile($this->checkData('excel', $File)));
        $this->assertStringContainsString('Row 2, column E contains a formula with no saved result', implode(' ', $Manager->getErrors()));
    }

    /**
     * `_file` comes back from the client: a name outside the upload directory is refused
     * before any volume is created
     */
    public function testImportRefusesAFileOutsideTheUploadDirectory(): void
    {
        $module = 'zz_import_' . bin2hex(random_bytes(3));

        try {
            foreach(['../../.env', '../strongs_en_orig.zip', 'missing.csv'] as $file) {
                $Manager = $this->runImport('csv', $file, $module);

                $this->assertTrue($Manager->hasErrors(), $file);
                $this->assertNull(Volume::findByTypeAndModule('strongs', $module), $file);
            }
        }
        finally {
            $this->removeVolume($module);
        }
    }

    /**
     * The importer's own directory holds the official module files: a bare name finds only
     * an upload, never a file there
     */
    public function testAFileNameDoesNotResolveToAnOfficialModuleFile(): void
    {
        $Importer = new \App\ContentImporters\Csv\CsvStrongs('strongs');
        $name     = 'zz_import_' . bin2hex(random_bytes(3)) . '.csv';
        $official = $Importer->getImportDir() . $name;
        $upload   = $Importer->getUploadDir() . $name;

        file_put_contents($official, 'Number,Definition');

        try {
            $this->assertNull($Importer->resolveImportFile($name));

            file_put_contents($upload, 'Number,Definition');
            $this->assertSame(realpath($upload), $Importer->resolveImportFile($name));
        }
        finally {
            foreach([$official, $upload] as $path) {
                is_file($path) && unlink($path);
            }
        }
    }

    /**
     * A row past the checked ones fails the import part way: the volume and its content
     * table must not survive
     */
    public function testAFailedImportLeavesNoVolumeOrTable(): void
    {
        $module = 'zz_import_' . bin2hex(random_bytes(3));
        $rows   = [static::HEADER];

        for($i = 1; $i <= 600; $i++) {
            $rows[] = ['H' . $i, '', '', '', 'Entry ' . $i, ''];
        }

        $rows[] = ['H5', '', '', '', 'Duplicate', ''];
        $File = $this->makeUpload('csv', $rows);

        // The rollback must not count as a deletion statics_changed reports.  Any class: model
        // events are per class, and the volume is the type's own (Strongs)
        $deleted = FALSE;
        \Illuminate\Support\Facades\Event::listen('eloquent.deleted: *', function(string $event, array $models) use ($module, &$deleted) {
            $deleted = $deleted || ($models[0] instanceof Volume && $models[0]->module === $module);
        });

        try {
            $Manager = new ContentImportManager();
            $this->assertTrue($Manager->checkImportFile($this->checkData('csv', $File)), implode(PHP_EOL, (array) $Manager->getErrors()));

            $Manager = $this->runImport('csv', $Manager->sanitized_filename, $module);

            $this->assertStringContainsString('Duplicate number \'H5\'', implode(' ', $Manager->getErrors()));
            $this->assertNull(Volume::findByTypeAndModule('strongs', $module));
            $this->assertFalse($deleted, 'The rollback fired the deleted event, recording a deletion');
            $this->assertFalse(Schema::hasTable(StrongsContent::getContentTableName('strongs', $module)));
        }
        finally {
            $this->removeVolume($module);
        }
    }

    /**
     * A row with no definition takes its tvm entry, as the legacy migration does: Count segment
     * removed, flagged is_special.  A row with a definition keeps it.
     */
    public function testTvmFillsAnEmptyDefinition(): void
    {
        $module  = 'zz_import_' . bin2hex(random_bytes(3));
        $mapping = ['first_row_data' => 2, 'col_A' => 'number', 'col_B' => 'definition', 'col_C' => 'tvm'];
        $File    = $this->makeUpload('csv', [
            ['Number', 'Definition', 'TVM'],
            ['G25', 'To love', 'ignored'],
            ['G5719', '', '<b>Count:</b> 1 <i>x</i><br>Present Active Indicative'],
        ]);

        try {
            $Manager = new ContentImportManager();
            $this->assertTrue($Manager->checkImportFile($this->checkData('csv', $File, $mapping)), implode(PHP_EOL, (array) $Manager->getErrors()));

            $Manager = $this->runImport('csv', $Manager->sanitized_filename, $module, $mapping);
            $this->assertFalse($Manager->hasErrors(), implode(PHP_EOL, (array) $Manager->getErrors()));

            $rows = Volume::findByTypeAndModule('strongs', $module)->content()->newQuery()->get()->keyBy('number');

            $this->assertSame('To love', $rows['G25']->definition);
            $this->assertSame(0, (int) $rows['G25']->is_special);
            $this->assertSame('Present Active Indicative', $rows['G5719']->definition);
            $this->assertSame(1, (int) $rows['G5719']->is_special);
        }
        finally {
            $this->removeVolume($module);
        }
    }

    /**
     * Replacing a volume's content with a file that fails part way puts the volume back as it
     * was: its name, its install and enabled flags, and its content.  No backup table is left.
     */
    public function testAFailedReplaceKeepsTheExistingVolume(): void
    {
        $module = 'zz_import_' . bin2hex(random_bytes(3));
        $rows   = [static::HEADER];

        for($i = 1; $i <= 600; $i++) {
            $rows[] = ['H' . $i, '', '', '', 'Entry ' . $i, ''];
        }

        $rows[] = ['H5', '', '', '', 'Duplicate', ''];

        try {
            $Manager = new ContentImportManager();
            $this->assertTrue($Manager->checkImportFile($this->checkData('csv', $this->makeUpload('csv', array_merge([static::HEADER], static::ROWS)))));
            $Manager = $this->runImport('csv', $Manager->sanitized_filename, $module);
            $this->assertFalse($Manager->hasErrors(), implode(PHP_EOL, (array) $Manager->getErrors()));

            $Volume = Volume::findByTypeAndModule('strongs', $module);
            $table  = $Volume->content()->getTable();

            $Manager = new ContentImportManager();
            $this->assertTrue($Manager->checkImportFile($this->checkData('csv', $this->makeUpload('csv', $rows))));

            $this->assertFalse($Manager->importFile([
                'name'          => 'Replacement Name',
                'shortname'     => 'Replaced',
                '_importer'     => 'csv',
                '_content_type' => 'strongs',
                '_file'         => $Manager->sanitized_filename,
                '_settings'     => json_encode(static::MAPPING),
            ], $Volume));

            $this->assertStringContainsString('Duplicate number \'H5\'', implode(' ', $Manager->getErrors()));

            $Volume = $Volume->fresh();
            $this->assertSame('Import Test ' . $module, $Volume->name);
            $this->assertSame(1, (int) $Volume->installed);
            $this->assertSame(1, (int) $Volume->enabled);
            $this->assertNotNull($Volume->installed_at);
            $this->assertSame(['G25', 'H1', 'H2'], $Volume->content()->newQuery()->orderBy('number')->pluck('number')->all());
            $this->assertNotEmpty(Schema::getIndexes($table), 'The restored table has lost its indexes');
            $this->assertFalse(Schema::hasTable(StrongsContent::backupTableName($table)));
        }
        finally {
            $this->removeVolume($module);
        }
    }

    /**
     * A successful replace keeps the volume's enabled state: a volume the admin disabled stays
     * disabled, and an enabled one stays enabled
     */
    public function testAReplaceKeepsTheEnabledState(): void
    {
        $module = 'zz_import_' . bin2hex(random_bytes(3));

        try {
            $Manager = new ContentImportManager();
            $this->assertTrue($Manager->checkImportFile($this->checkData('csv', $this->makeUpload('csv', array_merge([static::HEADER], static::ROWS)))));
            $Manager = $this->runImport('csv', $Manager->sanitized_filename, $module);
            $this->assertFalse($Manager->hasErrors(), implode(PHP_EOL, (array) $Manager->getErrors()));

            foreach([0, 1] as $enabled) {
                $Volume = Volume::findByTypeAndModule('strongs', $module);
                $Volume->enabled = $enabled;
                $Volume->save();

                $Manager = new ContentImportManager();
                $this->assertTrue($Manager->checkImportFile($this->checkData('csv', $this->makeUpload('csv', array_merge([static::HEADER], static::ROWS)))));

                $this->assertTrue($Manager->importFile([
                    '_importer'     => 'csv',
                    '_content_type' => 'strongs',
                    '_file'         => $Manager->sanitized_filename,
                    '_settings'     => json_encode(static::MAPPING),
                ], $Volume), implode(PHP_EOL, (array) $Manager->getErrors()));

                $Volume = $Volume->fresh();
                $this->assertSame(1, (int) $Volume->installed, 'enabled ' . $enabled);
                $this->assertSame($enabled, (int) $Volume->enabled, 'enabled ' . $enabled);
            }
        }
        finally {
            $this->removeVolume($module);
        }
    }

    /**
     * Source file as an UploadedFile, as an HTTP upload would arrive
     *
     * @param string $extension csv or xlsx
     * @param array<int, array<int, string>> $rows
     * @param bool $calculate_formulas FALSE writes formulas without their result, as a script might
     * @return UploadedFile
     */
    protected function makeUpload(string $extension, array $rows, bool $calculate_formulas = TRUE): UploadedFile
    {
        $name = 'strongs_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $path = $this->dir . '/' . $name;

        if($extension == 'csv') {
            $handle = fopen($path, 'w');

            foreach($rows as $row) {
                fputcsv($handle, $row, ',', '"', '\\');
            }

            fclose($handle);
        }
        else {
            $Spreadsheet = new Spreadsheet();
            $Sheet = $Spreadsheet->getActiveSheet();

            foreach($rows as $r => $row) {
                foreach($row as $c => $value) {
                    $Sheet->setCellValue([$c + 1, $r + 1], $value);
                }
            }

            $Writer = new Xlsx($Spreadsheet);
            $Writer->setPreCalculateFormulas($calculate_formulas);
            $Writer->save($path);
            $Spreadsheet->disconnectWorksheets();
        }

        $this->uploads[] = Volume::getModulePath('strongs') . 'uploads/' . $name;

        return new UploadedFile($path, $name, NULL, NULL, TRUE);
    }

    /**
     * Source file with exactly these bytes, for what makeUpload()'s writers would not produce
     */
    protected function makeRawUpload(string $extension, string $contents): UploadedFile
    {
        $name = 'strongs_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $contents);

        $this->uploads[] = Volume::getModulePath('strongs') . 'uploads/' . $name;

        return new UploadedFile($path, $name, NULL, NULL, TRUE);
    }

    protected function checkData(string $importer, UploadedFile $File, ?array $mapping = NULL): array
    {
        return array_merge($mapping ?? static::MAPPING, [
            'importer'     => $importer,
            'content_type' => 'strongs',
            'file'         => $File,
        ]);
    }

    protected function runImport(string $importer, ?string $file, string $module, ?array $mapping = NULL): ContentImportManager
    {
        $Manager = new ContentImportManager();

        $Manager->importFile([
            'name'          => 'Import Test ' . $module,
            'shortname'     => 'IT ' . $module,
            'module'        => $module,
            'language'      => 'en',
            'copyright_id'  => Copyright::first()->id,
            '_importer'     => $importer,
            '_content_type' => 'strongs',
            '_file'         => $file,
            '_settings'     => json_encode($mapping ?? static::MAPPING),
        ]);

        return $Manager;
    }

    protected function removeVolume(string $module): void
    {
        foreach(Volume::where('type', 'strongs')->where('module', $module)->get() as $Volume) {
            $Volume->content()->uninstall();
            $Volume->forceDelete();
        }

        $table = StrongsContent::getContentTableName('strongs', $module);
        Schema::dropIfExists($table);
        Schema::dropIfExists(StrongsContent::backupTableName($table));
    }
}
