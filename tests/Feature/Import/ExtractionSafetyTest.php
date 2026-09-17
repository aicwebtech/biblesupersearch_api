<?php

namespace Tests\Feature\Import;

use Tests\TestCase;
use App\Importers\MySword;

/**
 * Extraction used to write straight onto its destination with fopen(..., 'wb'), which
 * truncates. An already extracted database was therefore destroyed before a single byte
 * of the replacement had been verified, and the failure cleanup then deleted what was
 * left of it -- so a rejected upload cost the operator a good file.
 */
class ExtractionSafetyTest extends TestCase
{
    /**
     * @return string
     */
    protected function importDir(): string
    {
        return rtrim(realpath((new MySword())->getImportDir()), DIRECTORY_SEPARATOR);
    }

    /**
     * Call the private extraction entry point.
     *
     * @param  string  $path
     * @param  string  $orig_filename
     * @return mixed
     */
    protected function extract(MySword $Importer, string $path, string $orig_filename)
    {
        $method = new \ReflectionMethod(MySword::class, '_getSQLite');

        return $method->invoke($Importer, $path, $orig_filename);
    }

    /**
     * An upload that busts the decompressed-size limit must leave the previously
     * extracted database exactly as it was.
     */
    public function testARejectedUploadDoesNotDestroyTheExistingDatabase(): void
    {
        $dir = $this->importDir();
        $stem = 'extract_safety_' . bin2hex(random_bytes(4));

        $existing = $dir . DIRECTORY_SEPARATOR . $stem . '.mybible';
        $archive  = $dir . DIRECTORY_SEPARATOR . $stem . '.mybible.gz';

        file_put_contents($existing, 'THE GOOD DATABASE');

        // The cap is lowered rather than a gigabyte of payload built: the point is that the
        // write loop aborts part way, not how big the real ceiling is.
        config(['bible.max_decompressed_bytes' => 1024]);

        file_put_contents($archive, gzencode(str_repeat('A', 8192)));

        try {
            $Importer = new MySword();
            $result = $this->extract($Importer, $archive, $stem . '.mybible.gz');

            $this->assertFalse((bool) $result, 'The oversized archive must be refused');
            $this->assertTrue($Importer->hasErrors());

            $this->assertFileExists($existing, 'The existing database must survive a rejected upload');
            $this->assertSame(
                'THE GOOD DATABASE',
                file_get_contents($existing),
                'The existing database must be byte-identical'
            );

            $this->assertSame(
                [],
                glob($dir . DIRECTORY_SEPARATOR . $stem . '.mybible.part_*'),
                'No partial extraction may be left behind'
            );
        }
        finally {
            foreach(glob($dir . DIRECTORY_SEPARATOR . $stem . '*') ?: [] as $path) {
                @unlink($path);
            }
        }
    }

    /**
     * The happy path still replaces the destination, so the temp-file dance has not
     * broken ordinary extraction.
     */
    public function testASuccessfulExtractionReplacesTheDestination(): void
    {
        $dir = $this->importDir();
        $stem = 'extract_ok_' . bin2hex(random_bytes(4));

        $existing = $dir . DIRECTORY_SEPARATOR . $stem . '.mybible';
        $archive  = $dir . DIRECTORY_SEPARATOR . $stem . '.mybible.gz';

        file_put_contents($existing, 'THE OLD DATABASE');
        file_put_contents($archive, gzencode('THE NEW DATABASE'));

        try {
            $this->extract(new MySword(), $archive, $stem . '.mybible.gz');

            $this->assertSame(
                'THE NEW DATABASE',
                file_get_contents($existing),
                'A successful extraction must replace the destination'
            );

            $this->assertSame(
                [],
                glob($dir . DIRECTORY_SEPARATOR . $stem . '.mybible.part_*'),
                'The temp file must not be left behind'
            );
        }
        finally {
            foreach(glob($dir . DIRECTORY_SEPARATOR . $stem . '*') ?: [] as $path) {
                @unlink($path);
            }
        }
    }
}
