<?php

namespace Tests\Unit\Renderers;

use PHPUnit\Framework\TestCase;
use App\Models\Bible;
use App\Renderers\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * The Excel renderer builds a real .xlsx workbook: a title row, the copyright statement, a
 * header row, then one row per verse.
 *
 * The subclass below skips the database-dependent constructor and redirects the output path,
 * so the whole start/verse/finish cycle runs with no application booted and the produced
 * workbook is read back and asserted on.
 */
class ExcelTest extends TestCase
{
    /** Above the renderer's 512M threshold, so _renderStart chooses the six-column layout. */
    private const WIDE_LAYOUT_MEMORY = '1024M';

    /** Below the threshold, so the renderer drops to four columns to save memory. */
    private const NARROW_LAYOUT_MEMORY = '400M';

    private string $tempDir;

    private string $originalMemoryLimit;

    protected function setUp(): void
    {
        $this->originalMemoryLimit = ini_get('memory_limit');
        $this->tempDir = sys_get_temp_dir() . '/bss-excel-' . uniqid() . '/';
        mkdir($this->tempDir, 0775, true);
    }

    protected function tearDown(): void
    {
        ini_set('memory_limit', $this->originalMemoryLimit);

        foreach (glob($this->tempDir . '*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    private function makeRenderer(): Excel
    {
        $bible = new Bible();
        $bible->name = 'King James Version';

        return new class ($bible, $this->tempDir . 'kjv.xlsx') extends Excel {
            public function __construct($bible, private string $path)
            {
                // The parent constructor resolves a Bible from the database; bypass it.
                $this->Bible = $bible;
            }

            public function getRenderFilePath($create_dir = false, $relative = false)
            {
                return $this->path;
            }

            protected function _getCopyrightStatement($plain_text = false, $line_break_replacement = null)
            {
                return 'Public domain.';
            }

            public function columnCount(): int
            {
                return $this->columns;
            }

            public function callRenderStart()
            {
                return $this->_renderStart();
            }

            public function callRenderVerse($verse)
            {
                return $this->_renderSingleVerse($verse);
            }

            public function callRenderFinish()
            {
                return $this->_renderFinish();
            }
        };
    }

    private function verse(int $id, int $book, string $bookName, int $chapter, int $verse, string $text): object
    {
        return (object) [
            'id'        => $id,
            'book'      => $book,
            'book_name' => $bookName,
            'chapter'   => $chapter,
            'verse'     => $verse,
            'text'      => $text,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function renderAndRead(string $memoryLimit): array
    {
        ini_set('memory_limit', $memoryLimit);

        $renderer = $this->makeRenderer();
        $renderer->callRenderStart();
        $renderer->callRenderVerse($this->verse(1, 1, 'Genesis', 1, 1, 'In the beginning'));
        $renderer->callRenderVerse($this->verse(2, 1, 'Genesis', 1, 2, 'And the earth was without form'));
        $renderer->callRenderFinish();

        $path = $this->tempDir . 'kjv.xlsx';

        $this->assertFileExists($path);

        return IOFactory::load($path)->getActiveSheet()->toArray();
    }

    public function testRenderStartReportsSuccess(): void
    {
        $this->assertTrue($this->makeRenderer()->callRenderStart());
    }

    public function testWorkbookCarriesTheBibleNameAndCopyright(): void
    {
        $rows = $this->renderAndRead(self::WIDE_LAYOUT_MEMORY);

        $this->assertSame('King James Version', $rows[0][0]);
        $this->assertSame('Public domain.', $rows[2][0]);
    }

    /**
     * Row 5 is the header; the six-column layout carries the book name, which the four-column
     * layout drops to save memory.
     */
    public function testSixColumnLayoutHeaderIncludesBookName(): void
    {
        $rows = $this->renderAndRead(self::WIDE_LAYOUT_MEMORY);

        $this->assertSame(
            ['Verse ID', 'Book Name', 'Book Number', 'Chapter', 'Verse', 'Text'],
            array_slice($rows[4], 0, 6)
        );
    }

    public function testSixColumnLayoutWritesOneRowPerVerse(): void
    {
        $rows = $this->renderAndRead(self::WIDE_LAYOUT_MEMORY);

        $this->assertSame('Genesis', $rows[5][1]);
        $this->assertSame('In the beginning', $rows[5][5]);
        $this->assertSame('And the earth was without form', $rows[6][5]);
    }

    public function testFourColumnLayoutOmitsTheVerseIdAndBookName(): void
    {
        $rows = $this->renderAndRead(self::NARROW_LAYOUT_MEMORY);

        $this->assertEquals([1, 1, 1, 'In the beginning'], array_slice($rows[5], 0, 4));
    }

    /**
     * The layout is chosen from the memory limit, so the same renderer emits a narrower sheet
     * on a constrained host.
     *
     * There is no five-column case here: _renderStart only ever selects 4 or 6, so the case 5
     * arms of _renderSingleVerse and _renderFinish are unreachable in production.
     */
    public function testLayoutFollowsTheMemoryLimit(): void
    {
        ini_set('memory_limit', self::WIDE_LAYOUT_MEMORY);
        $wide = $this->makeRenderer();
        $wide->callRenderStart();
        $this->assertSame(6, $wide->columnCount());

        ini_set('memory_limit', self::NARROW_LAYOUT_MEMORY);
        $narrow = $this->makeRenderer();
        $narrow->callRenderStart();
        $this->assertSame(4, $narrow->columnCount());
    }

    /**
     * Verses start at row 6, immediately under the header, and the row index advances per
     * verse rather than overwriting.
     */
    public function testVersesBeginImmediatelyBelowTheHeader(): void
    {
        $rows = $this->renderAndRead(self::WIDE_LAYOUT_MEMORY);

        $this->assertNull($rows[3][0] ?? null, 'row 4 stays blank between copyright and header');
        $this->assertEquals(1, $rows[5][0]);
        $this->assertEquals(2, $rows[6][0]);
    }

    public function testRenderFinishReportsSuccessAndWritesTheFile(): void
    {
        ini_set('memory_limit', self::WIDE_LAYOUT_MEMORY);
        $renderer = $this->makeRenderer();
        $renderer->callRenderStart();
        $renderer->callRenderVerse($this->verse(1, 1, 'Genesis', 1, 1, 'In the beginning'));

        $this->assertTrue($renderer->callRenderFinish());
        $this->assertGreaterThan(0, filesize($this->tempDir . 'kjv.xlsx'));
    }

    /**
     * A stale file from a previous render must be removed rather than appended to.
     */
    public function testAnExistingFileIsReplaced(): void
    {
        $path = $this->tempDir . 'kjv.xlsx';
        file_put_contents($path, 'stale');

        $this->makeRenderer()->callRenderStart();

        $this->assertFileDoesNotExist($path);
    }
}
