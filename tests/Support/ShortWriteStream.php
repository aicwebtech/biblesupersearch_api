<?php

namespace Tests\Support;

/**
 * Stream wrapper with a byte budget: once it is spent, every further write accepts zero
 * bytes, which is how a filesystem behaves when it runs out of space.
 *
 * Returning a *partial* count per call would not reproduce the bug -- PHP's stream layer
 * retries a short write and keeps calling stream_write() until it either completes or is
 * refused outright, so fwrite() still reports the full length. Only a write that accepts
 * nothing ends the loop and makes fwrite() return a short count.
 *
 * Shared by the renderer and ffmpeg write tests. Register it with a protocol name local
 * to the test that uses it, and unregister in a finally.
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

    /**
     * Paths this wrapper was asked to delete, so a test can assert that a failed write
     * cleaned up after itself.
     *
     * @var array<int, string>
     */
    public static $unlinked = [];

    public function unlink($path): bool
    {
        static::$unlinked[] = $path;

        return TRUE;
    }

    public function url_stat($path, $flags)
    {
        return [];
    }
}
