<?php

namespace Tests\Unit\Models;

use App\Models\Bible;
use App\Models\Post;
use App\Models\StrongsDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * BSS-290: the HTML-bearing columns are sanitized at the model boundary, by Eloquent
 * Attribute accessors, so nothing that reads them can forget to.
 *
 * Eloquent resolves an Attribute by the camelCase form of the column name
 * (HasAttributes::hasAttributeMutator() calls Str::camel($key)), so a method named
 * copyright_statement() is never invoked and the column ships raw - which is why the accessor
 * bindings themselves are asserted here and not only their output.
 *
 * Plain PHP objects throughout: attributes are set with setRawAttributes(), no database and no
 * booted application.
 */
class ModelHtmlSanitizationTest extends TestCase
{
    /** Builds a model carrying $attributes as its raw (database-side) values. */
    private function withRawAttributes(string $class, array $attributes): object
    {
        $Model = new $class();
        $Model->setRawAttributes($attributes);

        return $Model;
    }

    // -----------------------------------------------------------------------
    // The accessors are actually bound to their columns
    // -----------------------------------------------------------------------

    #[DataProvider('sanitizedColumnDataProvider')]
    public function testTheColumnIsBoundToAnAttributeAccessor(string $class, string $column): void
    {
        $Model = new $class();

        $this->assertTrue(
            $Model->hasAttributeGetMutator($column),
            $class . '::$' . $column . ' has no attribute accessor - check the method is named ' .
            \Illuminate\Support\Str::camel($column) . '()'
        );
    }

    public static function sanitizedColumnDataProvider(): array
    {
        return [
            'Bible description'         => [Bible::class, 'description'],
            'Bible copyright statement' => [Bible::class, 'copyright_statement'],
            // Post::$content, not $description: content is what the admin writes through
            // CKEditor and what resources/views/docs/{tos,privacy}.php echo unescaped.
            'Post content'              => [Post::class,  'content'],
            // The Strong's lexicon is imported HTML like the rest. Sanitizing it here rather
            // than in Engine::_formatStrongs() is what lets the v3 engine hand those two
            // fields to _processHtml() alone and get Markdown back instead of HTML.
            'Strongs root word'         => [StrongsDefinition::class, 'root_word'],
            'Strongs entry'             => [StrongsDefinition::class, 'entry'],
        ];
    }

    /**
     * Imported module descriptions are a whole HTML document with the import credit appended
     * after </html> - Importers\MyBible builds exactly that. HTMLPurifier discards anything
     * past the document close, so 16 of the Bibles installed here were reading back without
     * their credit, and since the accessor is also the mutator a re-import made it permanent.
     */
    public function testReadingADescriptionKeepsTheImportCreditAppendedAfterTheDocument(): void
    {
        $Bible = $this->withRawAttributes(Bible::class, [
            'description' => '<html><head><title>T</title></head><body><p>Module desc</p></body></html>'
                . '<br /><br />This Bible imported from The Unbound Bible <a href="http://unbound.biola.edu/">unbound.biola.edu</a>',
        ]);

        $this->assertStringContainsString('<p>Module desc</p>', $Bible->description);
        $this->assertStringContainsString('This Bible imported from The Unbound Bible', $Bible->description);
        $this->assertStringContainsString('unbound.biola.edu', $Bible->description);
    }

    /**
     * 'tvm' deliberately has no accessor: Engine::_formatStrongs() has to strip the
     * '<b>Count:</b> n ...<br>' prefix off the raw column before anything sanitizes it, so
     * that field alone is still sanitized by the engine.
     */
    public function testTheStrongsTvmColumnIsLeftToTheEngine(): void
    {
        $this->assertFalse((new StrongsDefinition())->hasAttributeGetMutator('tvm'));
    }

    // -----------------------------------------------------------------------
    // Reading
    // -----------------------------------------------------------------------

    #[DataProvider('sanitizedColumnDataProvider')]
    public function testReadingTheColumnStripsUnsafeMarkup(string $class, string $column): void
    {
        $Model = $this->withRawAttributes($class, [
            $column => '<p>Ok</p><script>alert(1)</script><img src=x onerror=alert(1)>',
        ]);

        $value = $Model->{$column};

        $this->assertStringContainsString('<p>Ok</p>', $value);
        $this->assertStringNotContainsString('<script', $value);
        $this->assertStringNotContainsString('onerror', $value);
    }

    #[DataProvider('sanitizedColumnDataProvider')]
    public function testReadingTheColumnKeepsWhitelistedMarkup(string $class, string $column): void
    {
        $Model = $this->withRawAttributes($class, [$column => '<p><b>Bold</b> and <i>italic</i></p>']);

        $this->assertSame('<p><b>Bold</b> and <i>italic</i></p>', $Model->{$column});
    }

