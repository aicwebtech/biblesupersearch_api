<?php

namespace Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use App\Console\Commands\ImportBibleEvening;

/**
 * The list of Bibles an import command offers.
 *
 * Every importer directory now holds an 'uploads' subdirectory - that is where an upload made
 * over HTTP is stored - and it ships in the repository, so it is present in a fresh checkout.
 * An importer that filters on no extension at all would otherwise offer that directory as a
 * file to import.
 *
 * ImportBibleEvening is the one with an empty $file_extension, so it is the case that has to
 * hold. Constructing a command only parses its signature: no container, no database.
 */
class ImportBibleFileListTest extends TestCase
{
    /**
     * _getFileList is protected; exercise it the way the command does.
     *
     * No setAccessible() call: reflection has ignored visibility since PHP 8.1 and the method is
     * deprecated in 8.5, which CI runs.
     *
     * @return array<int, string>
     */
    private function fileList(ImportBibleEvening $Command): array
    {
        $method = new \ReflectionMethod($Command, '_getFileList');

        return $method->invoke($Command);
    }

    public function testTheFileListSkipsSubdirectories(): void
    {
        $Command = new ImportBibleEvening();

        $dir  = rtrim($Command->getImportDir(), '/') . '/';
        $list = $this->fileList($Command);

        $this->assertDirectoryExists($dir . 'uploads', 'the uploads directory is what this test is about');
        $this->assertNotContains('uploads', $list, 'a directory is not a Bible to import');

        foreach($list as $item) {
            $this->assertFileExists($dir . $item);
            $this->assertTrue(is_file($dir . $item), $item . ' is not a file');
        }
    }

    /**
     * The other half of it: an importer with no extension filter still has to offer the files
     * that really are there, which is the whole point of the listing.
     */
    public function testTheFileListStillOffersOrdinaryFiles(): void
    {
        $Command = new ImportBibleEvening();

        $dir  = rtrim($Command->getImportDir(), '/') . '/';
        $name = 'import_file_list_fixture_' . bin2hex(random_bytes(8)) . '.txt';

        file_put_contents($dir . $name, 'fixture');

        try {
            $this->assertContains($name, $this->fileList($Command));
        }
        finally {
            @unlink($dir . $name);
        }
    }
}
