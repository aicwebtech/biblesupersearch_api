<?php

namespace Tests\Feature\Renderers;

use Tests\TestCase;
use App\Renderers\PlainText;
use Tests\Support\ShortWriteStream;

/**
 * Every text renderer wrote through fwrite() without checking the byte count. fwrite()
 * can write fewer bytes than it was given without returning FALSE -- a full disk is the
 * usual cause -- so a truncated Bible download was produced and reported as a clean
 * render. The same applies to fputcsv(), and to the flush that fclose() performs.
 */
class ShortWriteTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        ShortWriteStream::$budget = 5;

        if(in_array('shortwrite', stream_get_wrappers(), TRUE)) {
            stream_wrapper_unregister('shortwrite');
        }

        stream_wrapper_register('shortwrite', ShortWriteStream::class);
    }

    public function tearDown(): void
    {
        if(in_array('shortwrite', stream_get_wrappers(), TRUE)) {
            stream_wrapper_unregister('shortwrite');
        }

        parent::tearDown();
    }

    /**
     * A renderer with its file handle pointed at an arbitrary stream, and no Bible or
     * database behind it.
     *
     * @param  string  $target
     * @return object
     */
    protected function renderer(string $target)
    {
        $renderer = new class extends PlainText {
            public function __construct()
            {
                // The parent constructor needs a Bible; nothing here does.
            }

            public function getRenderFilePath($create_dir = FALSE, $relative = false)
            {
                return '/tmp/short_write_stub.txt';
            }

            public function openHandle(string $target): void
            {
                $this->handle = fopen($target, 'w');
            }

            public function callWrite($text): void
            {
                $this->_write($text);
            }

            public function callCloseFile($check = TRUE): void
            {
                $this->_closeFile($check);
            }
        };

        $renderer->openHandle($target);

        return $renderer;
    }

    public function testAShortWriteIsDetected(): void
    {
        $renderer = $this->renderer('shortwrite://test');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to write render file');

        $renderer->callWrite('several bytes of verse text');
    }

    /**
     * fwrite() returning FALSE outright must still be caught.
     */
    public function testAFailedWriteIsDetected(): void
    {
        ShortWriteStream::$budget = 0;

        $renderer = $this->renderer('shortwrite://test');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to write render file');

        $renderer->callWrite('several bytes of verse text');
    }

    /**
     * The guard must not fire on an ordinary complete write.
     */
    public function testAFullWritePasses(): void
    {
        ShortWriteStream::$budget = -1;

        $renderer = $this->renderer('shortwrite://test');

        $renderer->callWrite('several bytes of verse text');

        $this->assertTrue(TRUE, 'A complete write must not raise');
    }

    /**
     * An empty string is a no-op rather than a spurious failure: strlen() is 0, and
     * fwrite() returning 0 for it must not read as a short write.
     */
    public function testAnEmptyWriteIsANoOp(): void
    {
        ShortWriteStream::$budget = 0;

        $renderer = $this->renderer('shortwrite://test');

        $renderer->callWrite('');

        $this->assertTrue(TRUE, 'An empty write must not raise');
    }

    /**
     * Closing releases the handle and is safe to repeat -- _renderFinish() and the error
     * path can both reach it.
     *
     * The close *failure* branch in _closeFile() is not exercised here: fclose() returns
     * TRUE on a userland stream wrapper even when its stream_flush() fails, so the branch
     * cannot be driven from a test. It stays as defence for real files, where a final
     * flush onto a full disk does make fclose() return FALSE.
     */
    public function testClosingIsIdempotent(): void
    {
        ShortWriteStream::$budget = -1;

        $renderer = $this->renderer('shortwrite://test');

        $renderer->callCloseFile();
        $renderer->callCloseFile();

        $this->assertTrue(TRUE, 'Closing twice must not raise');
    }

    /**
     * The error path passes FALSE so that a close failure cannot replace the exception
     * that actually explains why the render was abandoned.
     */
    public function testTheErrorPathClosesWithoutChecking(): void
    {
        ShortWriteStream::$budget = -1;

        $renderer = $this->renderer('shortwrite://test');

        $renderer->callCloseFile(FALSE);

        $this->assertTrue(TRUE, 'The error path must not raise');
    }

    /**
     * render() writes in place, and a throw skips the bookkeeping that would update the
     * Rendering record -- so the *previous* render's rendered_at, version and meta_hash all
     * survive. isRenderNeeded() then sees a file on disk plus intact metadata, reports
     * FALSE, and the truncated artifact is served as the current render.
     *
     * The error path therefore has to take the half-written file with it.
     */
    public function testTheErrorPathRemovesTheTruncatedArtifact(): void
    {
        $dir = sys_get_temp_dir() . '/bss_render_err_' . bin2hex(random_bytes(6));
        mkdir($dir);

        $artifact = $dir . '/partial.txt';
        file_put_contents($artifact, 'TRUNCATED OUTPUT');

        $renderer = new class($artifact) extends PlainText {
            /** @var string */
            private $path;

            public function __construct(string $path)
            {
                $this->path = $path;
            }

            public function getRenderFilePath($create_dir = FALSE, $relative = false)
            {
                return $this->path;
            }

            public function callOnRenderError(\Throwable $e): void
            {
                $this->_onRenderError($e);
            }
        };

        try {
            $this->assertFileExists($artifact, 'Precondition: the partial render is on disk');

            $renderer->callOnRenderError(new \Exception('verse chunk failed'));

            $this->assertFileDoesNotExist(
                $artifact,
                'A truncated render must not be left where isRenderNeeded() would serve it'
            );
        }
        finally {
            foreach(glob($dir . '/*') ?: [] as $path) {
                @unlink($path);
            }

            @rmdir($dir);
        }
    }

    /**
     * A render directory that cleans itself up whatever the test does.
     *
     * @param  callable  $test  Receives the artifact path
     * @return void
     */
    protected function withRenderDir(callable $test): void
    {
        $dir = sys_get_temp_dir() . '/bss_render_err_' . bin2hex(random_bytes(6));
        mkdir($dir);

        try {
            $test($dir . '/partial.txt');
        }
        finally {
            foreach(glob($dir . '/*') ?: [] as $path) {
                @unlink($path);
            }

            @rmdir($dir);
        }
    }

    /**
     * _openFile() truncates the destination at the top of _renderStart(), and every text
     * renderer writes its header from there -- Csv writes six rows before the first verse.
     * A disk that fills during that header throws outside verse rendering entirely, so the
     * cleanup has to hang off the whole render rather than the verse loop: otherwise
     * RenderManager moves on, the previous render's metadata survives untouched, and
     * isRenderNeeded() hands the 0-byte file out as a finished Bible.
     */
    public function testARenderStartFailureRemovesTheTruncatedArtifact(): void
    {
        $this->withRenderDir(function(string $artifact) {
            $renderer = $this->failingRenderer($artifact, 'start');

            try {
                $renderer->render();
                $this->fail('The render failure must reach the caller');
            }
            catch(\Exception $e) {
                $this->assertStringContainsString('disk full', $e->getMessage());
            }

            $this->assertFileDoesNotExist(
                $artifact,
                'A header written onto a full disk must not be left behind as a finished render'
            );
        });
    }

    /**
     * The same applies at the other end: _renderFinish() writes the trailing bytes (MySQL)
     * or the whole encoded document (Json) and then flushes.
     */
    public function testARenderFinishFailureRemovesTheTruncatedArtifact(): void
    {
        $this->withRenderDir(function(string $artifact) {
            $renderer = $this->failingRenderer($artifact, 'finish');

            try {
                $renderer->render();
                $this->fail('The render failure must reach the caller');
            }
            catch(\Exception $e) {
                $this->assertStringContainsString('disk full', $e->getMessage());
            }

            $this->assertFileDoesNotExist(
                $artifact,
                'A render abandoned at the final flush must not be left behind'
            );
        });
    }

    /**
     * The locale swap still has to be undone on the widened error path: RenderManager
     * carries on to the next Bible, which would otherwise render under this one's language.
     */
    public function testAFailedRenderStillRestoresTheLocale(): void
    {
        $this->withRenderDir(function(string $artifact) {
            $locale = \App::getLocale();

            try {
                $this->failingRenderer($artifact, 'start')->render();
            }
            catch(\Exception $e) {
                // The point of the test is what happens around it.
            }

            $this->assertSame($locale, \App::getLocale(), 'The locale must be restored');
        });
    }

    /**
     * json_encode() returns FALSE rather than throwing on malformed UTF-8, and third-party
     * module text is where that turns up. strlen(FALSE) is 0, so the write was skipped, the
     * close succeeded and a 0-byte JSON Bible was stamped as a complete render.
     */
    public function testAJsonEncodingFailureIsReportedRatherThanWrittenAsAnEmptyFile(): void
    {
        $this->withRenderDir(function(string $artifact) {
            $renderer = new class($artifact) extends \App\Renderers\Json {
                /** @var string */
                private $path;

                public function __construct(string $path)
                {
                    $this->path  = $path;
                    $this->Bible = (object) ['lang_short' => 'en'];
                }

                public function getRenderFilePath($create_dir = FALSE, $relative = false)
                {
                    return $this->path;
                }

                protected function _renderStart()
                {
                    // What a module carrying text in some other encoding produces.
                    $this->data = ['verses' => [['text' => "In the beginning \xB1\x31"]]];

                    $this->_openFile();

                    return TRUE;
                }

                protected function _verseRender()
                {
                    // No database behind this renderer.
                }
            };

            try {
                $renderer->render();
                $this->fail('A failed encode must not read as a successful render');
            }
            catch(\RuntimeException $e) {
                $this->assertStringContainsString('Failed to encode', $e->getMessage());
            }

            $this->assertFileDoesNotExist($artifact, 'No empty artifact may be left behind');
        });
    }

    /**
     * A renderer whose only job is to fail at a chosen stage, with no Bible or database
     * behind it. Only lang_short is needed: render() sets the locale from it and throws
     * before anything else touches the model.
     *
     * @param  string  $artifact
     * @param  string  $fail_at  'start' or 'finish'
     * @return \App\Renderers\PlainText
     */
    protected function failingRenderer(string $artifact, string $fail_at)
    {
        return new class($artifact, $fail_at) extends PlainText {
            /** @var string */
            private $path;

            /** @var string */
            private $fail_at;

            public function __construct(string $path, string $fail_at)
            {
                $this->path    = $path;
                $this->fail_at = $fail_at;
                $this->Bible   = (object) ['lang_short' => 'en'];
            }

            public function getRenderFilePath($create_dir = FALSE, $relative = false)
            {
                return $this->path;
            }

            protected function _renderStart()
            {
                $this->_openFile();
                $this->_write('PARTIAL HEADER');

                if($this->fail_at === 'start') {
                    throw new \Exception('disk full while writing the header');
                }

                return TRUE;
            }

            protected function _verseRender()
            {
                // No database behind this renderer.
            }

            protected function _renderFinish()
            {
                throw new \Exception('disk full during the final flush');
            }
        };
    }
}
