<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\RenderManager;
use App\Renderers\RenderAbstract;

/**
 * RenderManager's catalogue helpers describe the available download formats to the admin UI
 * and resolve where a rendered file lives.
 *
 * They read the static registry and a fixed base path, so they resolve with no application
 * booted. The instance side of RenderManager needs Bibles from the database and is covered in
 * Tests\Feature\RenderManagerTest.
 */
class RenderManagerListTest extends TestCase
{
    public function testRendererListCoversEveryRegisteredFormat(): void
    {
        $list = RenderManager::getRendererList();

        $this->assertSame(array_keys(RenderManager::$register), array_keys($list));
    }

    public function testEachListedRendererCarriesItsFormatNameAndClass(): void
    {
        $entry = RenderManager::getRendererList()['json'];

        $this->assertSame('json', $entry['format']);
        $this->assertSame('JSON', $entry['name']);
        $this->assertSame(\App\Renderers\Json::class, $entry['CLASS']);
        $this->assertNotEmpty($entry['desc']);
    }

    /**
     * The name and description come off the renderer class itself, so the catalogue cannot
     * drift from what the renderer reports.
     */
    public function testListedNamesComeFromTheRendererClasses(): void
    {
        foreach (RenderManager::getRendererList() as $format => $entry) {
            $class = $entry['CLASS'];

            $this->assertSame($class::getName(), $entry['name'], "name for {$format}");
            $this->assertSame($class::getDescription(), $entry['desc'], "description for {$format}");
        }
    }

    // -----------------------------------------------------------------------
    // Grouped catalogue
    // -----------------------------------------------------------------------

    public function testGroupedListKeepsTheDefinedKinds(): void
    {
        $grouped = RenderManager::getGroupedRendererList();

        $this->assertSame(array_keys(RenderManager::$format_kinds), array_keys($grouped));
    }

    public function testGroupedListNestsRenderersUnderTheirKind(): void
    {
        $grouped = RenderManager::getGroupedRendererList();

        $this->assertArrayHasKey('json', $grouped['database']['renderers']);
        $this->assertArrayHasKey('csv', $grouped['spreadsheet']['renderers']);
        $this->assertSame('JSON', $grouped['database']['renderers']['json']['name']);
    }

    /**
     * Every format named by a kind must resolve to a renderer, or the UI would offer a
     * download that cannot be produced.
     */
    public function testEveryGroupedFormatResolvesToARenderer(): void
    {
        foreach (RenderManager::getGroupedRendererList() as $kind => $info) {
            foreach ($info['formats'] as $format) {
                $this->assertArrayHasKey($format, $info['renderers'], "{$format} under {$kind}");
                $this->assertNotEmpty($info['renderers'][$format]['CLASS']);
            }
        }
    }

    public function testGroupedListPreservesTheKindMetadata(): void
    {
        $grouped = RenderManager::getGroupedRendererList();

        $this->assertSame('PDF', $grouped['pdf']['name']);
        $this->assertNotEmpty($grouped['pdf']['formats']);
    }

    // -----------------------------------------------------------------------
    // Rendered file location
    // -----------------------------------------------------------------------

    /**
     * Rendered files are filed under the renderer's short class name, not the format key -
     * several format keys share one renderer.
     */
    public function testRenderPathIsFiledUnderTheRendererShortName(): void
    {
        $path = RenderManager::getRenderFilepath('json', 'kjv');

        $this->assertSame(RenderAbstract::getRenderBasePath() . 'Json/kjv', $path);
    }

    public function testFormatsSharingARendererShareADirectory(): void
    {
        $letter = RenderManager::getRenderFilepath('pdf_cpt_let', 'kjv');
        $alias  = RenderManager::getRenderFilepath('pdf', 'kjv');

        $this->assertSame($letter, $alias, 'pdf and pdf_cpt_let both resolve to PdfCompact');
    }

    public function testDifferentRenderersGetDifferentDirectories(): void
    {
        $this->assertNotSame(
            RenderManager::getRenderFilepath('json', 'kjv'),
            RenderManager::getRenderFilepath('csv', 'kjv')
        );
    }

    public function testTheModuleIsTheFinalPathSegment(): void
    {
        $this->assertStringEndsWith('/kjv', RenderManager::getRenderFilepath('csv', 'kjv'));
    }
}
