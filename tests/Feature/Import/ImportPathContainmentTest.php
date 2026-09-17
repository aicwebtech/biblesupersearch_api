<?php

namespace Tests\Feature\Import;

use Tests\TestCase;
use App\ImportManager;
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
     * Call the protected extraction-path builder.
     *
     * @param  string  $basename
     * @return string|null
     */
    protected function containedImportPath(string $basename): ?string
    {
        $method = new \ReflectionMethod(MySword::class, '_containedImportPath');

        return $method->invoke($this->importer(), $basename);
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
