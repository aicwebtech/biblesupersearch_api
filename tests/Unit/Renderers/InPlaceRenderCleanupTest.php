<?php

namespace Tests\Unit\Renderers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use App\Renderers\SQLite3;
use App\Renderers\PlainText;

/**
 * Both of these renderers build their artifact in place: the render path is cleared and
 * then written to directly, so once a render is under way the previous one is gone while
 * the Rendering record still describes it. A throw skips the bookkeeping that would have
 * replaced that record, so isRenderNeeded() reports FALSE and RenderManager::download()
 * hands the truncated or half-built file out as the current render, indefinitely.
 *
 * _onRenderError() is what drops the artifact instead, and it is the contract asserted
 * here rather than either implementation. The renderers are exercised without a Bible, a
 * database or a container: the cleanup is filesystem work, and SQLite3's connection
 * tidying is skipped when _renderStart() never got as far as opening one.
 */
class InPlaceRenderCleanupTest extends TestCase
{
    /** @var string|null */
    private $scratch_dir = null;

    private function scratchDir(): string
    {
        if($this->scratch_dir === null) {
            $this->scratch_dir = sys_get_temp_dir() . '/bss_render_cleanup_' . bin2hex(random_bytes(6));
            mkdir($this->scratch_dir);
        }

        return $this->scratch_dir;
    }

    protected function tearDown(): void
    {
        if($this->scratch_dir !== null) {
            foreach(scandir($this->scratch_dir) as $entry) {
                if($entry === '.' || $entry === '..') {
                    continue;
                }

                $path = $this->scratch_dir . '/' . $entry;

                is_dir($path) && !is_link($path) ? rmdir($path) : unlink($path);
            }

            rmdir($this->scratch_dir);
            $this->scratch_dir = null;
        }

        parent::tearDown();
    }

    /**
     * One renderer of each kind, pointed at an arbitrary path and holding no connection
     * or file handle -- the state a throw from _renderStart() leaves behind.
     *
     * @param  string  $file_path
     * @return array<string, object>
     */
    private function renderers(string $file_path): array
    {
        $sqlite = new class($file_path) extends SQLite3 {
            /** @var string */
            private $file_path;

            public function __construct(string $file_path)
            {
                // The parent constructor needs a Bible; nothing here does.
                $this->file_path = $file_path;
            }

            public function getRenderFilePath($create_dir = FALSE, $relative = false)
            {
                return $this->file_path;
            }

            public function callOnRenderError(\Throwable $e): void
            {
                $this->_onRenderError($e);
            }
        };

        $text = new class($file_path) extends PlainText {
            /** @var string */
            private $file_path;

            public function __construct(string $file_path)
            {
                $this->file_path = $file_path;
            }

            public function getRenderFilePath($create_dir = FALSE, $relative = false)
            {
                return $this->file_path;
            }

            public function callOnRenderError(\Throwable $e): void
            {
                $this->_onRenderError($e);
            }
        };

        return ['SQLite3' => $sqlite, 'PlainText' => $text];
    }

    public function testThePartialArtifactIsRemoved(): void
    {
        foreach($this->renderers($this->scratchDir() . '/kjv.render') as $name => $renderer) {
            $file = $renderer->getRenderFilePath();
            file_put_contents($file, 'half a render');

            $renderer->callOnRenderError(new \Exception('disk full'));

            $this->assertFileDoesNotExist(
                $file,
                $name . ' must not leave its artifact where isRenderNeeded() will trust it'
            );
        }
    }

    /**
     * The hook also runs when the throw came before anything was written, and a render
     * path with nothing at it is not a failure.
     */
    public function testAnAbsentArtifactIsNotAFailure(): void
    {
        foreach($this->renderers($this->scratchDir() . '/never_written.render') as $name => $renderer) {
            $renderer->callOnRenderError(new \Exception('failed early'));

            $this->assertFileDoesNotExist($renderer->getRenderFilePath(), $name);
        }
    }

    /**
     * A removal that fails leaves the broken artifact as the current render, which is the
     * failure this hook exists to prevent -- so it is raised rather than swallowed, and
     * carries the original exception along, since render() rethrows whatever leaves here.
     */
    #[WithoutErrorHandler]
    public function testAnUnremovableArtifactIsRaisedWithItsCause(): void
    {
        // unlink() refuses a directory on every platform, which stands in for the removal
        // failures that would otherwise need ownership games to arrange.
        $path = $this->scratchDir() . '/kjv.render';
        mkdir($path);

        foreach($this->renderers($path) as $name => $renderer) {
            $cause = new \Exception('disk full');

            try {
                $renderer->callOnRenderError($cause);

                $this->fail($name . ': expected the failed cleanup to be raised');
            }
            catch(\RuntimeException $e) {
                $this->assertStringContainsString('could not be removed', $e->getMessage(), $name);
                $this->assertSame($cause, $e->getPrevious(), $name . ': the failure that started this must not be lost');
            }
        }
    }
}
