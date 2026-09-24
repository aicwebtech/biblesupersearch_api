<?php

namespace Tests\Feature\Models;

use Tests\TestCase;
use App\Models\Bible;
use App\Models\Copyright;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Bible::getCopyrightStatement() leads a Creative Commons statement with the copyright year
 * and owner.
 *
 * Those two belong to the text, not to the licence, so Copyright::getProcessedCopyrightStatement()
 * no longer builds that line itself - it hands back the licence statement raw and the Bible
 * puts its own year and owner in front before purifying the whole thing. Handed a Bible, the
 * licence record delegates straight back here rather than answering for it.
 *
 * The line is for 'creative_commons' and no other licence type: a CC licence is what requires
 * the holder to be named, and a public domain text has no copyright holder to name - 39 of the
 * installed Bibles are public domain and carry a year, and leading their statement with
 * 'Copyright (c) 1901' contradicts the 'This Bible is in the Public Domain' that follows it.
 *
 * A feature test rather than a unit one: the line is labelled with __('basic.copyright'), and
 * the translator is only resolvable from a booted application. The models themselves are built
 * in memory with setRawAttributes() and setRelation(), so nothing is read from or written to
 * the database.
 */
class BibleCopyrightStatementTest extends TestCase
{
    /** The licence attributes of a Creative Commons record - the one type that carries the line. */
    private const CREATIVE_COMMONS = [
        'type' => 'creative_commons',
        'name' => 'CC BY-SA 4.0',
        'url'  => 'https://example.test/cc',
    ];

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

    /** The same, under a Creative Commons licence. */
    private function creativeCommonsBible(array $attributes): Bible
    {
        return $this->bible($attributes, self::CREATIVE_COMMONS);
    }

    public function testTheYearAndOwnerAreBothIncluded(): void
    {
        $statement = $this->creativeCommonsBible(['year' => 1611, 'owner' => 'Example Society'])->getCopyrightStatement();

        $this->assertStringContainsString(__('basic.copyright') . ' © 1611 Example Society', $statement);
        $this->assertStringContainsString('This text is made available', $statement);
    }

    public function testOnlyAYear(): void
    {
        $statement = $this->creativeCommonsBible(['year' => 1769, 'owner' => NULL])->getCopyrightStatement();

        $this->assertStringContainsString(__('basic.copyright') . ' © 1769', $statement);
        $this->assertStringNotContainsString('1769 ', $statement);
    }

    public function testOnlyAnOwner(): void
    {
        $statement = $this->creativeCommonsBible(['year' => NULL, 'owner' => 'Example Society'])->getCopyrightStatement();

        $this->assertStringContainsString(__('basic.copyright') . ' © Example Society', $statement);
    }

    /** Neither one, and the label must not be emitted on its own. */
    public function testNeitherLeavesNoCopyrightLine(): void
    {
        $statement = $this->creativeCommonsBible(['year' => NULL, 'owner' => NULL])->getCopyrightStatement();

        $this->assertStringNotContainsString('©', $statement);
        $this->assertStringNotContainsString(__('basic.copyright'), $statement);
        $this->assertStringContainsString('This text is made available', $statement);
    }

    /** An empty string is as absent as a NULL - neither is a year or an owner. */
    public function testEmptyStringsAreTreatedAsAbsent(): void
    {
        $statement = $this->creativeCommonsBible(['year' => '', 'owner' => ''])->getCopyrightStatement();

        $this->assertStringNotContainsString('©', $statement);
    }

    // -----------------------------------------------------------------------
    // Which licence types carry the line
    // -----------------------------------------------------------------------

    /**
     * A public domain text has no copyright holder, so its statement carries no copyright
     * line however its year and owner are filled in.
     *
     * The line was briefly built for every licence type, which relabelled 43 of the installed
     * Bibles: 'asv' rendered 'Copyright (c) 1901' above 'This Bible is in the Public Domain',
     * and so did every rendered PDF and text file of it - the statement contradicted itself.
     */
    public function testAPublicDomainBibleCarriesNoCopyrightLine(): void
    {
        $statement = $this->bible(['year' => 1901, 'owner' => 'Example Society'])->getCopyrightStatement();

        $this->assertStringNotContainsString('©', $statement);
        $this->assertStringNotContainsString(__('basic.copyright'), $statement);
        $this->assertStringNotContainsString('1901', $statement);
        $this->assertStringContainsString('Public domain.', $statement);
    }

