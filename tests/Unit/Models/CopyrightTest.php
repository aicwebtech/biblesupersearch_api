<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
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
     * The real copyright year and owner live on the Bible, not on the licence, so
     * Bible::getCopyrightStatement() is what fills them in - see
     * tests/Feature/Models/BibleCopyrightStatementTest.php.
     */
    public function testCreativeCommonsIsBuiltFromTheLicenceNameAndUrl(): void
    {
        $copyright = $this->copyright([
            'type' => 'creative_commons',
            'name' => 'CC BY-SA 4.0',
            'url'  => 'https://example.test/cc',
        ]);

        $statement = $copyright->getProcessedCopyrightStatement();

        $this->assertStringContainsString('This text is made available', $statement);
        $this->assertStringContainsString('CC BY-SA 4.0', $statement);
        $this->assertStringContainsString('https://example.test/cc', $statement);
    }

    /**
     * With no Bible to read a year and owner from, the sanitized statement carries the
     * placeholder - this is the admin preview of a licence, where there is no one text to
     * name.
     *
     * The placeholder used to be assembled into a variable the next line overwrote, so it
     * never reached the output at all.
     */
    public function testCreativeCommonsCarriesTheYearAndOwnerPlaceholder(): void
    {
        $copyright = $this->copyright([
            'type' => 'creative_commons',
            'name' => 'CC BY-SA 4.0',
            'url'  => 'https://example.test/cc',
        ]);

        $statement = $copyright->getProcessedCopyrightStatement();

        $this->assertStringContainsString('[year]', $statement);
        $this->assertStringContainsString('[owner]', $statement);
        $this->assertStringContainsString('This text is made available', $statement);
    }

    /** And it leads, the way Bible::getCopyrightStatement() leads with the real values. */
    public function testThePlaceholderPrecedesTheLicenceText(): void
    {
        $copyright = $this->copyright([
            'type' => 'creative_commons',
            'name' => 'CC BY-SA 4.0',
            'url'  => 'https://example.test/cc',
        ]);

        $statement = $copyright->getProcessedCopyrightStatement();

        $this->assertLessThan(
            strpos($statement, 'This text is made available'),
            strpos($statement, '[year]'),
            'The placeholder should lead the licence text'
        );
    }

    /**
     * Raw is Bible::getCopyrightStatement() asking, and it has the Bible's real year and
     * owner to put there - a placeholder would end up in the response beside them.
     */
    public function testRawCarriesNoPlaceholder(): void
    {
        $copyright = $this->copyright([
            'type' => 'creative_commons',
            'name' => 'CC BY-SA 4.0',
            'url'  => 'https://example.test/cc',
        ]);

        $statement = $copyright->getProcessedCopyrightStatement(NULL, TRUE);

        $this->assertStringNotContainsString('[year]', $statement);
        $this->assertStringNotContainsString('[owner]', $statement);
        $this->assertStringNotContainsStringIgnoringCase('copyright &copy;', $statement);
    }

    /** Only the Creative Commons branch generates a statement of its own to lead. */
    public function testANonCreativeCommonsLicenceCarriesNoPlaceholder(): void
    {
        $copyright = $this->copyright([
            'type'                        => 'public_domain',
            'url'                         => NULL,
            'default_copyright_statement' => 'Public domain.',
        ]);

        $this->assertSame('Public domain.', $copyright->getProcessedCopyrightStatement());
    }

    /**
     * The Bible comes first and $raw second. Pinned because both orderings type-check for a
     * caller passing only the second argument positionally: getProcessedCopyrightStatement(TRUE)
     * was the raw statement under the old signature and is a TypeError under this one.
     */
    public function testItTakesTheBibleFirstAndRawSecond(): void
    {
        $Parameters = (new \ReflectionMethod(Copyright::class, 'getProcessedCopyrightStatement'))->getParameters();

        $this->assertCount(2, $Parameters);

        $this->assertSame('Bible', $Parameters[0]->getName());
        $this->assertSame('?App\\Models\\Bible', (string) $Parameters[0]->getType());
        $this->assertTrue($Parameters[0]->isOptional());

        $this->assertSame('raw', $Parameters[1]->getName());
        $this->assertSame('bool', (string) $Parameters[1]->getType());
        $this->assertTrue($Parameters[1]->isOptional());
    }

    /**
     * A Bible in hand means the statement is that Bible's, so the whole question goes back to
     * it - the licence record has no year or owner of its own to put in one.
     */
    public function testABibleIsAnsweredWithItsOwnStatement(): void
    {
        $copyright = $this->copyright([
            'type'                        => 'public_domain',
            'url'                         => NULL,
            'default_copyright_statement' => 'Public domain.',
        ]);

        $Bible = new \App\Models\Bible();
        $Bible->setRawAttributes(['copyright_statement' => 'Used by permission.']);

        $this->assertSame('Used by permission.', $copyright->getProcessedCopyrightStatement($Bible));
    }

}
