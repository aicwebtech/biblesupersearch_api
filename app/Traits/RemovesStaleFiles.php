<?php

namespace App\Traits;

trait RemovesStaleFiles
{
    /**
     * Remove whatever already occupies a path, so the caller can write a fresh file there.
     *
     * is_link() is checked before file_exists(): a *dangling* symlink is invisible to
     * file_exists() and to is_file(), both of which report on the link's (missing) target.
     * Such a link therefore survives an is_file() or file_exists() guard, and the write that
     * follows resolves through it -- creating or truncating the link target instead of the
     * file the caller meant to produce. Every writer used by the renderers behaves this way:
     * touch(), fopen(..., 'w'), file_put_contents(), PhpSpreadsheet's save() and TCPDF's
     * Output($path, 'F') (which is fopen(..., 'wb') underneath).
     *
     * Only the link itself is removed, never what it points at.
     *
     * @param  string  $file_path
     * @return void
     */
    protected static function removeStaleFile($file_path): void
    {
        if(is_link($file_path) || file_exists($file_path)) {
            unlink($file_path);
        }
    }

    /**
     * Remove a file this code created, after a failure.
     *
     * Same mechanics as removeStaleFile(), different intent, and named apart so the two
     * cannot be confused: this is cleanup of our own half-written artifact, not a guard
     * run *before* a write to stop a planted symlink being followed. Keeping the names
     * distinct also keeps the guard coverage in
     * Tests\Feature\Renderers\RendererHygieneTest countable -- a cleanup call must not
     * read as though a write were guarded.
     *
     * @param  string  $file_path
     * @return void
     */
    protected static function removeCreatedFile($file_path): void
    {
        if(is_link($file_path) || file_exists($file_path)) {
            unlink($file_path);
        }
    }
}
