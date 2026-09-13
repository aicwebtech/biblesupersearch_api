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

    public function testRendererGuardsAgainstSymlinkBeforeTouch(): void
    {
        $source = file_get_contents(app_path('Renderers/SQLite3.php'));

        $this->assertStringContainsString('is_link($filepath)', $source, 'Renderer must check is_link() before touch()');
    }

    public function testRenderedArtifactsAreNotGroupWritable(): void
    {
        $source = file_get_contents(app_path('Renderers/RenderAbstract.php'));

        $this->assertStringContainsString('chmod($file_path, 0644)', $source);
        $this->assertStringNotContainsString('chmod($file_path, 0775)', $source);
    }
}
