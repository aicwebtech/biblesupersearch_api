<?php

namespace Tests\Feature\Renderers;

use Tests\TestCase;
use App\Renderers\PlainText;

/**
 * Stream wrapper with a byte budget: once it is spent, every further write accepts zero
 * bytes, which is how a filesystem behaves when it runs out of space.
 *
 * Returning a *partial* count per call would not reproduce the bug -- PHP's stream layer
 * retries a short write and keeps calling stream_write() until it either completes or is
 * refused outright, so fwrite() still reports the full length. Only a write that accepts
 * nothing ends the loop and makes fwrite() return a short count.
 */
class ShortWriteStream
{
    /** @var resource */
    public $context;

    /** @var int Bytes this stream will accept in total; -1 accepts everything */
    public static $budget = 5;

    public function stream_open($path, $mode, $options, &$opened_path): bool
    {
        return TRUE;
    }

    public function stream_write($data)
    {
        if(static::$budget < 0) {
            return strlen($data);
        }

        $accepted = min(strlen($data), static::$budget);
        static::$budget -= $accepted;

        return $accepted;
    }

    public function stream_flush(): bool
    {
        return TRUE;
    }

    public function stream_close(): void
    {
    }

    public function stream_eof(): bool
    {
        return TRUE;
    }

    public function stream_stat()
    {
        return [];
    }
}

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
}