    /**
     * Several installed Bibles have no description and 14,248 of the 14,696 Strong's
     * definitions have no 'tvm', so a NULL column has to read back without fataling - and as
     * NULL, not as the sanitizer's empty string. The API has always reported an absent field
     * as null and '/api/{action}' is kept for backward compatibility, so a client testing
     * '=== null' has to keep working.
     */
    #[DataProvider('nullPreservingColumnDataProvider')]
    public function testReadingANullColumnReturnsNull(string $class, string $column): void
    {
        $Model = $this->withRawAttributes($class, [$column => NULL]);

        $this->assertNull($Model->{$column});
    }

    /**
     * Every sanitized column except Bible::$copyright_statement, which normalises an absent
     * value to '' instead - see the note on Bible::copyrightStatement(). The API never
     * reports that column directly, so the distinction cannot reach a client.
     */
    public static function nullPreservingColumnDataProvider(): array
    {
        $columns = self::sanitizedColumnDataProvider();

        unset($columns['Bible copyright statement']);

        return $columns;
    }

    public function testReadingANullCopyrightStatementReturnsTheEmptyString(): void
    {
        $Bible = $this->withRawAttributes(Bible::class, ['copyright_statement' => NULL]);

        $this->assertSame('', $Bible->copyright_statement);
    }

    // -----------------------------------------------------------------------
    // The editor columns
    // -----------------------------------------------------------------------

    /**
     * The columns an administrator edits in a WYSIWYG field go through
     * Helpers::sanitizeEditorHtml(), which is wider than what the API emits.
     *
     * admin/postconfig.blade.php reads Post::$content back into CKEditor through this very
     * accessor, so the allowlist decides what the administrator is shown when the page loads
     * - and therefore what PostConfigController writes back when they press Save without
     * changing anything. On the API allowlist that round trip destroyed the document's
     * images, rules and code spans, with nothing left to restore them from.
     *
     * @param string $class
     * @param string $column
     */
    #[DataProvider('editorColumnDataProvider')]
    public function testReadingAnEditorColumnKeepsTheEditorsMarkup(string $class, string $column): void
    {
        $Model = $this->withRawAttributes($class, [
            $column => '<p>Terms</p><img src="/logo.png" alt="Logo"><hr><p>A <code>span</code> and <s>struck</s></p>',
        ]);

        $value = $Model->{$column};

        $this->assertStringContainsString('<img src="/logo.png"', $value, $column);
        $this->assertStringContainsString('<hr />', $value, $column);
        $this->assertStringContainsString('<code>span</code>', $value, $column);
        $this->assertStringContainsString('<s>struck</s>', $value, $column);
    }

