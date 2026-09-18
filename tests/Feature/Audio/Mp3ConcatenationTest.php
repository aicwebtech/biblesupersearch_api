<?php

namespace Tests\Feature\Audio;

use Tests\TestCase;
use App\AudioManager;
use Tests\Support\ShortWriteStream;

/**
 * In compatibility mode (no ffmpeg) every verse's mp3 bytes are appended to one temp
 * stream that is then sent as the response body. The write was unchecked, so a stream
 * that stopped accepting bytes -- a full temp filesystem -- produced truncated or garbled
 * audio that was served as though nothing had gone wrong.
 */
class Mp3ConcatenationTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        ShortWriteStream::$budget = 4;

        if(!in_array('mp3short', stream_get_wrappers(), TRUE)) {
            stream_wrapper_register('mp3short', ShortWriteStream::class);
        }
    }

    public function tearDown(): void
    {
        if(in_array('mp3short', stream_get_wrappers(), TRUE)) {
            stream_wrapper_unregister('mp3short');
        }

        parent::tearDown();
    }

    public function testACompleteChunkIsAccepted(): void
    {
        $handle = tmpfile();

        try {
            $this->assertTrue(AudioManager::appendMp3Chunk($handle, 'ID3 mp3 bytes here'));

            fseek($handle, 0);
            $this->assertSame('ID3 mp3 bytes here', stream_get_contents($handle));
        }
        finally {
            fclose($handle);
        }
    }

    /**
     * The case that used to pass silently: the stream takes some bytes, then refuses the
     * rest. fwrite() returns a short count rather than FALSE.
     */
    public function testAShortChunkWriteIsRejected(): void
    {
        $handle = fopen('mp3short://audio', 'w');

        try {
            $this->assertFalse(
                AudioManager::appendMp3Chunk($handle, 'ID3 mp3 bytes here'),
                'A short write must not be reported as success'
            );
        }
        finally {
            fclose($handle);
        }
    }

    /**
     * A stream refusing everything outright must be caught too -- fwrite() returns 0,
     * not FALSE, so a === FALSE check would have missed this as well.
     */
    public function testAFullyRefusedWriteIsRejected(): void
    {
        ShortWriteStream::$budget = 0;

        $handle = fopen('mp3short://audio', 'w');

        try {
            $this->assertFalse(AudioManager::appendMp3Chunk($handle, 'ID3 mp3 bytes here'));
        }
        finally {
            fclose($handle);
        }
    }

    /**
     * A verse with no bytes is not a failure.
     */
    public function testAnEmptyChunkIsANoOp(): void
    {
        ShortWriteStream::$budget = 0;

        $handle = fopen('mp3short://audio', 'w');

        try {
            $this->assertTrue(AudioManager::appendMp3Chunk($handle, ''));
        }
        finally {
            fclose($handle);
        }
    }
}
