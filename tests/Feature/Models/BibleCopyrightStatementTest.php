<?php

namespace Tests\Feature\Models;

use Tests\TestCase;
use App\Models\Bible;
use App\Models\Copyright;

/**
 * Bible::getCopyrightStatement() appends the copyright year and owner.
 *
 * Those two belong to the text, not to the licence, so Copyright::getProcessedCopyrightStatement()
 * no longer takes a Bible and no longer builds that line - it hands back the licence statement
 * raw and the Bible adds its own year and owner before purifying the whole thing.
 *
 * A feature test rather than a unit one: the line is labelled with __('basic.copyright'), and
 * the translator is only resolvable from a booted application. The models themselves are built
 * in memory with setRawAttributes() and setRelation(), so nothing is read from or written to
 * the database.
 */
class BibleCopyrightStatementTest extends TestCase
{
    /** A Bible on the generated branch: no statement of its own, a licence record attached. */
    private function bible(array $attributes, array $copyright = []): Bible
    {
        $Copyright = new Copyright();
        $Copyright->setRawAttributes($copyright + [
            'type'                        => 'public_domain',
            'url'                         => '',
            'default_copyright_statement' => 'Public domain.',
        ]);

        $Bible = new Bible();
        $Bible->setRawAttributes($attributes + ['copyright_statement' => '', 'copyright_id' => 7]);
        $Bible->setRelation('copyrightInfo', $Copyright);

        return $Bible;
    }

    public function testTheYearAndOwnerAreBothAppended(): void
    {
        $statement = $this->bible(['year' => 1611, 'owner' => 'Example Society'])->getCopyrightStatement();

        $this->assertStringContainsString(__('basic.copyright') . ' © 1611 Example Society', $statement);
        $this->assertStringContainsString('Public domain.', $statement);
    }

    public function testOnlyAYear(): void
    {
        $statement = $this->bible(['year' => 1769, 'owner' => NULL])->getCopyrightStatement();

        $this->assertStringContainsString(__('basic.copyright') . ' © 1769', $statement);
        $this->assertStringNotContainsString('1769 ', $statement);
    }

    public function testOnlyAnOwner(): void
    {
        $statement = $this->bible(['year' => NULL, 'owner' => 'Example Society'])->getCopyrightStatement();

        $this->assertStringContainsString(__('basic.copyright') . ' © Example Society', $statement);
    }

    /** Neither one, and the label must not be emitted on its own. */
    public function testNeitherLeavesNoCopyrightLine(): void
    {
        $statement = $this->bible(['year' => NULL, 'owner' => NULL])->getCopyrightStatement();

        $this->assertStringNotContainsString('©', $statement);
        $this->assertStringNotContainsString(__('basic.copyright'), $statement);
        $this->assertStringContainsString('Public domain.', $statement);
    }

    /** An empty string is as absent as a NULL - neither is a year or an owner. */
    public function testEmptyStringsAreTreatedAsAbsent(): void
    {
        $statement = $this->bible(['year' => '', 'owner' => ''])->getCopyrightStatement();

        $this->assertStringNotContainsString('©', $statement);
    }

    /**
     * Every licence type carries the line now. It used to be built only for
     * 'creative_commons', so a Bible under any other licence reported no copyright holder at
     * all however its record was filled in.
     */
    public function testEveryLicenceTypeCarriesTheLine(): void
    {
        foreach(['public_domain', 'creative_commons', 'other'] as $type) {
            $statement = $this->bible(
                ['year' => 1611, 'owner' => 'Example Society'],
                ['type' => $type, 'name' => 'CC BY-SA 4.0', 'url' => 'https://example.test/cc']
            )->getCopyrightStatement();

            $this->assertStringContainsString('1611 Example Society', $statement, $type);
        }
    }

    /** The label comes from the lang file, so a translated install does not emit English. */
    public function testTheLabelComesFromTheLanguageFile(): void
    {
        app('translator')->addLines(['basic.copyright' => 'Urheberrecht'], 'de');

        $original = app()->getLocale();

        try {
            app()->setLocale('de');

            $this->assertStringContainsString(
                'Urheberrecht © 1611',
                $this->bible(['year' => 1611, 'owner' => NULL])->getCopyrightStatement()
            );
        }
        finally {
            app()->setLocale($original);
        }
    }

    /**
     * The licence statement is fetched raw so the year and owner can be appended before
     * anything purifies it - which means the Bible has to purify the result itself.
     */
    public function testTheWholeStatementIsPurified(): void
    {
        $statement = $this->bible(
            ['year' => 1611, 'owner' => 'Example <script>alert(1)</script> Society'],
            ['default_copyright_statement' => 'Public domain. <img src=x onerror=alert(1)>']
        )->getCopyrightStatement();

        $this->assertStringContainsString('Public domain.', $statement);
        $this->assertStringContainsString('1611', $statement);
        $this->assertStringNotContainsString('<script', $statement);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $statement);
    }

    /**
     * The symbol arrives as a literal '©', not as '&copy;'. The statement is written with the
     * entity and HTMLPurifier decodes it - it emits UTF-8 and escapes only the characters that
     * have to be escaped - so that is the shape a client receives.
     */
    public function testTheCopyrightSymbolIsDecoded(): void
    {
        $statement = $this->bible(['year' => 1611, 'owner' => 'Example Society'])->getCopyrightStatement();

        $this->assertStringContainsString('©', $statement);
        $this->assertStringNotContainsString('&copy;', $statement);
    }

    /** The line goes after the licence text, not before it. */
    public function testTheLineIsAppendedNotPrepended(): void
    {
        $statement = $this->bible(['year' => 1611, 'owner' => 'Example Society'])->getCopyrightStatement();

        $this->assertLessThan(
            strpos($statement, '1611'),
            strpos($statement, 'Public domain.'),
            'The copyright line should follow the licence statement'
        );
    }

    /** A Bible with its own statement never reaches the licence record at all. */
    public function testAnOwnStatementIsUsedInsteadAndCarriesNoYear(): void
    {
        $Bible = $this->bible(['year' => 1611, 'owner' => 'Example Society']);
        $Bible->setRawAttributes(['copyright_statement' => 'Used by permission.', 'copyright_id' => 7, 'year' => 1611]);

        $statement = $Bible->getCopyrightStatement();

        $this->assertSame('Used by permission.', $statement);
        $this->assertStringNotContainsString('1611', $statement);
    }
}