    /**
     * 'creative_commons' is the only type that carries it - that is where the licence itself
     * requires the holder to be named, and it is the one type whose statement is generated
     * rather than stored, so there is nowhere else for the attribution to have been written.
     *
     * @param string $type
     * @param bool $carries_the_line
     */
    #[DataProvider('licenceTypeDataProvider')]
    public function testOnlyCreativeCommonsCarriesTheLine(string $type, bool $carries_the_line): void
    {
        $statement = $this->bible(
            ['year' => 1611, 'owner' => 'Example Society'],
            ['type' => $type] + self::CREATIVE_COMMONS
        )->getCopyrightStatement();

        if($carries_the_line) {
            $this->assertStringContainsString('1611 Example Society', $statement, $type);
        }
        else {
            $this->assertStringNotContainsString('1611', $statement, $type);
            $this->assertStringNotContainsString('Example Society', $statement, $type);
        }
    }

    /** Every type the copyrights table uses, plus one it does not, so an unknown type is covered too. */
    public static function licenceTypeDataProvider(): array
    {
        return [
            'creative commons' => ['creative_commons', TRUE],
            'public domain'    => ['public_domain', FALSE],
            'non commercial'   => ['non_commercial', FALSE],
            'proprietary'      => ['proprietary', FALSE],
            'unknown type'     => ['other', FALSE],
        ];
    }

    // -----------------------------------------------------------------------
    // The shape of the line
    // -----------------------------------------------------------------------

    /** The label comes from the lang file, so a translated install does not emit English. */
    public function testTheLabelComesFromTheLanguageFile(): void
    {
        app('translator')->addLines(['basic.copyright' => 'Urheberrecht'], 'de');

        $original = app()->getLocale();

        try {
            app()->setLocale('de');

            $this->assertStringContainsString(
                'Urheberrecht © 1611',
                $this->creativeCommonsBible(['year' => 1611, 'owner' => NULL])->getCopyrightStatement()
            );
        }
        finally {
            app()->setLocale($original);
        }
    }

