<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Models\Copyright;

/**
 * Copyright::getProcessedCopyrightStatement() builds the statement shown beneath a Bible.
 * It reads only attributes already on the models, so it runs here with no database.
 */
class CopyrightTest extends TestCase
{
    private function copyright(array $attributes): Copyright
    {
        $copyright = new Copyright();

        foreach ($attributes as $key => $value) {
            $copyright->{$key} = $value;
        }

        return $copyright;
    }

    public function testTableName(): void
    {
        $this->assertSame('copyrights', (new Copyright())->getTable());
    }

    public function testFillableIncludesTheCopyrightStatement(): void
    {
        $this->assertContains('default_copyright_statement', (new Copyright())->getFillable());
    }

    /**
     * A plain licence with no url is passed through untouched.
     */
    public function testPlainStatementIsReturnedUnchanged(): void
    {
        $copyright = $this->copyright([
            'type'                        => 'public_domain',
            'url'                         => null,
            'default_copyright_statement' => 'Public domain.',
        ]);

        $this->assertSame('Public domain.', $copyright->getProcessedCopyrightStatement());
    }

    public function testNonCreativeCommonsWithAUrlAppendsTheLicenceLink(): void
    {
        $copyright = $this->copyright([
            'type'                        => 'other',
            'url'                         => 'https://example.test/licence',
            'default_copyright_statement' => 'Used by permission.',
        ]);

        $statement = $copyright->getProcessedCopyrightStatement();

        $this->assertStringStartsWith('Used by permission.', $statement);
        $this->assertStringContainsString('https://example.test/licence', $statement);
        $this->assertStringContainsString('can be found', $statement);
    }

    /**
     * The Creative Commons statement is generated rather than stored, from the licence's own
     * name and URL.
     *
     * It no longer carries the copyright year and owner line: those live on the Bible, not on
     * the licence, so Bible::getCopyrightStatement() appends them - see
     * tests/Feature/Models/BibleCopyrightStatementTest.php. This method never sees a Bible.
     */
    public function testCreativeCommonsIsBuiltFromTheLicenceNameAndUrl(): void
    {
        $copyright = $this->copyright([
            'type' => 'creative_commons',
            'name' => 'CC BY-SA 4.0',
            'url'  => 'https://example.test/cc',
        ]);

        $statement = $copyright->getProcessedCopyrightStatement();

        $this->assertStringContainsString('This Bible is made available', $statement);
        $this->assertStringContainsString('CC BY-SA 4.0', $statement);
        $this->assertStringContainsString('https://example.test/cc', $statement);
    }

    /** The year and owner are the Bible's, and no placeholder for them is left behind. */
    public function testCreativeCommonsCarriesNoCopyrightYearOrOwner(): void
    {
        $copyright = $this->copyright([
            'type' => 'creative_commons',
            'name' => 'CC BY-SA 4.0',
            'url'  => 'https://example.test/cc',
        ]);

        $statement = $copyright->getProcessedCopyrightStatement();

        $this->assertStringNotContainsString('[year]', $statement);
        $this->assertStringNotContainsString('[owner]', $statement);
        $this->assertStringNotContainsStringIgnoringCase('copyright &copy;', $statement);
    }

    /**
     * The method takes no Bible any more. Pinned because the old signature accepted one by
     * reference, so a stale caller passing one would be a TypeError rather than a no-op.
     */
    public function testItTakesNoBible(): void
    {
        $Parameters = (new \ReflectionMethod(Copyright::class, 'getProcessedCopyrightStatement'))->getParameters();

        $this->assertCount(1, $Parameters);
        $this->assertSame('raw', $Parameters[0]->getName());
        $this->assertSame('bool', (string) $Parameters[0]->getType());
    }

    // -----------------------------------------------------------------------
    // $raw - who purifies the result
    // -----------------------------------------------------------------------

    /**
     * default_copyright_statement is admin-supplied and is concatenated in as it stands, so
     * by default the method purifies what it returns rather than trusting the column.
     */
    public function testTheStatementIsPurifiedByDefault(): void
    {
        $copyright = $this->copyright([
            'type'                        => 'public_domain',
            'url'                         => null,
            'default_copyright_statement' => 'Public domain. <script>alert(1)</script><img src=x onerror=alert(1)>',
        ]);

        $statement = $copyright->getProcessedCopyrightStatement();

        $this->assertStringContainsString('Public domain.', $statement);
        $this->assertStringNotContainsString('<script', $statement);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $statement);
    }

    /**
     * $raw hands the statement back unpurified, for a caller that is going to add to it and
     * purify the whole thing itself - which is what Bible::getCopyrightStatement() does when
     * it appends the copyright year and owner.
     */
    public function testRawReturnsTheStatementUnpurified(): void
    {
        $copyright = $this->copyright([
            'type'                        => 'public_domain',
            'url'                         => null,
            'default_copyright_statement' => 'Public domain. <script>alert(1)</script>',
        ]);

        $this->assertStringContainsString('<script>alert(1)</script>', $copyright->getProcessedCopyrightStatement(TRUE));
    }

    /**
     * The URL lands inside a single-quoted href and is admin-supplied, so an apostrophe in it
     * would close the attribute. It is escaped even in raw mode, because raw only defers the
     * purifier - it does not hand back an unescaped interpolation.
     */
    public function testAHostileUrlIsEscapedEvenInRawMode(): void
    {
        $copyright = $this->copyright([
            'type'                        => 'other',
            'url'                         => "x' onmouseover='alert(1)",
            'default_copyright_statement' => 'Used by permission.',
        ]);

        $raw = $copyright->getProcessedCopyrightStatement(TRUE);

        $this->assertStringContainsString('&#039;', $raw);
        $this->assertStringNotContainsString("x' onmouseover", $raw);
    }
}
