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
     * The .zip branch used ZipArchive::extractTo(), which writes straight onto the
     * destination and so truncated an existing database before the upload had been shown
     * to be whole -- the same defect the .gz branch had, left behind when that one was
     * fixed. Both now stream to a sibling temp file and rename only on success.
     *
     * Note this particular case is refused by the declared-size pre-check before any
     * extraction starts, so it passes with either implementation; it is here as an
     * end-to-end guard. The destructive case -- a failure *during* extraction -- cannot be
     * provoked through ZipArchive, so it is covered against the shared copier instead, by
     * testARejectedStreamLeavesTheDestinationUntouched() below.
     */
    public function testARejectedZipDoesNotDestroyTheExistingDatabase(): void
    {
        $dir = $this->importDir();
        $stem = 'zip_safety_' . bin2hex(random_bytes(4));

        $existing = $dir . DIRECTORY_SEPARATOR . $stem . '.mybible';
        $archive  = $dir . DIRECTORY_SEPARATOR . $stem . '.mybible.zip';

        file_put_contents($existing, 'THE GOOD DATABASE');

        $Zip = new \ZipArchive();
        $Zip->open($archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $Zip->addFromString($stem . '.mybible', str_repeat('B', 8192));
        $Zip->close();

        // Cap lowered so the copy aborts part way rather than needing a huge payload.
        config(['bible.max_decompressed_bytes' => 1024]);

        try {
            $Importer = new MySword();
            $result = $this->extract($Importer, $archive, $stem . '.mybible.zip');

            $this->assertFalse((bool) $result, 'The oversized entry must be refused');
            $this->assertSame(
                'THE GOOD DATABASE',
                file_get_contents($existing),
                'The existing database must survive a rejected zip upload'
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
     * A well-formed zip still extracts, so the temp-file route has not broken it.
     */
    public function testASuccessfulZipExtractionReplacesTheDestination(): void
    {
        $dir = $this->importDir();
        $stem = 'zip_ok_' . bin2hex(random_bytes(4));

        $existing = $dir . DIRECTORY_SEPARATOR . $stem . '.mybible';
        $archive  = $dir . DIRECTORY_SEPARATOR . $stem . '.mybible.zip';

        file_put_contents($existing, 'THE OLD DATABASE');

        $Zip = new \ZipArchive();
        $Zip->open($archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $Zip->addFromString($stem . '.mybible', 'THE NEW DATABASE');
        $Zip->close();

        try {
            $this->extract(new MySword(), $archive, $stem . '.mybible.zip');

            $this->assertSame('THE NEW DATABASE', file_get_contents($existing));

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

    /**
     * Both branches now funnel through _extractStreamToFile(), which is where the
     * guarantee actually lives: nothing touches the destination until the whole stream has
     * been read and written. Driving it directly is the only way to exercise a failure
     * *during* extraction -- neither gzip nor ZipArchive can be made to fail mid-stream on
     * demand.
     *
     * @param  callable  $read
     * @param  callable|null  $eof
     * @return true|string
     */
    protected function copyStream(string $uz_path, callable $read, ?callable $eof = null)
    {
        $method = new \ReflectionMethod(MySword::class, '_extractStreamToFile');

        return $method->invoke(
            new MySword(),
            $uz_path,
            $eof ?: function() { return FALSE; },
            $read,
            'read failed',
            'too big'
        );
    }

    public function testARejectedStreamLeavesTheDestinationUntouched(): void
    {
        $dir = $this->importDir();
        $stem = 'copier_fail_' . bin2hex(random_bytes(4));
        $dest = $dir . DIRECTORY_SEPARATOR . $stem . '.mybible';

        file_put_contents($dest, 'THE GOOD DATABASE');

        $calls = 0;

        try {
            $result = $this->copyStream($dest, function($size) use (&$calls) {
                $calls++;

                // Some bytes land, then the source fails -- exactly the state that used to
                // leave the destination truncated.
                return $calls > 1 ? FALSE : str_repeat('X', 128);
            });

            $this->assertSame('read failed', $result, 'The failure must be reported');
            $this->assertSame(
                'THE GOOD DATABASE',
                file_get_contents($dest),
                'The destination must not have been touched'
            );
            $this->assertSame(
                [],
                glob($dir . DIRECTORY_SEPARATOR . $stem . '.mybible.part_*'),
                'The partial temp file must be cleaned up'
            );
        }
        finally {
            foreach(glob($dir . DIRECTORY_SEPARATOR . $stem . '*') ?: [] as $path) {
                @unlink($path);
            }
        }
    }

    /**
     * An oversized stream is stopped by the byte counter, not by any header the archive
     * happens to declare -- which is what a zip bomb or a lying size field needs.
     */
    public function testAnOversizedStreamIsStoppedMidCopy(): void
    {
        $dir = $this->importDir();
        $stem = 'copier_big_' . bin2hex(random_bytes(4));
        $dest = $dir . DIRECTORY_SEPARATOR . $stem . '.mybible';

        file_put_contents($dest, 'THE GOOD DATABASE');
        config(['bible.max_decompressed_bytes' => 1024]);

        try {
            $result = $this->copyStream($dest, function($size) {
                return str_repeat('Y', 4096);
            });

            $this->assertSame('too big', $result);
            $this->assertSame('THE GOOD DATABASE', file_get_contents($dest));
            $this->assertSame([], glob($dir . DIRECTORY_SEPARATOR . $stem . '.mybible.part_*'));
        }
        finally {
            foreach(glob($dir . DIRECTORY_SEPARATOR . $stem . '*') ?: [] as $path) {
                @unlink($path);
            }
        }
    }

    /**
     * A complete stream does replace the destination, so the guard has not simply made
     * extraction impossible.
     */
    public function testACompleteStreamReplacesTheDestination(): void
    {
        $dir = $this->importDir();
        $stem = 'copier_ok_' . bin2hex(random_bytes(4));
        $dest = $dir . DIRECTORY_SEPARATOR . $stem . '.mybible';

        file_put_contents($dest, 'THE OLD DATABASE');

        $done = FALSE;

        try {
            $result = $this->copyStream(
                $dest,
                function($size) use (&$done) { $done = TRUE; return 'THE NEW DATABASE'; },
                function() use (&$done) { return $done; }
            );

            $this->assertTrue($result);
            $this->assertSame('THE NEW DATABASE', file_get_contents($dest));
            $this->assertSame([], glob($dir . DIRECTORY_SEPARATOR . $stem . '.mybible.part_*'));
        }
        finally {
            foreach(glob($dir . DIRECTORY_SEPARATOR . $stem . '*') ?: [] as $path) {
                @unlink($path);
            }
        }
    }
}
