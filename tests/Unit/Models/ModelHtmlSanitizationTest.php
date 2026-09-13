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
     * Several installed Bibles have no description and both seeded posts have none, so a NULL
     * column has to read back as the sanitizer's empty string rather than fataling.
     */
    #[DataProvider('sanitizedColumnDataProvider')]
    public function testReadingANullColumnReturnsTheEmptyString(string $class, string $column): void
    {
        $Model = $this->withRawAttributes($class, [$column => NULL]);

        $this->assertSame('', $Model->{$column});
    }

    // -----------------------------------------------------------------------
    // Writing
    // -----------------------------------------------------------------------

    #[DataProvider('sanitizedColumnDataProvider')]
    public function testWritingTheColumnStoresSanitizedMarkup(string $class, string $column): void
    {
        $Model = new $class();
        $Model->{$column} = '<p>Ok</p><script>alert(1)</script>';

        $stored = $Model->getAttributes()[$column];

        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringContainsString('<p>Ok</p>', $stored);
    }

    /**
     * The Bible columns follow the sanitizer's contract on write: an absent value is stored
     * as ''. Both were nullable before, so this is the one place a save changes shape.
     */
    #[DataProvider('bibleColumnDataProvider')]
    public function testWritingNullToABibleColumnStoresTheEmptyString(string $column): void
    {
        $Bible = new Bible();
        $Bible->{$column} = NULL;

        $this->assertSame('', $Bible->getAttributes()[$column]);
    }

    public static function bibleColumnDataProvider(): array
    {
        return [
            'description'         => ['description'],
            'copyright statement' => ['copyright_statement'],
        ];
    }

    /**
     * Post::$content keeps NULL rather than storing '', which is the opposite of the Bible
     * columns above. Recorded rather than asserted as the house rule - the two mutators
     * genuinely differ, and posts.content NULL-vs-'' is the admin form's business.
     */
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
