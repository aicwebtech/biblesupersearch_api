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
