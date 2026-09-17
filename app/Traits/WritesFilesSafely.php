<?php

namespace App\Traits;

/**
 * Write helpers that refuse to report a partial write as success.
 *
 * file_put_contents(), fwrite() and fputcsv() all return the number of bytes written, and
 * that can be fewer than they were given without being FALSE -- a full disk is the usual
 * cause. An unchecked call therefore produces a truncated file that every later step
 * treats as complete: a generated PHP class that fatals on include, an import CSV quietly
 * missing rows, a rendered artifact served to users as though it were whole.
 *
 * A failed write also removes whatever it managed to produce. A truncated file left on
 * disk is worse than no file at all -- a half-written class file under app/Models/Verses
 * would fatal on every subsequent request until somebody deleted it by hand.
 */
trait WritesFilesSafely
{
    /**
     * file_put_contents() that throws unless the whole string reached the disk.
     *
     * @param  string  $path
     * @param  string  $contents
     * @param  string  $what      Named in the exception message
     * @return void
     * @throws \RuntimeException
     */
    protected static function putFileContentsOrFail($path, $contents, $what = 'file')
    {
        $length  = strlen($contents);
        $written = file_put_contents($path, $contents);

        if($written === $length) {
            return;
        }

        @unlink($path);

        throw new \RuntimeException(sprintf(
            'Failed to write %s "%s": wrote %s of %d bytes',
            $what,
            $path,
            var_export($written, TRUE),
            $length
        ));
    }

    /**
     * Format one CSV row exactly as fputcsv() would, without writing it anywhere.
     *
     * Writing a row straight to the destination gives nothing to check against: fputcsv()
     * formats the row itself, so the caller never learns how long it should have been.
     * Worse, fputcsv() does not report a refused write as FALSE -- it returns the byte
     * count it managed, which is 0 when the stream accepts nothing and a partial count
     * when the disk fills mid-row. A `=== FALSE` test therefore misses exactly the case
     * that matters. Formatting through php://temp first yields the expected length, so
     * the real write can be verified like any other.
     *
     * @param  array   $row
     * @param  string  $escape
     * @return string
     */
    protected static function csvRowToString(array $row, $escape)
    {
        $buffer = fopen('php://temp', 'r+');

        fputcsv($buffer, $row, escape: $escape);
        rewind($buffer);

        $line = stream_get_contents($buffer);
        fclose($buffer);

        return $line;
    }

    /**
     * Write one CSV row, throwing rather than silently dropping or truncating it.
     *
     * @param  resource  $handle
     * @param  array     $row
     * @param  string    $escape
     * @param  string    $path    Named in the exception message
     * @return void
     * @throws \RuntimeException
     */
    protected static function putCsvRowOrFail($handle, array $row, $escape, $path = '')
    {
        $line = static::csvRowToString($row, $escape);
        $length = strlen($line);

        if($length === 0) {
            return;
        }

        if(fwrite($handle, $line) !== $length) {
            throw new \RuntimeException('Failed to write CSV row' . ($path === '' ? '' : ' to "' . $path . '"'));
        }
    }

    /**
     * fclose() that throws unless the final flush succeeded.
     *
     * Buffered bytes are written out by fclose(), so a disk that filled part way through
     * can surface here rather than at any individual write.
     *
     * @param  resource  $handle
     * @param  string    $path  Named in the exception message
     * @return void
     * @throws \RuntimeException
     */
    protected static function closeFileOrFail($handle, $path = '')
    {
        if(!fclose($handle)) {
            throw new \RuntimeException('Failed to finish writing' . ($path === '' ? ' file' : ' "' . $path . '"'));
        }
    }
}
