<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Models\Bible;
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
     * Creative Commons statements are generated rather than stored, and carry a copyright
     * line. With no Bible to read a year and owner from, the placeholders remain.
     */
    public function testCreativeCommonsWithoutABibleKeepsThePlaceholders(): void
    {
        $copyright = $this->copyright([
            'type' => 'creative_commons',
            'name' => 'CC BY-SA 4.0',
            'url'  => 'https://example.test/cc',
        ]);

        $statement = $copyright->getProcessedCopyrightStatement();

        $this->assertStringContainsString('Copyright &copy; [year] [owner]', $statement);
        $this->assertStringContainsString('CC BY-SA 4.0', $statement);
        $this->assertStringContainsString('https://example.test/cc', $statement);
    }

    public function testCreativeCommonsUsesTheBibleYearAndOwner(): void
    {
        $copyright = $this->copyright([
            'type' => 'creative_commons',
            'name' => 'CC BY 4.0',
            'url'  => 'https://example.test/cc',
        ]);

        $bible        = new Bible();
        $bible->year  = 1611;
        $bible->owner = 'Example Society';

        $statement = $copyright->getProcessedCopyrightStatement($bible);

        $this->assertStringContainsString('Copyright &copy; 1611 Example Society', $statement);
    }

    public function testCreativeCommonsWithOnlyAYear(): void
    {
        $copyright = $this->copyright(['type' => 'creative_commons', 'name' => 'CC', 'url' => 'u']);

        $bible        = new Bible();
        $bible->year  = 1769;
        $bible->owner = null;

        $this->assertStringContainsString('Copyright &copy; 1769<br />', $copyright->getProcessedCopyrightStatement($bible));
    }

    public function testCreativeCommonsWithOnlyAnOwner(): void
    {
        $copyright = $this->copyright(['type' => 'creative_commons', 'name' => 'CC', 'url' => 'u']);

        $bible        = new Bible();
        $bible->year  = null;
        $bible->owner = 'Example Society';

        $this->assertStringContainsString('Copyright &copy; Example Society', $copyright->getProcessedCopyrightStatement($bible));
    }

    /**
     * A Bible with neither year nor owner must not emit a bare "Copyright ©" line.
     */
    public function testCreativeCommonsOmitsTheCopyrightLineWhenTheBibleHasNeither(): void
    {
        $copyright = $this->copyright(['type' => 'creative_commons', 'name' => 'CC', 'url' => 'u']);

        $bible        = new Bible();
        $bible->year  = null;
        $bible->owner = null;

        $statement = $copyright->getProcessedCopyrightStatement($bible);

        $this->assertStringNotContainsString('Copyright &copy;', $statement);
        $this->assertStringContainsString('This Bible is made available', $statement);
    }
}
