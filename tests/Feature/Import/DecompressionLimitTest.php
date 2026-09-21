<?php

namespace Tests\Feature\Import;

use Tests\TestCase;
use App\Importers\MySword;

/**
 * The gzip extraction loop had no output cap, so a small archive could expand
 * without bound and fill the disk.
 */
class DecompressionLimitTest extends TestCase
{
    /**
     * Extraction stops at the configured ceiling and leaves no partial file.
     */
    public function testGzipExtractionStopsAtLimit(): void
    {
        $Importer = new MySword();
        $dir = $Importer->getImportDir();
        $stem = 'bomb_fixture_' . bin2hex(random_bytes(4));
        $gz_path = $dir . $stem . '.mybible.gz';
        $out_path = $dir . $stem . '.mybible';

        // Highly compressible payload: small on disk, large when expanded.
        file_put_contents($gz_path, gzencode(str_repeat('A', 512 * 1024)));

        try {
            config(['bible.max_decompressed_bytes' => 1024]); // 1 KiB ceiling

            $result = $this->callGetSQLite($Importer, $gz_path, $stem . '.mybible.gz');

            $this->assertFalse((bool) $result, 'Extraction should have been refused');
            $this->assertTrue($Importer->hasErrors());
            $this->assertStringContainsString('exceeds the allowed limit', implode(' ', $Importer->getErrors()));
            $this->assertFileDoesNotExist($out_path, 'Partial output must be removed');
        }
        finally {
            $this->removeIfPresent($gz_path);
            $this->removeIfPresent($out_path);
        }
    }

    /**
     * Below the ceiling, extraction still works.
     */
    public function testGzipExtractionSucceedsUnderLimit(): void
    {
        $Importer = new MySword();
        $dir = $Importer->getImportDir();
        $stem = 'ok_fixture_' . bin2hex(random_bytes(4));
        $gz_path = $dir . $stem . '.mybible.gz';
        $out_path = $dir . $stem . '.mybible';

        file_put_contents($gz_path, gzencode('not a real sqlite file'));

        try {
            config(['bible.max_decompressed_bytes' => 1048576]);

            $this->callGetSQLite($Importer, $gz_path, $stem . '.mybible.gz');

            // The payload is not a valid database, so we only assert that the
            // extraction itself ran and produced the file.
            $this->assertFileExists($out_path);
            $this->assertSame('not a real sqlite file', file_get_contents($out_path));
        }
        finally {
            $this->removeIfPresent($gz_path);
            $this->removeIfPresent($out_path);
        }
    }

    /**
     * A traversing filename must never write outside the importer directory.
     *
     * sanitizeFileName() neutralises the traversal rather than rejecting it, so
     * the assertion is containment: whatever gets written stays in the importer
     * directory and nothing appears at the traversed target.
     */
    public function testTraversalFilenameCannotEscapeImportDirectory(): void
    {
        $Importer = new MySword();
        $dir = realpath($Importer->getImportDir());
        $escaped = dirname($dir) . DIRECTORY_SEPARATOR . 'evil.mybible';
        $contained = $dir . DIRECTORY_SEPARATOR . 'evil.mybible';

        try {
            $this->callGetSQLite($Importer, __FILE__, '../../evil.mybible.gz');

            $this->assertFileDoesNotExist($escaped, 'Wrote outside the importer directory');
        }
        finally {
            $this->removeIfPresent($escaped);
            $this->removeIfPresent($contained);
        }
    }

    /**
     * @param  string  $path
     * @return void
     */
    protected function removeIfPresent(string $path): void
    {
        if(file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * _getSQLite is private; exercise it the way the importer does.
     *
     * No setAccessible() call: reflection has ignored visibility since PHP 8.1 and the method is
     * deprecated in 8.5, which CI runs.
     */
    protected function callGetSQLite(MySword $Importer, string $path, string $name)
    {
        $method = new \ReflectionMethod(MySword::class, '_getSQLite');

        try {
            return $method->invoke($Importer, $path, $name);
        }
        catch(\Throwable $e) {
            return FALSE; // an invalid SQLite payload throws; not what we assert on
        }
    }
}