    /** Wider, not weaker - these columns are echoed unescaped by the documentation views. */
    #[DataProvider('editorColumnDataProvider')]
    public function testReadingAnEditorColumnStillStripsTheVectors(string $class, string $column): void
    {
        $Model = $this->withRawAttributes($class, [
            $column => '<p>Ok</p><script>alert(1)</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">x</a>',
        ]);

        $value = $Model->{$column};

        $this->assertStringContainsString('<p>Ok</p>', $value, $column);
        $this->assertStringNotContainsString('<script', $value, $column);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $value, $column);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $value, $column);
    }

    public static function editorColumnDataProvider(): array
    {
        return [
            'Post content'              => [Post::class,  'content'],
            'Bible description'         => [Bible::class, 'description'],
            'Bible copyright statement' => [Bible::class, 'copyright_statement'],
        ];
    }

    /**
     * The round trip the admin form performs: read into the editor, save back unchanged,
     * read again. Unless that is a fixed point the document erodes on every save.
     */
    public function testThePostEditorRoundTripIsLossless(): void
    {
        $original = '<p>Terms</p><img src="/logo.png" alt="Logo"><hr><p><code>x = 1</code></p>';

        $first = $this->withRawAttributes(Post::class, ['content' => $original])->content;

        $Saved = new Post();
        $Saved->content = $first;

        $this->assertSame($first, $Saved->getAttributes()['content'], 'Save altered what the editor was given');
        $this->assertSame($first, $Saved->content, 'Reading it back altered it again');
    }

    /**
     * The three toolbar features that were being destroyed on that round trip.
     *
     * public/js/bin/ckeditor5/src/ckeditor.ts ships alignment and indent, which write their
     * result into a style attribute, and postconfig.blade.php configures the openInNewTab
     * link decorator, which writes target/rel. None of the three was on the allowlist, and
     * because the editor reads the column back through the accessor that sanitizes it, an
     * administrator who centred a paragraph and saved twice lost the formatting for good.
     *
     * @param string $html
     * @param string $expected_fragment
     */
    #[DataProvider('ckeditorToolbarDataProvider')]
    public function testTheEditorKeepsWhatItsToolbarWrites(string $html, string $expected_fragment): void
    {
        $content = $this->withRawAttributes(Post::class, ['content' => $html])->content;

        $this->assertStringContainsString($expected_fragment, $content);

        $Saved = new Post();
        $Saved->content = $content;

        $this->assertSame($content, $Saved->content, 'The round trip eroded it');
    }

    public static function ckeditorToolbarDataProvider(): array
    {
        return [
            'alignment'      => ['<p style="text-align:center">Centred</p>',              'text-align:center'],
            'indent'         => ['<p style="margin-left:40px">Indented</p>',              'margin-left:40px'],
            'open in new tab'=> ['<a href="https://e.test" target="_blank">Link</a>',     'target="_blank"'],
        ];
    }

    /** And the new tab it opens cannot reach back through window.opener. */
    public function testALinkOpenedInANewTabCarriesTheRelThatClosesTheOpener(): void
    {
        $content = $this->withRawAttributes(Post::class, [
            'content' => '<a href="https://e.test" target="_blank">Link</a>',
        ])->content;

        $this->assertStringContainsString('noopener', $content);
        $this->assertStringContainsString('noreferrer', $content);
    }

    /**
     * The Strong's columns are imported lexicon HTML, not something anybody edits, so they
     * stay on the narrower API allowlist - the split only exists for the editor columns.
     */
    #[DataProvider('strongsColumnDataProvider')]
    public function testTheStrongsColumnsStayOnTheApiAllowlist(string $column): void
    {
        $Model = $this->withRawAttributes(StrongsDefinition::class, [$column => '<p>Ok</p><img src="/l.png"><hr>']);

        $value = $Model->{$column};

        $this->assertStringContainsString('<p>Ok</p>', $value);
        $this->assertStringNotContainsString('<img', $value);
        $this->assertStringNotContainsString('<hr', $value);
    }

    public static function strongsColumnDataProvider(): array
    {
        return [
            'root word' => ['root_word'],
            'entry'     => ['entry'],
        ];
    }

    // -----------------------------------------------------------------------
    // Absence has one shape
    // -----------------------------------------------------------------------

    /**
     * An empty column reads back as NULL, not ''. The accessors preserved a NULL but let an
     * empty string through as '', so a column could report absence two different ways
     * depending on whether anything had ever written to it - and a client branching on
     * '=== null' saw one of them and not the other.
     *
     * @param string $class
     * @param string $column
     */
    #[DataProvider('nullPreservingColumnDataProvider')]
    public function testReadingAnEmptyColumnReturnsNull(string $class, string $column): void
    {
        $Model = $this->withRawAttributes($class, [$column => '']);

        $this->assertNull($Model->{$column});
    }

    /** And writing an empty value stores NULL, so it cannot be persisted as '' either. */
    #[DataProvider('nullPreservingMutatedColumnDataProvider')]
    public function testWritingAnEmptyValueStoresNull(string $class, string $column): void
    {
        $Model = new $class();
        $Model->{$column} = '';

        $this->assertNull($Model->getAttributes()[$column]);
    }

    /**
     * The columns with a mutator, less Bible::$copyright_statement, which normalises absence
     * the other way - see testTheCopyrightStatementStillNormalisesAbsenceToTheEmptyString().
     */
    public static function nullPreservingMutatedColumnDataProvider(): array
    {
        $columns = self::mutatedColumnDataProvider();

        unset($columns['Bible copyright statement']);

        return $columns;
    }

    /**
     * '0' is content. Helpers::sanitizeHtml() tests the empty string rather than falsiness
     * precisely so a column holding a single zero is not read as absent.
     *
     * @param string $class
     * @param string $column
     */
    #[DataProvider('nullPreservingColumnDataProvider')]
    public function testAZeroIsNotTreatedAsAbsent(string $class, string $column): void
    {
        $Model = $this->withRawAttributes($class, [$column => '0']);

        $this->assertSame('0', $Model->{$column});
    }

    /**
     * The exception, in both directions: Bible::$copyright_statement keeps its own contract
     * that absence is '' - see the note on Bible::copyrightStatement().
     */
    public function testTheCopyrightStatementStillNormalisesAbsenceToTheEmptyString(): void
    {
        $Bible = new Bible();
        $Bible->copyright_statement = '';

        $this->assertSame('', $Bible->getAttributes()['copyright_statement']);
        $this->assertSame('', $this->withRawAttributes(Bible::class, ['copyright_statement' => ''])->copyright_statement);
        $this->assertSame('', $this->withRawAttributes(Bible::class, ['copyright_statement' => NULL])->copyright_statement);
    }

    // -----------------------------------------------------------------------
    // Writing
    // -----------------------------------------------------------------------

    #[DataProvider('mutatedColumnDataProvider')]
    public function testWritingTheColumnStoresSanitizedMarkup(string $class, string $column): void
    {
        $Model = new $class();
        $Model->{$column} = '<p>Ok</p><script>alert(1)</script>';

        $stored = $Model->getAttributes()[$column];

        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringContainsString('<p>Ok</p>', $stored);
    }

    /**
     * The columns whose value arrives from an import and can be restored by re-importing it -
     * so sanitizing on the way in costs nothing that cannot be got back. Post::$content is
     * not one of them; see testThePostContentHasNoMutator().
     */
    public static function mutatedColumnDataProvider(): array
    {
        return [
            'Bible description'         => [Bible::class, 'description'],
            'Bible copyright statement' => [Bible::class, 'copyright_statement'],
            'Strongs root word'         => [StrongsDefinition::class, 'root_word'],
            'Strongs entry'             => [StrongsDefinition::class, 'entry'],
        ];
    }

    /**
     * An absent description is stored as NULL, not as ''. The column is nullable and reads
     * back as NULL, so a save must not quietly turn one shape into the other.
     */
    public function testWritingNullToTheDescriptionStoresNull(): void
    {
        $Bible = new Bible();
        $Bible->description = NULL;

        $this->assertNull($Bible->getAttributes()['description']);
    }

    /** The copyright statement is the exception - see testReadingANullCopyrightStatement(). */
    public function testWritingNullToTheCopyrightStatementStoresTheEmptyString(): void
    {
        $Bible = new Bible();
        $Bible->copyright_statement = NULL;

        $this->assertSame('', $Bible->getAttributes()['copyright_statement']);
    }

    /**
     * Post::$content is sanitized on read only.
     *
     * It is the one sanitized column with no importable source behind it: an admin types it
     * into CKEditor, and that build ships the image, horizontal-line, highlight, strikethrough,
     * code, page-break and font plugins, none of whose markup survives SANITIZE_HTML_ALLOWED.
     * A mutator would strip an inserted image on Save and write the loss over the column, with
     * nothing left to restore it from - and it would buy nothing, because the accessor
     * sanitizes the value again on the way out.
     */
    public function testThePostContentHasNoMutator(): void
    {
        $this->assertFalse(
            (new Post())->hasAttributeSetMutator('content'),
            'Post::$content must sanitize on read only - a mutator destroys what the admin typed'
        );
    }

    public function testWritingThePostContentStoresItUnchanged(): void
    {
        $Post = new Post();
        $Post->content = '<p>Terms</p><figure class="image"><img src="/logo.png"></figure>';

        $this->assertSame('<p>Terms</p><figure class="image"><img src="/logo.png"></figure>', $Post->getAttributes()['content']);
    }

    /** What the accessor hands back is still sanitized, which is what the views rely on. */
    public function testReadingThePostContentSanitizesWhatTheMutatorNoLongerDoes(): void
    {
        $Post = new Post();
        $Post->content = '<p>Terms</p><script>alert(1)</script>';

        $this->assertStringContainsString('<p>Terms</p>', $Post->content);
        $this->assertStringNotContainsString('<script', $Post->content);
    }

    public function testWritingNullToThePostContentLeavesItNull(): void
    {
        $Post = new Post();
        $Post->content = NULL;

        $this->assertNull($Post->getAttributes()['content']);
    }

    /**
     * copyright_statement used to be written through a setCopyrightStatementAttribute()
     * mutator that trimmed. That mutator would have shadowed the Attribute's set() entirely -
     * setAttribute() consults hasSetMutator() first - so it was removed; sanitizeHtml() trims
     * on its own and what is stored does not change.
     */
    public function testWritingTheCopyrightStatementStillTrims(): void
    {
        $Bible = new Bible();
        $Bible->copyright_statement = '   <p>Public domain</p>   ';

        $this->assertSame('<p>Public domain</p>', $Bible->getAttributes()['copyright_statement']);
    }

    /**
     * The trimming mutator is gone; if it comes back it silently disables the sanitizer above,
     * because setAttribute() reaches hasSetMutator() before it reaches hasAttributeSetMutator().
     */
    public function testTheLegacyCopyrightStatementMutatorIsGone(): void
    {
        $this->assertFalse(
            method_exists(Bible::class, 'setCopyrightStatementAttribute'),
            'setCopyrightStatementAttribute() shadows the copyrightStatement() Attribute'
        );
    }
}
