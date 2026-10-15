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

    public function testCheckFailsWithoutARequiredMapping(): void
    {
        $File    = $this->makeUpload('csv', array_merge([static::HEADER], static::ROWS));
        $mapping = static::MAPPING;
        unset($mapping['col_A']);

        $Manager = new ContentImportManager();
        $Manager->test_mode = TRUE;

        $this->assertFalse($Manager->checkImportFile($this->checkData('csv', $File, $mapping)));
        $this->assertStringContainsString('Please specify a column for Number', implode(' ', $Manager->getErrors()));
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

    public function testCheckRejectsAFormulaInExcel(): void
    {
        $File = $this->makeUpload('xlsx', [
            static::HEADER,
            ['H1', '', '', '', '=CONCAT("a","b")', ''],
        ]);

        $Manager = new ContentImportManager();
        $Manager->test_mode = TRUE;

        $this->assertFalse($Manager->checkImportFile($this->checkData('excel', $File)));
        $this->assertStringContainsString('Row 2, column E contains a formula', implode(' ', $Manager->getErrors()));
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

        try {
            $Manager = new ContentImportManager();
            $this->assertTrue($Manager->checkImportFile($this->checkData('csv', $File)), implode(PHP_EOL, (array) $Manager->getErrors()));

            $Manager = $this->runImport('csv', $Manager->sanitized_filename, $module);

            $this->assertStringContainsString('Duplicate number \'H5\'', implode(' ', $Manager->getErrors()));
            $this->assertNull(Volume::findByTypeAndModule('strongs', $module));
            $this->assertFalse(Schema::hasTable(StrongsContent::getContentTableName('strongs', $module)));
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
     * @return UploadedFile
     */
    protected function makeUpload(string $extension, array $rows): UploadedFile
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

            (new Xlsx($Spreadsheet))->save($path);
            $Spreadsheet->disconnectWorksheets();
        }

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

        Schema::dropIfExists(StrongsContent::getContentTableName('strongs', $module));
    }
}
