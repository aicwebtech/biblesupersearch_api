<?php

namespace Tests\Feature\Renderers;

use Tests\TestCase;

/**
 * SQLite3::_renderStart() used file_exists() -> unlink() -> touch(). A
 * *dangling* symlink is invisible to file_exists(), so touch() created the file
 * at the link target instead of in the render directory. Generated artifacts
 * were also left group-writable.
 */
class RendererHygieneTest extends TestCase
{
    public function testDanglingSymlinkIsRemovedRatherThanFollowed(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'render_hygiene_' . bin2hex(random_bytes(4));
        mkdir($dir);

        $target = $dir . DIRECTORY_SEPARATOR . 'target_should_not_be_created';
        $link = $dir . DIRECTORY_SEPARATOR . 'render.sqlite';

        try {
            symlink($target, $link); // dangling: target does not exist

            $this->assertFalse(file_exists($link), 'Precondition: dangling link is invisible to file_exists()');
            $this->assertTrue(is_link($link), 'Precondition: but is_link() sees it');

            // The guard the renderer now applies.
            if(is_link($link) || file_exists($link)) {
                unlink($link);
            }

            touch($link);

            $this->assertFalse(is_link($link), 'Link must have been replaced by a real file');
            $this->assertFileDoesNotExist($target, 'touch() must not have written through the link');
        }
        finally {
            foreach([$link, $target] as $path) {
                if(is_link($path) || file_exists($path)) {
                    unlink($path);
                }
            }

            if(is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    /**
     * The guard lives in one place now (RenderAbstract::removeStaleRenderFile,
     * unit-tested in Tests\Unit\Renderers\RenderAbstractTest). It was
     * originally applied to SQLite3 alone while three sibling renderers with the
     * same remove-then-write pattern kept the is_file() guard, so this asserts
     * every one of them routes through the shared helper.
     */
    public function testEveryRendererUsesTheSharedStaleFileGuard(): void
    {
        $renderers = ['SQLite3.php', 'TextAbstract.php', 'Excel.php', 'ExcelFromCsv.php', 'PdfAbstract.php'];

        foreach($renderers as $file) {
            $source = file_get_contents(app_path('Renderers/' . $file));

            $this->assertStringContainsString(
                'removeStaleFile($filepath)',
                $source,
                $file . ' must clear the render path through the shared guard'
            );

            $this->assertStringNotContainsString(
                'if(is_file($filepath)) {',
                $source,
                $file . ' must not guard with is_file(), which cannot see a dangling symlink'
            );
        }
    }

    /**
     * The Extras renderers write into the same rendered tree but are a separate class
     * hierarchy (ExtrasAbstract does not extend RenderAbstract), which is how they came
     * to be missed when the guard lived on RenderAbstract. It is a trait now, so every
     * writer on both sides can reach it.
     *
     * Asserted by counting writes rather than naming them, so a new unguarded write
     * cannot be added without this failing.
     */
    public function testEveryExtrasWriteIsGuarded(): void
    {
        $files = ['ExtrasAbstract.php', 'Csv.php', 'Json.php', 'MySQL.php'];

        foreach($files as $file) {
            $source = file_get_contents(app_path('Renderers/Extras/' . $file));

            $writes = preg_match_all("/\bfile_put_contents\(|\bfopen\(/", $source);
            $guards = preg_match_all('/removeStaleFile\(/', $source);

            if($writes === 0) {
                continue;
            }

            $this->assertSame(
                $writes,
                $guards,
                $file . ' has ' . $writes . ' file write(s) but ' . $guards . ' stale-file guard(s)'
            );
        }
    }

    /**
     * One guard, one implementation: the bug this protects against was first fixed in
     * SQLite3 alone while its siblings kept an is_file() check, and again when the PDF
     * and Extras writers were found still bypassing it.
     */
    public function testTheGuardHasASingleImplementation(): void
    {
        $trait = file_get_contents(app_path('Traits/RemovesStaleFiles.php'));

        $this->assertStringContainsString('is_link($file_path) || file_exists($file_path)', $trait);

        foreach(['Renderers/RenderAbstract.php', 'Renderers/Extras/ExtrasAbstract.php'] as $file) {
            $this->assertStringContainsString(
                'use \\App\\Traits\\RemovesStaleFiles;',
                file_get_contents(app_path($file)),
                $file . ' must take the guard from the trait'
            );
        }
    }

    /**
     * deleteRenderFile() is the other way a stale artifact is cleared, and had
     * the same is_file() guard: a dangling link left behind there is what the
     * next render would write through.
     */
    public function testDeleteRenderFileUsesTheSharedStaleFileGuard(): void
    {
        $source = file_get_contents(app_path('Renderers/RenderAbstract.php'));

        $this->assertStringContainsString('static::removeStaleFile($file_path);', $source);
        $this->assertStringNotContainsString('if(is_file($file_path)) {', $source);
    }

    public function testRenderedArtifactsAreNotGroupWritable(): void
    {
        $source = file_get_contents(app_path('Renderers/RenderAbstract.php'));

        $this->assertStringContainsString('chmod($file_path, 0644)', $source);
        $this->assertStringNotContainsString('chmod($file_path, 0775)', $source);
    }
}
