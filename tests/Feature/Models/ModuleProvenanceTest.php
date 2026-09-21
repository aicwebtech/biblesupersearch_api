<?php

namespace Tests\Feature\Models;

use Tests\TestCase;
use App\Models\Bible;

/**
 * `official` decides which directory a module file lives in, and anyone able to upload an
 * archive can put "official": true inside its info.json. createFromModuleFile() and
 * updateFromModuleFile() already refused to take it from there; revertMetaInfo() did not,
 * so an uploaded unofficial archive could be promoted through the admin revert endpoint
 * and then moved into bibles/modules by migrateModuleFile().
 */
class ModuleProvenanceTest extends TestCase
{
    /**
     * Build an unofficial module archive whose info.json lies about its provenance.
     *
     * bibles/unofficial is not tracked in git; the fixture is removed in the finally
     * blocks below either way.
     *
     * @param  string  $module
     * @param  array   $info
     * @return string  Path to the archive
     */
    protected function createModuleFixture(string $module, array $info): string
    {
        $path = Bible::getUnofficialModulePath() . $module . '.zip';

        $Zip = new \ZipArchive();
        $Zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $Zip->addFromString('info.json', json_encode($info));
        $Zip->close();

        return $path;
    }

    /**
     * @param  string  $module
     * @return \App\Models\Bible
     */
    protected function createBibleFixture(string $module): Bible
    {
        $Bible = new Bible();
        $Bible->module     = $module;
        $Bible->name       = 'Provenance Fixture';
        $Bible->shortname  = 'Prov ' . $module;
        $Bible->year       = '2000';
        $Bible->lang_short = 'en';
        $Bible->official   = 0;
        $Bible->save();

        return $Bible;
    }

    public function testRevertMetaInfoCannotPromoteAnUnofficialModule(): void
    {
        $module = 'prov_' . bin2hex(random_bytes(4));
        $Bible = null;
        $archive = null;

        try {
            $Bible = $this->createBibleFixture($module);
            $archive = $this->createModuleFixture($module, [
                'name'      => 'Renamed By Archive',
                'official'  => 1,
                // Deliberately not a real module name: without the guard this value is
                // written to the record, and pointing it at an installed module would make
                // the failure a unique-index violation instead of a clear assertion.
                'module'    => $module . '_hijacked',
                'year'      => '1987',
            ]);

            $this->assertTrue($Bible->revertMetaInfo(), 'Revert should succeed');

            $Bible->refresh();

            $this->assertSame(0, (int) $Bible->official, 'info.json must not be able to grant official status');
            $this->assertSame($module, $Bible->module, 'info.json must not be able to rename the module');

            // The rest of the metadata is still reverted -- the rule is narrow.
            $this->assertSame('Renamed By Archive', $Bible->name);
            $this->assertSame('1987', (string) $Bible->year);

            $this->assertStringContainsString(
                'unofficial',
                $Bible->getModuleFilePath(),
                'The module file must still resolve to the unofficial directory'
            );
        }
        finally {
            if($archive && is_file($archive)) {
                unlink($archive);
            }

            if($Bible) {
                $Bible->forceDelete();
            }
        }
    }

    /**
     * The same rule, at the helper every path now shares.
     */
    public function testProvenanceAttributesAreStripped(): void
    {
        $method = new \ReflectionMethod(Bible::class, 'stripFileProvenanceAttributes');

        $result = $method->invoke(null, [
            'name'     => 'Kept',
            'official' => 1,
            'module'   => 'kjv',
        ]);

        $this->assertSame(['name' => 'Kept'], $result);
        $this->assertSame([], $method->invoke(null, null), 'A malformed info.json yields no attributes');
    }
}
