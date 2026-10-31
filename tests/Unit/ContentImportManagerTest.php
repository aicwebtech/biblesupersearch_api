<?php

namespace Tests\Unit;

use App\ContentImporters\ContentImportAbstract;
use App\ContentImporters\Csv\CsvStrongs;
use App\ContentImporters\Excel\ExcelStrongs;
use App\ContentImportManager;
use PHPUnit\Framework\TestCase;

class ContentImportManagerTest extends TestCase
{
    public function testGetImportClassResolvesStrongsSources(): void
    {
        $this->assertSame(CsvStrongs::class, ContentImportManager::getImportClass('csv', 'strongs'));
        $this->assertSame(ExcelStrongs::class, ContentImportManager::getImportClass('excel', 'strongs'));
    }

    public function testGetImportClassIsNullForAnUnknownSourceOrType(): void
    {
        $this->assertNull(ContentImportManager::getImportClass('bacon', 'strongs'));
        $this->assertNull(ContentImportManager::getImportClass('csv', 'bacon'));
    }

    public function testImportersListHoldsTheTypesSourcesForTheUi(): void
    {
        $list = ContentImportManager::getImportersList('strongs');

        $this->assertSame(['csv', 'excel'], array_column($list, 'type'));

        foreach($list as $item) {
            foreach(['name', 'desc', 'ext', 'kind', 'type'] as $key) {
                $this->assertArrayHasKey($key, $item);
            }

            $this->assertArrayNotHasKey('class', $item);
        }

        $this->assertSame([], ContentImportManager::getImportersList('bacon'));
    }

    public function testMakeImporterReportsAMissingOrUnknownSelection(): void
    {
        $Manager = new ContentImportManager();
        $this->assertNull($Manager->makeImporter(NULL, 'strongs'));
        $this->assertStringContainsString('Importer is required', implode(' ', $Manager->getErrors()));

        $Manager = new ContentImportManager();
        $this->assertNull($Manager->makeImporter('csv', 'bacon'));
        $this->assertStringContainsString('Volume type does not exist', implode(' ', $Manager->getErrors()));

        $Manager = new ContentImportManager();
        $this->assertInstanceOf(CsvStrongs::class, $Manager->makeImporter('csv', 'strongs'));
        $this->assertFalse($Manager->hasErrors());
    }

    /**
     * A new source - a future BibleSuperSearch or SQLite importer - is a registry entry plus an
     * import class.  Nothing in the manager or the importer lifecycle needs to change.
     */
    public function testANewSourceNeedsOnlyARegistryEntryAndAClass(): void
    {
        $Manager = new StubSourceManager();

        $this->assertSame(['stub'], array_column(StubSourceManager::getImportersList('strongs'), 'type'));

        $Importer = $Manager->makeImporter('stub', 'strongs');
        $this->assertInstanceOf(StubContentImporter::class, $Importer);

        // No settings form: the default no-op settings hook accepts anything
        $this->assertTrue($Importer->setSettings(NULL));
        $this->assertTrue($Importer->check('unused'), implode(' ', (array) $Importer->getErrors()));
        $this->assertSame(['name' => 'From the file'], $Importer->getVolumeAttributes());
    }

    public function testTheLifecycleValidatesRowsFromAnySource(): void
    {
        $Importer = (new StubSourceManager())->makeImporter('stub', 'strongs');
        $Importer->rows = [
            10 => ['number' => 'H1', 'definition' => 'One'],
            11 => ['number' => 'H1', 'definition' => 'Again'],
            12 => ['number' => 'H3'],
        ];

        $this->assertFalse($Importer->check('unused'));
        $this->assertStringContainsString('Row 11: Duplicate number \'H1\', also on row 10', implode(' ', $Importer->getErrors()));
    }

    public function testBlankRowsAreSkippedAndAnEmptyFileIsAnError(): void
    {
        $Importer = (new StubSourceManager())->makeImporter('stub', 'strongs');
        $Importer->rows = [5 => ['number' => '', 'definition' => NULL]];

        $this->assertFalse($Importer->check('unused'));
        $this->assertStringContainsString('No content found', implode(' ', $Importer->getErrors()));
    }

    /** The memory limit is raised to 256M, never lowered. */
    public function testMemoryLimitIsRaisedButNeverLowered(): void
    {
        $original = ini_get('memory_limit');
        $raise = new \ReflectionMethod(ContentImportManager::class, 'raiseMemoryLimit');

        try {
            foreach(['128M' => '256M', '1G' => '1G', '-1' => '-1', '512M' => '512M'] as $before => $after) {
                ini_set('memory_limit', $before);
                $raise->invoke(NULL);
                $this->assertSame($after, ini_get('memory_limit'), 'Starting from ' . $before);
            }
        }
        finally {
            ini_set('memory_limit', $original);
        }
    }
}

/**
 * A manager with one extra source, registered the way a real one would be
 */
class StubSourceManager extends ContentImportManager
{
    protected static $import_source_map = [
        'stub' => [
            'name' => 'Stub',
            'desc' => 'Test-only source',
            'url'  => NULL,
            'ext'  => ['stub'],
            'kind' => 'Stub',
        ],
    ];

    protected static function getTypeImportClasses(string $content_type): array
    {
        return $content_type == 'strongs' ? ['stub' => StubContentImporter::class] : [];
    }
}

/**
 * An importer reading rows already keyed by field name, as a module-format importer would
 */
class StubContentImporter extends ContentImportAbstract
{
    public array $rows = [
        1 => ['number' => 'H1', 'definition' => 'One'],
        2 => ['number' => 'G2', 'definition' => 'Two'],
    ];

    protected function openFile(string $path): bool
    {
        return TRUE;
    }

    protected function readMeta(): array
    {
        return ['name' => 'From the file'];
    }

    protected function readBatches(int $size): \Generator
    {
        yield $this->rows;
    }

    protected function closeFile(): void
    {
    }

    protected function mapRow(array $raw, int|string $row): array
    {
        return $raw;
    }
}
