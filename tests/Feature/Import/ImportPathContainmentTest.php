<?php

namespace Tests\Feature\Import;

use Tests\TestCase;
use App\ImportManager;
use App\Importers\BibleSuperSearch;
use App\Importers\MySword;
use App\Models\Bible;

/**
 * The preflight step (checkImportFile) sanitizes the upload and hands the safe
 * name back to the client; the commit step (importFile) then receives that name
 * back as ordinary request input. It must not be trusted.
 */
class ImportPathContainmentTest extends TestCase
{
    protected function importer(): MySword
    {
        return new MySword();
    }

    /**
     * A traversal attempt must not resolve, however it is spelled.
     */
    public function testTraversalFilenamesDoNotResolve(): void
    {
        $Importer = $this->importer();

        $attempts = [
            '../../etc/passwd',
            '../modules/kjv.zip',
            '/etc/passwd',
            '....//....//etc/passwd',
            'subdir/file.mybible',
            '',
            null,
        ];

        foreach($attempts as $attempt) {
            $this->assertNull(
                $Importer->resolveImportFile($attempt),
                'Expected NULL for: ' . var_export($attempt, true)
            );
        }
    }

    /**
     * sanitizeFileName() strips separators and collapses '..', so validating *after* it
     * turned "../existing.mybible" into "existing.mybible" -- a name that passed the
     * basename test and resolved to a real staged file. Containment was never broken (the
     * resolved path still had to sit under the importer directory), but a traversing name
     * silently aliasing onto another staged file means the commit step can address a file
     * other than the one that was preflighted.
     */
    public function testATraversingNameIsNotAliasedOntoAnExistingFile(): void
    {
        $Importer = $this->importer();
        $dir = realpath($Importer->getImportDir());
        $name = 'alias_target_' . bin2hex(random_bytes(4)) . '.mybible';
        $path = $dir . DIRECTORY_SEPARATOR . $name;

        file_put_contents($path, 'a real staged file');

        try {
            // Precondition: the bare name resolves, so a NULL below is the guard working
            // rather than the file simply being absent.
            $this->assertNotNull($Importer->resolveImportFile($name));

            foreach(['../' . $name, '..\\' . $name, './' . $name, 'sub/' . $name] as $attempt) {
                $this->assertNull(
                    $Importer->resolveImportFile($attempt),
                    'Expected NULL for: ' . $attempt
                );

                $this->assertNull(
                    $Importer->safeImportFileName($attempt),
                    'safeImportFileName must agree: ' . $attempt
                );
            }
        }
        finally {
            @unlink($path);
        }
    }

    /**
     * A real file in the importer's own directory still resolves, so the guard
     * has not broken normal imports.
     */
    public function testLegitimateFilenameResolves(): void
    {
        $Importer = $this->importer();
        $dir = $Importer->getImportDir();
        $name = 'containment_fixture_' . bin2hex(random_bytes(4)) . '.mybible';
        $path = $dir . $name;

        file_put_contents($path, 'fixture');

        try {
            $resolved = $Importer->resolveImportFile($name);

            $this->assertNotNull($resolved);
            $this->assertSame(realpath($path), $resolved);
            $this->assertSame($name, $Importer->safeImportFileName($name));
        }
        finally {
            @unlink($path);
        }
    }

