<?php

namespace Tests\Feature\Models;

use Tests\TestCase;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;

use App\Models\Language;

class LanguageTest extends TestCase
{
    #[DataProvider('rtlCheckDataProvider')]
    public function testRtlCheck(string|null $lang, bool $expected): void 
    {
        $this->assertSame($expected, Language::isRtl($lang));
    }

    public static function rtlCheckDataProvider(): array 
    {
        return [
            [ 'he', true ],
            [ 'ar', true ],
            [ 'en', false ],
            [ 'es', false ],
            [ 'zz', false ],
            [ 'zzz', false ],
            [ 'abcd', false ],
            [ '', false ],
            [ null, false ],
            [ 'mer-234324', false],
            ['123', false],
            ['<script>hackit()</script>', false],
            ['; DROP TABLE users; --', false],
            ['; dangerous(); ', false],
            [ 'dne', false ], // does not exist
        ];
    }

    #[DataProvider('providerValidateLanguage')]
    public function testValidateLanguage(string|null $lang, bool $expected): void
    {
        $this->assertSame($expected, Language::validateLanguage($lang));
    }

    public static function providerValidateLanguage(): array 
    {
        return [
            [ 'en', true ],
            [ 'es', true ],
            [ 'fr', true ],
            [ 'de', true ],
            [ 'zz', false ],
            [ 'zzz', false ],
            [ 'abcd', false ],
            [ '', false ],
            [ null, false ],
            [ 'mer-234324', false],
            ['123', false],
            ['<script>hackit()</script>', false],
            ['; DROP TABLE users; --', false],
            ['; dangerous(); ', false],
            [ 'dne', false ], // does not exist
        ];
    }

    /**
     * The relation is keyed on bibles.lang_short, which is where a Bible records its language
     * code - there is no 'language_code' column. Read-only against installed content data.
     *
     * Nothing in the application calls bibles() yet; every Language-to-Bible path is a manual
     * join. That is why the relation could name both a nonexistent class and a nonexistent
     * column until BSS-285 without anything failing.
     */
    public function testBiblesRelationReturnsTheLanguagesBibles(): void
    {
        $Language = Language::findByCode('en');

        $this->assertNotNull($Language, 'English should be installed');

        $bibles = $Language->bibles;

        $this->assertNotEmpty($bibles, 'English should have at least one Bible');

        foreach ($bibles as $Bible) {
            $this->assertInstanceOf(\App\Models\Bible::class, $Bible);
            $this->assertSame('en', $Bible->lang_short);
        }
    }

    /**
     * A language with no Bibles returns an empty collection rather than failing - the fixture
     * has no rows in bibles, so this also proves the relation filters rather than returning
     * everything.
     */
    public function testBiblesRelationIsEmptyForALanguageWithNoBibles(): void
    {
        $code = 'qqz';

        try {
            $Language = $this->createLanguageFixture($code, 'Bibles Relation Test');

            $this->assertCount(0, $Language->bibles);
        }
        finally {
            $this->removeLanguageFixture($code);
        }
    }

    /**
     * denitLanguage() drops the language's book tables, so it must drop the 'book_list'
     * attribute with them - that pairing is what initLanguage() establishes. A language left
     * advertising book support it can no longer back resolves to no model class in
     * Engine::actionBooks() and ExtrasAbstract::_renderBibleBookLists().
     */
    public function testDenitLanguageClearsBookSupport(): void
    {
        $code = 'qqy';

        try {
            $Language = $this->createLanguageFixture($code, 'Denit Test');
            $Language->setAttr('book_list', 1);

            $this->assertTrue(Language::hasBookSupport($code), 'Fixture was not flagged to begin with');

            $Language->denitLanguage();

            $this->assertFalse(Language::hasBookSupport($code));
            $this->assertNotContains($code, Language::haveBookSupport());
        }
        finally {
            // Every write is inside the try, so an assertion failure - or a throw between the
            // two writes - cannot leave the fixture behind in the shared test database.
            $this->removeLanguageFixture($code);
        }
    }
}
