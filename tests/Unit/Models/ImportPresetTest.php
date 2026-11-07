<?php

namespace Tests\Unit\Models;

use App\Models\ImportPreset;
use PHPUnit\Framework\TestCase;

class ImportPresetTest extends TestCase
{
    public function testTableAndFillable(): void
    {
        $Preset = new ImportPreset();

        $this->assertSame('import_presets', $Preset->getTable());
        $this->assertSame(['type', 'name', 'settings'], $Preset->getFillable());
    }

    public function testSettingsAreCastToAnArray(): void
    {
        $this->assertSame('array', (new ImportPreset())->getCasts()['settings']);
    }

    public function testValidTypesAreBibleAndTheVolumeTypes(): void
    {
        $types = ImportPreset::getValidTypes();

        $this->assertSame(ImportPreset::TYPE_BIBLE, $types[0]);
        $this->assertContains('strongs', $types);
    }

    public function testCleanSettingsDropsPerImportKeys(): void
    {
        $clean = ImportPreset::cleanSettings([
            'first_row_data' => 2,
            'col_A'          => 'number',
            'importer'       => 'csv',
            'content_type'   => 'strongs',
            'file'           => 'x.csv',
            '_token'         => 'secret',
            'skip_invalid_rows' => TRUE,
        ]);

        $this->assertSame(['first_row_data' => 2, 'col_A' => 'number'], $clean);
    }
}
