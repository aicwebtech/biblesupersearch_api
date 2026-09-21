<?php

namespace Tests\Feature\Renderers;

use App\Renderers\RenderAbstract;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * _renderStart() and _renderFinish() are documented to report failure by returning falsy, and
 * render() used to answer the two of them in ways the rest of the class does not expect.
 *
 * A falsy _renderStart() returned straight out of the try, skipping _onRenderError() - so for
 * a TextAbstract renderer the destination it had already truncated stayed truncated, the handle
 * stayed open, and the previous render's rendered_at, version and meta_hash survived intact.
 * isRenderNeeded() then reported FALSE and RenderManager::download() served the truncated file
 * as the current render.
 *
 * A falsy _renderFinish() was worse: it fell through to the bookkeeping, which stamped the
 * Rendering record with a fresh rendered_at before render() returned FALSE - recording the
 * unfinished artifact as the current render rather than merely failing to replace it.
 *
 * A booted application is needed for App::getLocale(), so this is a feature test; no database
 * is touched, because every case here returns before the Rendering record is reached.
 */
class RenderStageFailureTest extends TestCase
{
    /** @var string|null */
    private $scratch_dir = null;

    private function scratchDir(): string
    {
        if($this->scratch_dir === null) {
            $this->scratch_dir = sys_get_temp_dir() . '/bss_render_stage_' . bin2hex(random_bytes(6));
            mkdir($this->scratch_dir);
        }

        return $this->scratch_dir;
    }

    public function tearDown(): void
    {
        if($this->scratch_dir !== null) {
            foreach(scandir($this->scratch_dir) as $entry) {
                if($entry === '.' || $entry === '..') {
                    continue;
                }

                unlink($this->scratch_dir . '/' . $entry);
            }

            rmdir($this->scratch_dir);
            $this->scratch_dir = null;
        }

        parent::tearDown();
    }

    /**
     * A renderer with no Bible, no file and no Rendering record: every case below stops before
     * any of the three is needed, and asking for the record at all is itself a failure.
     */
    private function makeRenderer(): RenderAbstract
    {
        return new class($this->scratchDir() . '/render.txt') extends RenderAbstract {
            protected $file_extension = 'txt';

            /** @var string */
            private $file_path;

            /** Whatever _renderStart() should report. */
            public $start_result = TRUE;

            /** Whatever _renderFinish() should report. */
            public $finish_result = TRUE;

            /** Thrown from _renderStart() when set, in place of returning. */
            public $start_throwable = NULL;

            /** @var array<int, \Throwable> */
            public $render_error_calls = [];

            public $rendering_record_requested = FALSE;

            public function __construct(string $file_path)
            {
                // The parent constructor needs Bible::findByModule(); nothing here does.
                $this->file_path = $file_path;
                $this->Bible     = (object) ['lang_short' => 'en'];
            }

            public function getRenderFilePath($create_dir = FALSE, $relative = false)
            {
                return $this->file_path;
            }

            protected function _renderStart()
            {
                if($this->start_throwable) {
                    throw $this->start_throwable;
                }

                return $this->start_result;
            }

            protected function _verseRender() { }

            protected function _renderSingleVerse($verse) { }

            protected function _renderFinish()
            {
                return $this->finish_result;
            }

            protected function _onRenderError(\Throwable $e)
            {
                $this->render_error_calls[] = $e;
            }

            public function _getRenderingRecord($ignore_cache = FALSE)
            {
                $this->rendering_record_requested = TRUE;

                throw new \LogicException('the Rendering record must not be touched after a failed stage');
            }
        };
    }

    public function testAFalsyRenderStartIsCleanedUpLikeAThrow(): void
    {
        $Renderer = $this->makeRenderer();
        $Renderer->start_result = FALSE;

        $this->assertFalse($Renderer->render(), 'a refused render answers FALSE, as it always has');

        $this->assertCount(1, $Renderer->render_error_calls, '_onRenderError() has to run: _renderStart() may already have truncated the destination');
        $this->assertFalse($Renderer->rendering_record_requested);

        $this->assertStringContainsString('_renderStart', implode(' ', $Renderer->getErrors()), 'the caller needs to be told which stage refused');
    }

    /**
     * The costly one. Falling through here recorded the unfinished artifact as the current
     * render, so isRenderNeeded() stopped asking for a new one.
     */
    public function testAFalsyRenderFinishDoesNotUpdateTheRenderingRecord(): void
    {
        $Renderer = $this->makeRenderer();
        $Renderer->finish_result = FALSE;

        $this->assertFalse($Renderer->render());

        $this->assertCount(1, $Renderer->render_error_calls);
        $this->assertFalse($Renderer->rendering_record_requested, 'the previous render must stay the current one, so the next call re-renders');

        $this->assertStringContainsString('_renderFinish', implode(' ', $Renderer->getErrors()));
    }

    /**
     * The locale is set to the Bible's language for the duration of a render. RenderManager
     * moves on to the next Bible after a failure, so a locale left behind would follow it into
     * every later render's copyright block and book names.
     */
    public function testTheLocaleIsRestoredAfterARefusedStage(): void
    {
        $locale = App::getLocale();

        $Renderer = $this->makeRenderer();
        $Renderer->start_result = FALSE;

        $this->assertFalse($Renderer->render());
        $this->assertSame($locale, App::getLocale());
    }

    /**
     * Only the falsy return is answered with FALSE. A stage that throws has always been left to
     * reach RenderManager, which decides whether to carry on to the next Bible, so the new catch
     * must not start swallowing those.
     */
    public function testAThrowingStageStillReachesTheCaller(): void
    {
        $locale = App::getLocale();

        $Renderer = $this->makeRenderer();
        $Renderer->start_throwable = new \RuntimeException('disk full');

        try {
            $Renderer->render();

            $this->fail('a throwing stage must not be turned into a FALSE return');
        }
        catch (\RuntimeException $e) {
            $this->assertSame('disk full', $e->getMessage());
        }

        $this->assertCount(1, $Renderer->render_error_calls);
        $this->assertSame($locale, App::getLocale(), 'the locale is restored on this path too');
    }
}
