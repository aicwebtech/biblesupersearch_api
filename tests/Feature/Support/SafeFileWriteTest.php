<?php

namespace Tests\Feature\Support;

use Tests\TestCase;
use Tests\Support\ShortWriteStream;

/**
 * Concrete holder for the trait under test.
 */
class SafeWriter
{
    use \App\Traits\WritesFilesSafely;

    public static function put($path, $contents, $what = 'file'): void
    {
        static::putFileContentsOrFail($path, $contents, $what);
    }

    public static function putRow($handle, array $row, $escape, $path = ''): void
    {
        static::putCsvRowOrFail($handle, $row, $escape, $path);
    }

    public static function close($handle, $path = ''): void
    {
        static::closeFileOrFail($handle, $path);
    }
}

/**
 * file_put_contents() and fputcsv() were called for their side effect and never checked.
 * A partial write leaves a file every later step treats as complete -- a generated PHP
 * class that fatals on include, an export quietly missing rows, an extras artifact served
 * to users as whole.
 */
class SafeFileWriteTest extends TestCase
{
    /** @var string */
    private $dir;

    public function setUp(): void
    {
        parent::setUp();

        ShortWriteStream::$budget = 5;
        ShortWriteStream::$unlinked = [];

        if(!in_array('safewrite', stream_get_wrappers(), TRUE)) {
            stream_wrapper_register('safewrite', ShortWriteStream::class);
        }

        $this->dir = sys_get_temp_dir() . '/bss_safe_write_' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    public function tearDown(): void
    {
        if(in_array('safewrite', stream_get_wrappers(), TRUE)) {
            stream_wrapper_unregister('safewrite');
        }

        foreach(glob($this->dir . '/*') ?: [] as $path) {
            @unlink($path);
        }

        @rmdir($this->dir);

        parent::tearDown();
    }

    public function testACompleteWriteSucceeds(): void
    {
        $path = $this->dir . '/whole.php';

        SafeWriter::put($path, '<?php class Whole {}');

        $this->assertSame('<?php class Whole {}', file_get_contents($path));
    }

    /**
     * The case that used to pass unnoticed. file_put_contents() reports a partial write
     * rather than completing it, and the caller must not carry on as though the file were
     * whole -- these contents get include()d.
     */
    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function testAPartialWriteThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('verse model class');

        SafeWriter::put('safewrite://truncated.php', '<?php class WayTooLongToFit {}', 'verse model class');
    }

    /**
     * A truncated PHP class file left on disk fatals on every later request, so the
     * failed write has to take its own leavings with it.
     */
    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function testAPartialWriteRemovesWhatItWrote(): void
    {
        try {
            SafeWriter::put('safewrite://truncated.php', '<?php class WayTooLongToFit {}');
            $this->fail('A partial write should throw');
        }
        catch(\RuntimeException $e) {
            $this->assertContains(
                'safewrite://truncated.php',
                ShortWriteStream::$unlinked,
                'The partial file must be removed'
            );
        }
    }

    /**
     * An unwritable destination is a failure, not a silent no-op.
     */
    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function testAnUnwritablePathThrows(): void
    {
        $this->expectException(\RuntimeException::class);

        SafeWriter::put($this->dir . '/no_such_dir/x.php', 'content');
    }

    public function testACsvRowIsWritten(): void
    {
        $path = $this->dir . '/rows.csv';
        $handle = fopen($path, 'w');

        SafeWriter::putRow($handle, ['a', 'b'], '\\', $path);
        SafeWriter::close($handle, $path);

        $this->assertSame("a,b\n", file_get_contents($path));
    }

    /**
     * The case a `=== FALSE` check misses entirely. fputcsv() does not report a refused
     * write as FALSE -- it returns 0 when the stream accepts nothing, and the partial
     * count when the disk fills mid-row -- so the row has to be formatted first and the
     * real write verified against its length.
     */
    public function testACsvRowThatCannotBeWrittenThrows(): void
    {
        ShortWriteStream::$budget = 0;

        $handle = fopen('safewrite://rows.csv', 'w');

        try {
            $this->expectException(\RuntimeException::class);

            SafeWriter::putRow($handle, ['aaa', 'bbb'], '\\', 'rows.csv');
        }
        finally {
            fclose($handle);
        }
    }

    /**
     * A row cut off part way through is equally unacceptable.
     */
    public function testAPartiallyWrittenCsvRowThrows(): void
    {
        ShortWriteStream::$budget = 3;

        $handle = fopen('safewrite://rows.csv', 'w');

        try {
            $this->expectException(\RuntimeException::class);

            SafeWriter::putRow($handle, ['aaa', 'bbb'], '\\', 'rows.csv');
        }
        finally {
            fclose($handle);
        }
    }

    /**
     * The formatter must produce exactly what fputcsv() would, quoting included.
     */
    public function testCsvFormattingMatchesFputcsv(): void
    {
        $path = $this->dir . '/native.csv';
        $handle = fopen($path, 'w');
        fputcsv($handle, ['plain', 'has,comma', 'has"quote'], escape: '\\');
        fclose($handle);

        $method = new \ReflectionMethod(SafeWriter::class, 'csvRowToString');

        $this->assertSame(
            file_get_contents($path),
            $method->invoke(null, ['plain', 'has,comma', 'has"quote'], '\\')
        );
    }
}