    /**
     * The licence statement is fetched raw so the year and owner can be appended before
     * anything purifies it - which means the Bible has to purify the result itself. The owner
     * is admin-supplied and is interpolated in as it stands.
     */
    public function testTheWholeStatementIsPurified(): void
    {
        $statement = $this->creativeCommonsBible([
            'year'  => 1611,
            'owner' => 'Example <script>alert(1)</script> Society<img src=x onerror=alert(1)>',
        ])->getCopyrightStatement();

        $this->assertStringContainsString('This text is made available', $statement);
        $this->assertStringContainsString('1611', $statement);
        $this->assertStringNotContainsString('<script', $statement);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $statement);
    }

    /** The statement a public domain Bible does get is purified on the same path. */
    public function testThePublicDomainStatementIsPurifiedToo(): void
    {
        $statement = $this->bible(
            ['year' => 1901, 'owner' => NULL],
            ['default_copyright_statement' => 'Public domain. <img src=x onerror=alert(1)>']
        )->getCopyrightStatement();

        $this->assertStringContainsString('Public domain.', $statement);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $statement);
    }

    /**
     * The symbol arrives as a literal '©', not as '&copy;'. The statement is written with the
     * entity and HTMLPurifier decodes it - it emits UTF-8 and escapes only the characters that
     * have to be escaped - so that is the shape a client receives.
     */
    public function testTheCopyrightSymbolIsDecoded(): void
    {
        $statement = $this->creativeCommonsBible(['year' => 1611, 'owner' => 'Example Society'])->getCopyrightStatement();

        $this->assertStringContainsString('©', $statement);
        $this->assertStringNotContainsString('&copy;', $statement);
    }

    /** The line leads; the licence text follows it. */
    public function testTheLineIsPrependedNotAppended(): void
    {
        $statement = $this->creativeCommonsBible(['year' => 1611, 'owner' => 'Example Society'])->getCopyrightStatement();

        $this->assertLessThan(
            strpos($statement, 'This text is made available'),
            strpos($statement, '1611'),
            'The copyright line should lead the licence statement'
        );
    }

    /** And the two are separated, rather than running the owner into the licence text. */
    public function testTheLineIsSeparatedFromTheLicenceText(): void
    {
        $statement = $this->creativeCommonsBible(['year' => 1611, 'owner' => 'Example Society'])->getCopyrightStatement();

        $this->assertStringContainsString('Example Society<br />This text is made available', $statement);
    }

    // -----------------------------------------------------------------------
    // Copyright::getProcessedCopyrightStatement() handed a Bible
    // -----------------------------------------------------------------------

    /**
     * The licence record answers for a Bible by asking the Bible, so a caller that still
     * passes one - the signature took a Bible before BSS-290 and takes one again - gets the
     * same statement either way rather than a licence statement with no year on it.
     */
    public function testTheLicenceRecordDelegatesBackToTheBible(): void
    {
        $Bible = $this->creativeCommonsBible(['year' => 1611, 'owner' => 'Example Society']);

        $this->assertSame(
            $Bible->getCopyrightStatement(),
            $Bible->copyrightInfo->getProcessedCopyrightStatement($Bible)
        );
    }

    /** Which holds for a licence that carries no copyright line as well. */
    public function testTheLicenceRecordDelegatesBackForAPublicDomainBibleToo(): void
    {
        $Bible = $this->bible(['year' => 1901, 'owner' => 'Example Society']);

        $this->assertSame(
            $Bible->getCopyrightStatement(),
            $Bible->copyrightInfo->getProcessedCopyrightStatement($Bible)
        );
    }

    /**
     * Which means the '[year] [owner]' placeholder never reaches a real text: it stands in
     * for values the licence record does not have, and on this path the Bible has them.
     */
    public function testTheBiblePathCarriesNoPlaceholder(): void
    {
        $Bible = $this->creativeCommonsBible(['year' => 1611, 'owner' => 'Example Society']);

        $statement = $Bible->copyrightInfo->getProcessedCopyrightStatement($Bible);

        $this->assertStringNotContainsString('[year]', $statement);
        $this->assertStringNotContainsString('[owner]', $statement);
        $this->assertStringContainsString('1611 Example Society', $statement);
    }

    /**
     * Nor does it reach one through the Bible's own accessor, which is the path every
     * response and every rendered file takes.
     */
    public function testACreativeCommonsBibleCarriesNoPlaceholder(): void
    {
        $statement = $this->creativeCommonsBible(['year' => 1611, 'owner' => 'Example Society'])->getCopyrightStatement();

        $this->assertStringNotContainsString('[year]', $statement);
        $this->assertStringNotContainsString('[owner]', $statement);
        $this->assertStringContainsString('This text is made available', $statement);
    }

    /**
     * A Bible with neither a year nor an owner still carries no placeholder - the licence
     * statement stands on its own rather than advertising two values nobody filled in.
     */
    public function testACreativeCommonsBibleWithNoYearCarriesNoPlaceholder(): void
    {
        $statement = $this->creativeCommonsBible(['year' => NULL, 'owner' => NULL])->getCopyrightStatement();

        $this->assertStringNotContainsString('[year]', $statement);
        $this->assertStringNotContainsString('[owner]', $statement);
    }

    /** A Bible with its own statement never reaches the licence record at all. */
    public function testAnOwnStatementIsUsedInsteadAndCarriesNoYear(): void
    {
        $Bible = $this->creativeCommonsBible(['year' => 1611, 'owner' => 'Example Society']);
        $Bible->setRawAttributes(['copyright_statement' => 'Used by permission.', 'copyright_id' => 7, 'year' => 1611]);

        $statement = $Bible->getCopyrightStatement();

        $this->assertSame('Used by permission.', $statement);
        $this->assertStringNotContainsString('1611', $statement);
    }
}
