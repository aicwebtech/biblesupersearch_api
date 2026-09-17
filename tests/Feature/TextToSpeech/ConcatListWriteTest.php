<?php

namespace Tests\Feature\TextToSpeech;

use Tests\TestCase;
use App\TextToSpeech\Ffmpeg;
use Tests\Support\ShortWriteStream;

/**
 * The concat demuxer's input list names every file ffmpeg will join. A truncated list is
 * the dangerous failure: ffmpeg concatenates whatever entries it can read and exits 0, so
 * a dropped line becomes audio that is silently missing verses rather than an error.
 *
 * fwrite() can return a short count without returning FALSE, so the write was previously
 * unchecked in both senses -- as were fopen() and fclose().
 */
class ConcatListWriteTest extends TestCase
{
    /** @var string */
    private $dir;

    public function setUp(): void
    {
        parent::setUp();

        Ffmpeg::$useErrors = [];

        $this->dir = sys_get_temp_dir() . '/bss_concat_' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    public function tearDown(): void
    {
        foreach(glob($this->dir . '/*') ?: [] as $path) {
            @unlink($path);
        }

        @rmdir($this->dir);

        parent::tearDown();
    }

    /**
     * @param  string  $path
     * @param  array   $files
     * @return bool
     */
    protected function writeList(string $path, array $files): bool
    {
        $method = new \ReflectionMethod(Ffmpeg::class, 'writeConcatList');

        return (bool) $method->invoke(null, $path, $files);
    }

    public function testAWritableListIsProduced(): void
    {
        $path = $this->dir . '/list.txt';

        $this->assertTrue($this->writeList($path, ['/a/one.mp3', '/a/two.mp3']));

        $contents = file_get_contents($path);

        $this->assertSame("file '/a/one.mp3'\nfile '/a/two.mp3'\n", $contents);
        $this->assertSame([], Ffmpeg::$useErrors);
    }

    /**
     * Single quotes in a path must stay escaped for the demuxer.
     */
    public function testQuotesInPathsAreEscaped(): void
    {
        $path = $this->dir . '/list.txt';

        $this->writeList($path, ["/a/it's.mp3"]);

        $this->assertStringContainsString("'\\''", file_get_contents($path));
    }

    /**
     * An unwritable destination is reported rather than producing an empty list that
     * ffmpeg would treat as "nothing to concatenate".
     *
     * PHPUnit's error handler surfaces the fopen() warning even though the call site
     * suppresses it, so this test opts out of that handler; the warning is expected here
     * and the FALSE return is what the code acts on.
     */
    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function testAnUnopenableListIsReported(): void
    {
        $this->assertFalse($this->writeList($this->dir . '/no_such_dir/list.txt', ['/a/one.mp3']));

        $this->assertNotEmpty(Ffmpeg::$useErrors, 'The failure must be recorded');
    }

    /**
     * A stream that stops accepting bytes part way -- what a full disk does -- must be
     * caught rather than leaving a short list behind.
     */
    public function testAShortWriteIsReported(): void
    {
        ShortWriteStream::$budget = 5;

        if(!in_array('concatshort', stream_get_wrappers(), TRUE)) {
            stream_wrapper_register('concatshort', ShortWriteStream::class);
        }

        try {
            $this->assertFalse(
                $this->writeList('concatshort://list', ['/a/one.mp3', '/a/two.mp3']),
                'A short write must not be reported as success'
            );

            $this->assertNotEmpty(Ffmpeg::$useErrors, 'The failure must be recorded');
        }
        finally {
            stream_wrapper_unregister('concatshort');
        }
    }
}
