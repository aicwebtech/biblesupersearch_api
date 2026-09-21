<?php

namespace Tests\Feature\Renderers;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

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
            $code = $this->sourceWithoutComments(app_path('Renderers/Extras/' . $file));

            // Every call that can *create* the destination file needs the guard, because
            // each one follows a symlink there -- copy() silently, returning TRUE while it
            // overwrites the link target. Per-row writers (putCsvRowOrFail, fputcsv,
            // fwrite) are deliberately not counted: they write into a handle that one of
            // these calls already opened and guarded.
            //
            // putFileContentsOrFail is the checked form of file_put_contents; both are
            // counted so that swapping one for the other cannot quietly drop a guard.
            $writes = preg_match_all(
                '/\\b(?:file_put_contents|putFileContentsOrFail|fopen|copy|rename)\\s*\\(/',
                $code
            );
            $guards = preg_match_all('/removeStaleFile\\s*\\(/', $code);

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
     * Source with comments and docblocks removed.
     *
     * The counting above runs over code only: prose naming a function (this very test
     * describes copy() and rename()) would otherwise be counted as a call site and make
     * the totals meaningless.
     *
     * @param  string  $path
     * @return string
     */
    protected function sourceWithoutComments(string $path): string
    {
        $code = '';

        foreach(token_get_all(file_get_contents($path)) as $token) {
            if(!is_array($token)) {
                $code .= $token;

                continue;
            }

            if($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                continue;
            }

            $code .= $token[1];
        }

        return $code;
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

    /**
     * The decision points that let a planted link be *served* rather than merely written
     * through. isRenderNeeded() gates whether a render happens at all, render() gates
     * whether an existing artifact blocks one, and RenderManager is what finally calls
     * readfile() -- all three followed links through is_file()/file_exists().
     */
    public function testTheRenderDecisionPointsAreLinkAware(): void
    {
        $render = $this->sourceWithoutComments(app_path('Renderers/RenderAbstract.php'));

        $this->assertStringContainsString(
            'if(!static::isRealFile($file_path)) {',
            $render,
            'isRenderNeeded() must not accept a symlink as a finished render'
        );

        $this->assertStringContainsString(
            'if(!$overwrite && static::isRealFile($file_path)) {',
            $render,
            'A symlink must not block a render from replacing it'
        );

        $this->assertStringNotContainsString(
            'if(!is_file($file_path)) {',
            $render,
            'The link-following check must be gone'
        );

        $manager = $this->sourceWithoutComments(app_path('RenderManager.php'));

        $this->assertStringContainsString(
            'static::isRealFile($download_file_path)',
            $manager,
            'readfile() is the point of no return and must not follow a link'
        );
    }

    /**
     * A close failure in _renderFinish() is raised outside render()'s inner try/catch, so
     * it never reaches _onVerseRenderError(); the artifact has to be dropped where the
     * exception is thrown instead.
     */
    public function testAFailedCloseDropsTheArtifact(): void
    {
        $source = $this->sourceWithoutComments(app_path('Renderers/TextAbstract.php'));

        $this->assertMatchesRegularExpression(
            '/if\(!fclose\(\$handle\) && \$check\) \{\s*static::removeCreatedFile/',
            $source,
            'A failed final flush must remove the partial render before throwing'
        );
    }

    /**
     * The multi-Bible download is the other way a render file reaches a user, and it had
     * none of the protection the single-file branch got: file_exists() and is_file() both
     * answer about a symlink's target, and ZipArchive::addFile() reads through the link,
     * so a link planted at a render path was packaged into the archive under that Bible's
     * name with whatever it pointed at inside it. The archive is also a file this code
     * creates in the shared rendered/ tree, so it needs the same stale-path guard as every
     * other writer.
     */
    public function testEveryZipAdditionIsLinkAware(): void
    {
        $source = $this->sourceWithoutComments(app_path('RenderManager.php'));

        $this->assertStringContainsString(
            'if(!$filepath || !static::isRealFile($filepath)) {',
            $source,
            'A symlink must not be packaged into the ZIP as a Bible'
        );

        $this->assertStringContainsString(
            'if(!static::isRealFile($file) || !$Zip->addFile($file',
            $source,
            'A symlink must not be packaged into the ZIP as an extra'
        );

        $this->assertStringContainsString(
            'static::removeStaleFile($zip_path);',
            $source,
            'ZipArchive::CREATE follows a link at the destination like any other writer'
        );

        foreach(['file_exists($filepath)', 'is_file($file)'] as $followed) {
            $this->assertStringNotContainsString(
                $followed,
                $source,
                'The link-following check must be gone: ' . $followed
            );
        }

        // Counted so that a new addition cannot be written without this test being
        // revisited: each one needs a guard of its own, and nothing above can see it.
        $this->assertSame(
            2,
            preg_match_all('/->addFile\s*\(/', $source),
            'A new ZIP addition needs its own isRealFile() guard -- add it, then update this count'
        );
    }

    /**
     * Space accounting reads the same paths: a link counted as an existing render made
     * the batch look cheaper than it is, since isRenderNeeded() rebuilds over it anyway.
     */
    public function testSpaceAccountingDoesNotCountASymlinkAsARender(): void
    {
        $source = $this->sourceWithoutComments(app_path('RenderManager.php'));

        $this->assertStringContainsString(
            'if(static::isRealFile($Renderer->getRenderFilePath())) {',
            $source
        );

        $this->assertStringNotContainsString(
            'if(file_exists($Renderer->getRenderFilePath())) {',
            $source
        );
    }

    /**
     * The renderers that write their artifact in place -- clear the path, then write
     * straight to it -- cannot leave a partial one behind: the Rendering record still
     * describes the render that was just unlinked, so isRenderNeeded() would report
     * FALSE and the partial file would be served as the current render. SQLite3 had the
     * pattern without the cleanup while TextAbstract had both.
     */
    public function testEveryInPlaceRendererDropsItsArtifactOnFailure(): void
    {
        foreach(['TextAbstract.php', 'SQLite3.php'] as $file) {
            $source = $this->sourceWithoutComments(app_path('Renderers/' . $file));

            $this->assertStringContainsString(
                'protected function _onRenderError(\Throwable $e)',
                $source,
                $file . ' writes in place, so it must implement the render-wide error hook'
            );

            $this->assertMatchesRegularExpression(
                '/if\(!static::removeCreatedFile\(\$this->getRenderFilePath\(\)\)\) \{\s*throw new/',
                $source,
                $file . ' must drop its partial artifact, and say so if it cannot'
            );
        }
    }

    /**
     * Both failure paths in the extras CSV writer -- a row write and the final flush --
     * must leave nothing behind.
     */
    public function testExtrasCsvCleansUpOnBothFailurePaths(): void
    {
        $source = $this->sourceWithoutComments(app_path('Renderers/Extras/Csv.php'));

        $this->assertSame(
            2,
            preg_match_all('/removeCreatedFile\s*\(/', $source),
            'Both the row-write and fclose failure paths must remove the partial dump'
        );
    }

    /**
     * Exceptions from the extras subsystem and from the shared write helpers can reach a
     * caller, so none of them may name a file on the server. The paths still go to the
     * log, where an operator can read them and a client cannot.
     *
     * Asserted across the whole directory rather than one file, because this started as a
     * fix to Csv.php alone while its siblings went on naming their paths.
     */
    #[DataProvider('pathBearingSourceProvider')]
    public function testExceptionsDoNotNameServerPaths(string $file, array $path_variables): void
    {
        $source = $this->sourceWithoutComments(app_path($file));

        preg_match_all('/throw new [^;]+;/', $source, $matches);

        $this->assertNotEmpty($matches[0], $file . ': expected to find throw statements');

        foreach($matches[0] as $statement) {
            foreach($path_variables as $variable) {
                $this->assertStringNotContainsString(
                    $variable,
                    $statement,
                    $file . ': ' . $variable . ' must not be interpolated into a thrown message: '
                        . trim($statement)
                );
            }
        }
    }

    public static function pathBearingSourceProvider(): array
    {
        return [
            'extras csv'      => ['Renderers/Extras/Csv.php', ['$filepath']],
            'extras abstract' => ['Renderers/Extras/ExtrasAbstract.php', ['$src_filepath', '$dest_filepath', '$filepath']],
            'write helpers'   => ['Traits/WritesFilesSafely.php', ['$path']],
        ];
    }

    /**
     * The detail is not simply discarded: it has to land somewhere an operator can find it.
     */
    #[DataProvider('pathBearingSourceProvider')]
    public function testThePathIsStillLogged(string $file, array $path_variables): void
    {
        $this->assertStringContainsString(
            'Log::error',
            $this->sourceWithoutComments(app_path($file)),
            $file . ': the path must still be recorded for operators'
        );
    }
}
