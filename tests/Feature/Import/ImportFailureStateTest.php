<?php

namespace Tests\Feature\Import;

use App\Importers\Database as Importer;
use App\Models\Books\BookAbstract;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

/**
 * A failed import must not leave process-wide state behind.
 *
 * app:install-testing catches one language's failure and carries on installing the next 48, so
 * anything a throw leaks - a lifted mass-assignment guard, a half-filled insert buffer - is
 * inherited by every import that follows it.
 */
class ImportFailureStateTest extends TestCase
{
    /** A book table name nothing will ever create. */
    private const MISSING_TABLE = 'books_no_such_table_zz';

    public function tearDown(): void
    {
        Model::reguard();
        $this->resetImporterState();

        parent::tearDown();
    }

    /**
     * Clears the importer's static buffer and its processed-file list, so each test starts from
     * a known state regardless of what ran before it in this process.
     */
    private function resetImporterState(): void
    {
        foreach(['insertable' => [], 'insert_count' => 0, 'insert_model' => NULL, 'processed_files' => []] as $name => $value) {
            $Property = new \ReflectionProperty(Importer::class, $name);
            $Property->setAccessible(TRUE);
            $Property->setValue(NULL, $value);
        }
    }

    /**
     * @return mixed
     */
    private function importerState(string $name)
    {
        $Property = new \ReflectionProperty(Importer::class, $name);
        $Property->setAccessible(TRUE);

        return $Property->getValue();
    }

    /**
     * Writes a throwaway book-list CSV and returns [directory, filename].
     *
     * @return array{0: string, 1: string}
     */
    private function writeProbeCsv(int $rows = 3): array
    {
        $dir  = sys_get_temp_dir() . '/bss_import_failure_' . getmypid();
        $file = 'probe.csv';

        if(!is_dir($dir)) {
            mkdir($dir, 0775, TRUE);
        }

        $lines = ['id,name,shortname,matching1,matching2'];

        for($i = 1; $i <= $rows; $i++) {
            $lines[] = $i . ',Book ' . $i . ',B' . $i . ',,';
        }

        file_put_contents($dir . '/' . $file, implode(PHP_EOL, $lines));

        return [$dir, $file];
    }

    private function removeProbeCsv(string $dir, string $file): void
    {
        @unlink($dir . '/' . $file);
        @rmdir($dir);
    }

    /**
     * An insert that throws must still drop the buffer. Otherwise the next caller appends to the
     * failed rows and pushes them into a different model's table.
     */
    public function testAFailedPushClearsTheInsertBuffer(): void
    {
        $this->resetImporterState();
        [$dir, $file] = $this->writeProbeCsv();

        try {
            Importer::importCSV($file, ['id', 'name', 'shortname', 'matching1', 'matching2'], MissingTableBook::class, 'id', $dir, 100);
            $this->fail('The import was expected to throw');
        }
        catch(\Illuminate\Database\QueryException $e) {
            // Expected - the target table does not exist.
        }
        finally {
            $this->assertSame([], $this->importerState('insertable'), 'The failed rows were left in the buffer');
            $this->assertSame(0, $this->importerState('insert_count'), 'The buffered row count was left set');
            $this->assertNull($this->importerState('insert_model'), 'The failed model was left as the buffer target');

            $this->removeProbeCsv($dir, $file);
        }
    }

    /**
     * A buffer left behind would be flushed into whichever model the next import names. This
     * proves the failed rows do not follow it there.
     */
    public function testRowsFromAFailedImportDoNotReachTheNextModel(): void
    {
        $this->resetImporterState();
        [$dir, $file] = $this->writeProbeCsv();

        $class_name = BookAbstract::getClassNameByLanguageStrict(config('bss.defaults.language_short'));
        $before     = $class_name::count();

        try {
            Importer::importCSV($file, ['id', 'name', 'shortname', 'matching1', 'matching2'], MissingTableBook::class, 'id', $dir, 100);
            $this->fail('The import was expected to throw');
        }
        catch(\Illuminate\Database\QueryException $e) {
            // Expected - the target table does not exist.
        }
        finally {
            $this->removeProbeCsv($dir, $file);
        }

        // Flushing now must be a no-op rather than a delivery of the failed rows.
        $Method = new \ReflectionMethod(Importer::class, '_directInsertPush');
        $Method->setAccessible(TRUE);
        $Method->invoke(NULL);

        $this->assertEquals($before, $class_name::count(), 'Rows from the failed import reached another table');
    }

    /**
     * migrateFromCsv() lifts the mass-assignment guard around the import. If the import throws,
     * the guard has to go back on, or Eloquent stays globally unguarded for the whole process.
     */
    public function testAFailedBookImportRestoresTheMassAssignmentGuard(): void
    {
        $this->resetImporterState();

        // A regional code carries a permanent model class, so it resolves strictly without
        // needing its table - which lets the import get past the guard and fail on the insert.
        $language = 'zh_cn';
        $table    = 'books_' . $language;

        $this->assertNotFalse(BookAbstract::getClassNameByLanguageStrict($language));
        $this->assertFalse(Model::isUnguarded(), 'Eloquent was already unguarded before this test');

        $existed = \Schema::hasTable($table);
        \Schema::dropIfExists($table);

        try {
            BookAbstract::migrateFromCsv($language);
            $this->fail('The import was expected to throw');
        }
        catch(\Throwable $e) {
            $this->assertFalse(Model::isUnguarded(), 'The mass-assignment guard was left off after a failed import');
        }
        finally {
            $this->resetImporterState();

            if($existed) {
                BookAbstract::createTableAndMigrateFromCsv($language);
            }
        }

        $this->assertEquals($existed, \Schema::hasTable($table), 'The book table was not restored');
    }
}

/** Points at a table that does not exist, so every insert against it throws. */
class MissingTableBook extends BookAbstract
{
    protected $table = 'books_no_such_table_zz';
}
