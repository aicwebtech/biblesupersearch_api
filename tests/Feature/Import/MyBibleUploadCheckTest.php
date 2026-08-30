<?php

namespace Tests\Feature\Import;

use App\Importers\MyBible;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * checkUploadedFile() is the gate an uploaded module passes before an import is offered.
 *
 * It had never run: the class in MyBible.php was named MySword, so App\Importers\MyBible was
 * not autoloadable at all, and the method read a $SQLITE variable that was never opened and
 * queried the MySword table layout, which the MyBible format does not have.
 *
 * Each test builds a throwaway .SQLite3 module in the system temp dir and removes it again.
 */
class MyBibleUploadCheckTest extends TestCase
{
    /** @var string[] */
    private array $paths = [];

    public function tearDown(): void
    {
        foreach($this->paths as $path) {
            if(is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    private function tempPath(): string
    {
        $path = sys_get_temp_dir() . '/bss-mybible-' . uniqid() . '.SQLite3';
        $this->paths[] = $path;

        return $path;
    }

    /**
     * Builds a minimal MyBible module: an info table of name/value pairs and a verses table
     * keyed by book_number/chapter/verse.
     *
     * @param  array<int, array{int, int, int, string}>  $verses
     */
    private function makeModule(array $verses): string
    {
        $path   = $this->tempPath();
        $SQLITE = new \SQLite3($path);

        $SQLITE->exec('CREATE TABLE info (name TEXT, value TEXT)');
        $SQLITE->exec("INSERT INTO info (name, value) VALUES ('description', 'Test MyBible Module')");
        $SQLITE->exec("INSERT INTO info (name, value) VALUES ('abbreviation', 'TESTMB')");
        $SQLITE->exec("INSERT INTO info (name, value) VALUES ('language', 'en')");

        $SQLITE->exec('CREATE TABLE verses (book_number INTEGER, chapter INTEGER, verse INTEGER, text TEXT)');

        foreach($verses as $verse) {
            $SQLITE->exec(vsprintf(
                "INSERT INTO verses (book_number, chapter, verse, text) VALUES (%d, %d, %d, '%s')",
                [$verse[0], $verse[1], $verse[2], \SQLite3::escapeString($verse[3])]
            ));
        }

        $SQLITE->close();

        return $path;
    }

    private function upload(string $path): UploadedFile
    {
        return new UploadedFile($path, basename($path), NULL, NULL, TRUE);
    }

    public function testAValidModuleIsAcceptedAndItsMetaIsMapped(): void
    {
        $Importer = new MyBible();

        $accepted = $Importer->checkUploadedFile($this->upload($this->makeModule([
            [10, 1, 1, 'In the beginning God created the heaven and the earth.'],
        ])));

        $this->assertTrue($accepted, implode(' ', $Importer->getErrors()));
        $this->assertFalse($Importer->hasErrors());

        $attributes = $Importer->getBibleAttributes();

        $this->assertSame('Test MyBible Module', $attributes['name']);
        $this->assertSame('TESTMB', $attributes['shortname']);
    }

    /**
     * A module whose verses table is empty - or holds nothing but blank rows - must be
     * rejected rather than imported into an empty Bible.
     */
    public function testAModuleWithNoUsableVersesIsRejected(): void
    {
        $Importer = new MyBible();

        $this->assertFalse($Importer->checkUploadedFile($this->upload($this->makeModule([
            [0, 0, 0, ''],
        ]))));

        $this->assertTrue($Importer->hasErrors());
        $this->assertStringContainsString('no verses', implode(' ', $Importer->getErrors()));
    }

    /**
     * The info table carries the module's meta; without it there is nothing to map, and the
     * failure has to arrive as a recorded error rather than as a fatal on a FALSE result set.
     */
    public function testAModuleWithoutAnInfoTableIsRejected(): void
    {
        $path   = $this->tempPath();
        $SQLITE = new \SQLite3($path);
        $SQLITE->exec('CREATE TABLE verses (book_number INTEGER, chapter INTEGER, verse INTEGER, text TEXT)');
        $SQLITE->close();

        $Importer = new MyBible();

        $this->assertFalse($Importer->checkUploadedFile($this->upload($path)));
        $this->assertTrue($Importer->hasErrors());
    }

    /**
     * An upload that is not a SQLite database at all must be turned away with an error. The
     * method opened no database of its own until BSS-285, so this arrived as a TypeError -
     * an Error, which the method's own catch(\Exception) does not catch.
     */
    public function testAFileThatIsNotASqliteDatabaseIsRejected(): void
    {
        $path = $this->tempPath();
        file_put_contents($path, 'this is not a database');

        $Importer = new MyBible();

        $this->assertFalse($Importer->checkUploadedFile($this->upload($path)));
        $this->assertTrue($Importer->hasErrors());
        $this->assertNotEmpty($Importer->getErrors());
    }
}
