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
     * Throws when the path could not be cleared, because the caller writes to it
     * unconditionally afterwards: a planted link that survives a failed unlink() -- the
     * rendered directory left world-writable with the sticky bit is enough, since the
     * link's owner is then the only one who may remove it -- would be followed by the
     * very write this guard exists to protect. Failing the render is the safe outcome.
     *
     * @param  string  $file_path
     * @return void
     * @throws \RuntimeException
     */
    protected static function removeStaleFile($file_path): void
    {
        if(!static::removePath($file_path)) {
            // The basename alone: it is already public in the download URL, while the
            // directory it sits in is the server layout and these messages can reach a
            // caller.
            throw new \RuntimeException('Failed to clear the render path for ' . basename($file_path));
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
     * Reports rather than throws: every caller is already handling a failure of its own,
     * and an exception raised here would replace the one that explains it. The callers
     * that cannot afford to leave the artifact behind -- _onRenderError(), where the
     * Rendering record still describes the *previous* render and isRenderNeeded() would
     * therefore hand the partial file out as the current one -- check the result and
     * escalate. The rest throw immediately afterwards regardless.
     *
     * @param  string  $file_path
     * @return bool  FALSE if something is still at the path
     */
    protected static function removeCreatedFile($file_path): bool
    {
        return static::removePath($file_path);
    }

    /**
     * Remove whatever is at a path, and say whether the path is now clear.
     *
     * unlink() is silenced so that its return value is the only signal: with Laravel's
     * error handler installed the warning would otherwise become an ErrorException,
     * which would bypass the callers' own handling of a failure, and without one it is
     * noise on a path that is already reported.
     *
     * @param  string  $file_path
     * @return bool
     */
    protected static function removePath($file_path): bool
    {
        if(is_link($file_path) || file_exists($file_path)) {
            if(!@unlink($file_path)) {
                clearstatcache(TRUE, $file_path);

                // Anything else removing it first is a success, not a failure.
                return !is_link($file_path) && !file_exists($file_path);
            }
        }

        return TRUE;
    }

    /**
     * Is there a genuine file at this path, rather than a symlink pointing at one?
     *
     * is_file() and file_exists() both follow the link and answer about its target, so a
     * planted link reads as a perfectly good artifact. Nothing here ever writes through a
     * link, so a link is never something this code produced -- treating it as absent makes
     * the caller rebuild (and removeStaleFile() then clears the link) instead of trusting
     * whatever it points at.
     *
     * @param  string  $file_path
     * @return bool
     */
    protected static function isRealFile($file_path): bool
    {
        return !is_link($file_path) && is_file($file_path);
    }
}