    /**
     * An upload goes into the importer's uploads subdirectory, never the importer
     * directory itself.
     *
     * They shared a directory until now, which is what made bibles:prune-imports
     * unsafe: it could not tell an abandoned upload from a source file an operator
     * had placed there for a CLI import, and deleted both after seven days.
     */
    public function testAnUploadIsStoredInTheUploadDirectory(): void
    {
        $Importer = new class extends MySword {
            // The storage location is what is under test, not the format check.
            public function checkUploadedFile(\Illuminate\Http\UploadedFile $File): bool
            {
                return TRUE;
            }
        };

        $name = 'upload_location_' . bin2hex(random_bytes(4)) . '.mybible';
        $upload_path = $Importer->getUploadDir() . $name;
        $import_path = $Importer->getImportDir() . $name;

        try {
            $this->assertTrue(
                $Importer->acceptUploadedFile(\Illuminate\Http\UploadedFile::fake()->createWithContent($name, 'an upload')),
                'The upload was rejected: ' . implode(', ', $Importer->getErrors())
            );

            $this->assertFileExists($upload_path, 'The upload must land in the uploads subdirectory');
            $this->assertFileDoesNotExist($import_path, 'Nothing may be written to the importer directory itself');

            // And the commit step still finds it by the name it was given back.
            $this->assertSame(realpath($upload_path), $Importer->resolveImportFile($name));
        }
        finally {
            foreach([$upload_path, $import_path] as $path) {
                if(file_exists($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * Both directories resolve, so a CLI source keeps working, and an upload wins a
     * name it shares with one: that is the file the preflight step just stored.
     */
    public function testAnUploadWinsOverASameNamedSourceFile(): void
    {
        $Importer = $this->importer();
        $name = 'both_trees_' . bin2hex(random_bytes(4)) . '.mybible';
        $upload_path = $Importer->getUploadDir() . $name;
        $source_path = $Importer->getImportDir() . $name;

        file_put_contents($source_path, 'placed by the operator for a CLI import');

        try {
            $this->assertSame(
                realpath($source_path),
                $Importer->resolveImportFile($name),
                'A source file with no upload beside it must still resolve'
            );

            $this->assertSame(
                rtrim($Importer->getImportDir(), '/'),
                rtrim($this->importFileDir($Importer, $name), '/'),
                'With no upload, the importer reads from its own directory'
            );

            file_put_contents($upload_path, 'just uploaded');

            $this->assertSame(
                realpath($upload_path),
                $Importer->resolveImportFile($name),
                'The upload must win the name'
            );

            $this->assertSame(
                rtrim($Importer->getUploadDir(), '/'),
                rtrim($this->importFileDir($Importer, $name), '/'),
                'And the importer must read it from there'
            );
        }
        finally {
            foreach([$upload_path, $source_path] as $path) {
                if(file_exists($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * A decompressed .mybible.zip / .gz is written beside the archive it came from, so
     * an extracted upload stays in the prunable tree instead of being left in the
     * operator's source directory.
     */
    public function testExtractionTargetsTheDirectoryTheFileCameFrom(): void
    {
        $Importer = $this->importer();
        $name = 'extract_from_' . bin2hex(random_bytes(4)) . '.mybible.gz';
        $upload_path = $Importer->getUploadDir() . $name;

        file_put_contents($upload_path, 'compressed');

        try {
            $Importer->file = $name;

            $this->assertSame(
                realpath($Importer->getUploadDir()) . DIRECTORY_SEPARATOR . 'extracted.mybible',
                $this->containedImportPath('extracted.mybible', $Importer)
            );
        }
        finally {
            @unlink($upload_path);
        }
    }

    /**
     * The preflight check decompresses too, and must also write into the upload
     * directory.
     *
     * getImportFileDir() distinguishes an upload from an operator-placed source by
     * finding the file in the upload directory, which the preflight step cannot
     * satisfy: acceptUploadedFile() runs the check before it has a name or a stored
     * file. So the check fell through to the importer directory -- which
     * bibles:prune-imports deliberately never touches -- and an import abandoned at the
     * confirmation step left its decompressed database, up to the 1 GiB extraction cap,
     * there for good. The archive beside it in uploads/ was pruned after seven days.
     */
    public function testAPreflightCheckExtractsIntoTheUploadDirectory(): void
    {
        $Importer = new class extends MySword {
            public $probe;

            // Stands in for the decompression the real check does before it can read
            // the database; the destination is what is under test.
            public function checkUploadedFile(\Illuminate\Http\UploadedFile $File): bool
            {
                $this->probe = $this->_containedImportPath('probe.mybible');

                return TRUE;
            }
        };

        $name = 'preflight_' . bin2hex(random_bytes(4)) . '.mybible.zip';
        $upload_path = $Importer->getUploadDir() . $name;

        try {
            $this->assertTrue(
                $Importer->acceptUploadedFile(\Illuminate\Http\UploadedFile::fake()->createWithContent($name, 'an upload')),
                'The upload was rejected: ' . implode(', ', $Importer->getErrors())
            );

            $this->assertSame(
                realpath($Importer->getUploadDir()) . DIRECTORY_SEPARATOR . 'probe.mybible',
                $Importer->probe,
                'A preflight check must decompress into the prunable uploads directory'
            );

            // And the answer is only forced while the check is running: with the name
            // cleared there is no upload to find, so the same importer must fall back
            // to its own directory the way a CLI import does.
            $Importer->file = NULL;

            $this->assertSame(
                rtrim($Importer->getImportDir(), '/'),
                rtrim($Importer->getImportFileDir(), '/'),
                'The preflight flag must not outlive the check'
            );
        }
        finally {
            @unlink($upload_path);
        }
    }

    /**
     * The Bible SuperSearch importer is the exception to the rule above: its upload is
     * the installed module archive itself, not a transient source that the commit step
     * consumes and forgets.
     *
     * It never reads $this->file. import() hands the module name to
     * Bible::createFromModuleFile() / Bible::install(), and the archive is opened by
     * Bible::openModuleFileByModule(), which looks only in bibles/modules and
     * bibles/unofficial. Storing the upload in bibles/unofficial/uploads hid it from
     * that lookup -- install() returned FALSE and the whole import rolled back -- and
     * exposed an installed module to bibles:prune-imports a week later.
     */
    public function testABibleSuperSearchUploadIsStoredInTheModuleDirectory(): void
    {
        $Importer = new class extends BibleSuperSearch {
            // The storage location is what is under test, not the module format check.
            public function checkUploadedFile(\Illuminate\Http\UploadedFile $File): bool
            {
                return TRUE;
            }
        };

        $this->assertSame('unofficial', $Importer->getUploadStoragePath(), 'No uploads subdirectory');

        $module = 'upload_module_' . bin2hex(random_bytes(4));
        $name = $module . '.zip';
        $module_path = Bible::getUnofficialModulePath() . $name;
        $uploads_path = $Importer->getImportDir() . 'uploads/' . $name;

        try {
            $this->assertTrue(
                $Importer->acceptUploadedFile(\Illuminate\Http\UploadedFile::fake()->createWithContent($name, 'a module archive')),
                'The upload was rejected: ' . implode(', ', $Importer->getErrors())
            );

            // Exactly where Bible::openModuleFileByModule() reads it from.
            $this->assertFileExists($module_path, 'The module must land in the unofficial module directory');
            $this->assertFileDoesNotExist($uploads_path, 'And must not be staged in a prunable uploads subdirectory');

            $this->assertSame(realpath($module_path), $Importer->resolveImportFile($name));
        }
        finally {
            foreach([$module_path, $uploads_path] as $path) {
                if(file_exists($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * The directory the importer would read $name from.
     *
     * @param  MySword  $Importer
     * @param  string   $name
     * @return string
     */
    protected function importFileDir(MySword $Importer, string $name): string
    {
        $Importer->file = $name;

        return $Importer->getImportFileDir();
    }

    /**
     * Call the protected extraction-path builder.
     *
     * @param  string        $basename
     * @param  MySword|null  $Importer  Defaults to a fresh one, which reads from the
     *                                  importer directory because it has no file set
     * @return string|null
     */
    protected function containedImportPath(string $basename, ?MySword $Importer = NULL): ?string
    {
        $method = new \ReflectionMethod(MySword::class, '_containedImportPath');

        return $method->invoke($Importer ?: $this->importer(), $basename);
    }

    /**
     * The destination of a decompressed upload is written by fopen($path, 'wb') on
     * the .gz branch and ZipArchive::extractTo() on the .zip branch. Both follow an
     * existing symlink and write through to its target, so a link planted at the
     * destination name redirected the upload out of the importer directory. The
     * containment check only canonicalized the parent, which a final-component link
     * sails past.
     */
    public function testASymlinkedDestinationIsRefused(): void
    {
        $Importer = $this->importer();
        $dir = realpath($Importer->getImportDir());
        $name = 'symlink_dest_' . bin2hex(random_bytes(4)) . '.mybible';
        $link = $dir . DIRECTORY_SEPARATOR . $name;

        $outside = sys_get_temp_dir() . '/bss_import_victim_' . bin2hex(random_bytes(4));
        file_put_contents($outside, 'ORIGINAL');

        try {
            symlink($outside, $link);

            $this->assertNull(
                $this->containedImportPath($name),
                'A destination that is already a symlink must be refused'
            );

            $this->assertSame('ORIGINAL', file_get_contents($outside), 'The link target must be untouched');
        }
        finally {
            if(is_link($link) || file_exists($link)) {
                unlink($link);
            }

            @unlink($outside);
        }
    }

    /**
     * The guard must not reject an ordinary destination: a name with nothing at it
     * yet, and a name occupied by a real file from a previous import, both resolve.
     */
    public function testOrdinaryDestinationsStillResolve(): void
    {
        $Importer = $this->importer();
        // getImportDir() is not canonical (app/Importers/../../bibles/...) and the
        // method under test realpath()s it, so compare against the resolved form.
        $dir = realpath($Importer->getImportDir());
        $fresh = 'dest_fresh_' . bin2hex(random_bytes(4)) . '.mybible';
        $existing = 'dest_existing_' . bin2hex(random_bytes(4)) . '.mybible';
        $existing_path = $dir . DIRECTORY_SEPARATOR . $existing;

        file_put_contents($existing_path, 'left over from a previous import');

        try {
            $this->assertSame($dir . DIRECTORY_SEPARATOR . $fresh, $this->containedImportPath($fresh));
            $this->assertSame($existing_path, $this->containedImportPath($existing));
        }
        finally {
            @unlink($existing_path);
        }
    }

    /**
     * A name that resolves nowhere must not produce a half-created Bible row.
     */
    public function testCommitWithTamperedFileLeavesNoBibleRow(): void
    {
        $module = 'containment_test_' . bin2hex(random_bytes(3));

        $Manager = new ImportManager();
        $result = $Manager->importFile([
            '_importer' => 'mysword',
            '_file'     => '../../etc/passwd',
            '_settings' => json_encode([]),
            'module'    => $module,
            'name'      => 'Containment Test',
            'lang'      => 'English',
            'lang_short'=> 'en',
        ]);

        try {
            $this->assertFalse($result, 'Tampered _file should be refused');
            $this->assertTrue($Manager->hasErrors());
            $this->assertNull(Bible::findByModule($module), 'No Bible row should be created');
        }
        finally {
            $Bible = Bible::findByModule($module);

            if($Bible) {
                $Bible->forceDelete();
            }
        }
    }
}
