<?php

namespace Tests\Feature;

use App\RenderManager;
use App\Renderers\Extras\ExtrasAbstract;
use App\Renderers\PlainText;
use Tests\TestCase;

/**
 * renderExtras() renders the shared files - book lists, language list, Strong's definitions -
 * that ride along in a multi-Bible download, and hands the ZIP builder their paths.
 *
 * Both of its FALSE returns meant the same thing to the caller until BSS-285: 'no extras for
 * this format', which is normal, and 'the extras failed to render', which is not. The ZIP
 * builder treated both as nothing-to-add and shipped an archive silently missing its extras.
 * FALSE now means only failure, and an empty list means nothing was applicable.
 *
 * The failing renderer is registered as a throwaway format and unregistered again.
 */
class RenderManagerExtrasTest extends TestCase
{
    private const BROKEN_FORMAT = 'test_broken_extras';
    private const MISSING_FORMAT = 'test_missing_extras';

    private ?string $remote_addr = NULL;

    public function setUp(): void
    {
        parent::setUp();

        RenderManager::$register[self::BROKEN_FORMAT]  = BrokenExtrasRenderer::class;
        RenderManager::$register[self::MISSING_FORMAT] = MissingFileExtrasRenderer::class;

        // download() logs and prunes renders by IP address; keep the test off that path.
        $this->remote_addr = $_SERVER['REMOTE_ADDR'] ?? NULL;
        unset($_SERVER['REMOTE_ADDR']);
    }

    public function tearDown(): void
    {
        unset(RenderManager::$register[self::BROKEN_FORMAT], RenderManager::$register[self::MISSING_FORMAT]);

        if($this->remote_addr !== NULL) {
            $_SERVER['REMOTE_ADDR'] = $this->remote_addr;
        }

        parent::tearDown();
    }

    /**
     * Plain text has no extras class. Nothing is rendered, but nothing failed either, so a
     * caller asking for the file list gets an empty list rather than a failure.
     */
    public function testAFormatWithNoExtrasYieldsAnEmptyFileList(): void
    {
        $manager = new RenderManager([], ['text']);

        $this->assertSame([], $manager->renderExtras(FALSE, FALSE, TRUE));
        $this->assertFalse($manager->hasErrors());
    }

    /**
     * The two-state contract the render:extras console command relies on is unchanged: without
     * a file list it still answers FALSE when no format had extras.
     */
    public function testAFormatWithNoExtrasStillAnswersFalseWithoutAFileList(): void
    {
        $manager = new RenderManager([], ['text']);

        $this->assertFalse($manager->renderExtras(FALSE, FALSE, FALSE));
    }

    public function testAFailingExtrasRendererReturnsFalseAndRecordsTheError(): void
    {
        $manager = new RenderManager([], [self::BROKEN_FORMAT]);

        $this->assertFalse($manager->renderExtras(FALSE, FALSE, TRUE));
        $this->assertTrue($manager->hasErrors());
        $this->assertStringContainsString('extras render failed', implode(' ', $manager->getErrors()));
    }

    /**
     * The download must fail outright rather than hand back an archive that looks complete but
     * has no extras folder in it.
     */
    public function testTheZipDownloadFailsWhenTheExtrasCannotBeRendered(): void
    {
        $base_path = \App\Renderers\RenderAbstract::getRenderBasePath();
        $before    = glob($base_path . 'truth_*.zip') ?: [];

        try {
            $manager = new RenderManager([], [self::BROKEN_FORMAT], TRUE);
            $manager->include_extras = TRUE;

            // make_file_only: a download that got as far as sending the file exits the process,
            // which would take the test runner with it.
            $this->assertFalse($manager->download(FALSE, TRUE));
            $this->assertTrue($manager->hasErrors());
            $this->assertStringContainsString('extras render failed', implode(' ', $manager->getErrors()));
        }
        finally {
            foreach(array_diff(glob($base_path . 'truth_*.zip') ?: [], $before) as $leftover) {
                unlink($leftover);
            }
        }
    }

    /**
     * A file the extras renderer listed but that cannot be added to the archive fails with the
     * path in the message. The message read $file['file'] on what getFileList() returns - a
     * list of path strings - so reporting the failure was itself a TypeError on PHP 8, masking
     * the ZIP error it was written to report.
     */
    public function testAFileThatCannotBeAddedToTheZipIsReportedWithItsPath(): void
    {
        $base_path = \App\Renderers\RenderAbstract::getRenderBasePath();
        $before    = glob($base_path . 'truth_*.zip') ?: [];

        try {
            $manager = new RenderManager([], [self::MISSING_FORMAT], TRUE);
            $manager->include_extras = TRUE;

            $this->assertFalse($manager->download(FALSE, TRUE));
            $this->assertStringContainsString(
                MissingFileExtras::MISSING_PATH,
                implode(' ', $manager->getErrors())
            );
        }
        finally {
            foreach(array_diff(glob($base_path . 'truth_*.zip') ?: [], $before) as $leftover) {
                unlink($leftover);
            }
        }
    }
}

/**
 * Extras that fail the way a missing source dump does - see
 * Tests\Feature\Renderers\Extras\CsvExtrasTest.
 */
class BrokenExtras extends ExtrasAbstract
{
    public function render($overwrite = FALSE)
    {
        throw new \Exception('extras render failed');
    }
}

class BrokenExtrasRenderer extends PlainText
{
    static public $extras_class = BrokenExtras::class;
}

/**
 * Extras that render without complaint but list a file that is not there, which is what a ZIP
 * add failure looks like from the outside.
 */
class MissingFileExtras extends ExtrasAbstract
{
    public const MISSING_PATH = '/nonexistent/bss-extras/no_such_file.csv';

    public function render($overwrite = FALSE)
    {
        $this->filelist = [self::MISSING_PATH];

        return TRUE;
    }
}

class MissingFileExtrasRenderer extends PlainText
{
    static public $extras_class = MissingFileExtras::class;
}
