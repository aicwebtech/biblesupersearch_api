<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\AppInstallTesting;
use App\Models\Books\BookAbstract;
use App\Models\Language;
use App\Models\LanguageAttr;
use Tests\TestCase;

/**
 * Covers app:install-testing, the single command CI runs to fill every gap the PHPUnit suite
 * skips itself over: the testing Bibles, every book list, and every feature.
 *
 * The command itself is not executed here - it rewrites the whole database - so these tests
 * pin down the parts that decide *what* it installs, plus its refusal to run against an
 * uninstalled application.
 */
class AppInstallTestingTest extends TestCase
{
    /** Languages whose book lists PassageTest walks; each one skips itself when unsupported. */
    private const REQUIRED_BOOK_LIST_LANGUAGES = ['ru', 'lt', 'pl', 'hi', 'zh_tw', 'ja'];

    private function bookListLanguages(): array
    {
        $Method = new \ReflectionMethod(AppInstallTesting::class, '_getBookListLanguages');

        return $Method->invoke(new AppInstallTesting());
    }

    public function testCommandIsRegistered(): void
    {
        $commands = \Artisan::all();

        $this->assertArrayHasKey('app:install-testing', $commands);
    }

    /**
     * The languages PassageTest needs must all be offered by the installer, or those data sets
     * go on silently skipping.
     */
    public function testBookListLanguagesCoverEveryLanguageTheSuiteNeeds(): void
    {
        $languages = $this->bookListLanguages();

        foreach(self::REQUIRED_BOOK_LIST_LANGUAGES as $language) {
            $this->assertContains($language, $languages, "app:install-testing would not install the '{$language}' book list");
        }

        // The default language's book list backs most of the suite.
        $this->assertContains(config('bss.defaults.language_short'), $languages);
    }

    /**
     * template.csv ships as a starting point for new translations, not as a language, so it
     * must not become a books_template table.
     */
    public function testBookListLanguagesExcludeTheTemplate(): void
    {
        $languages = $this->bookListLanguages();

        $this->assertNotContains('template', $languages);
        $this->assertNotEmpty($languages);

        // Every entry must name a real CSV, since each becomes a table and an import.
        foreach($languages as $language) {
            $this->assertFileExists(database_path('dumps/bible_books/' . $language . '.csv'));
        }
    }

    /**
     * Each language the command flags as supported has to be queryable afterwards - a book
     * model class must resolve, or the tests skip on the class check instead.
     */
    public function testEveryRequiredLanguageResolvesABookModelClass(): void
    {
        foreach(self::REQUIRED_BOOK_LIST_LANGUAGES as $language) {
            if(!Language::hasBookSupport($language)) {
                $this->markTestSkipped("Language '{$language}' book list not installed; run php artisan app:install-testing");
            }

            $this->assertNotFalse(
                BookAbstract::getClassNameByLanguageStrict($language),
                "No book model class resolves for '{$language}'"
            );
        }
    }

    /**
     * Cross references back RequestTest::testCrossReferencesAreAggregatedAcrossReturnedVerses,
     * which skips itself when the feature is not enabled.
     */
    public function testCrossReferencesFeatureIsDefined(): void
    {
        $identifiers = array_column(\App\Features\FeatureDefinitions::all(), 'identifier');

        $this->assertContains('cross_references', $identifiers);
    }

    /**
     * language_attr.code was varchar(3), so MySQL silently truncated the regional codes the
     * application ships book lists for: 'zh_cn' and 'zh_tw' both became 'zh_', colliding on the
     * ica unique key, and hasBookSupport('zh_tw') could never match the truncated row. SQLite
     * ignores varchar widths, so only MySQL ever showed it.
     */
    public function testBookListSupportSurvivesARegionalLanguageCode(): void
    {
        $code = 'qq_zz';

        LanguageAttr::where('code', $code)->delete();

        $Method = new \ReflectionMethod(AppInstallTesting::class, '_setBookListSupported');
        $Method->invoke(new AppInstallTesting(), $code);

        try {
            $stored = LanguageAttr::where('code', $code)->where('attribute', 'book_list')->first();

            $this->assertNotNull($stored, 'Regional language code was not stored');
            $this->assertEquals($code, $stored->code, 'Regional language code was truncated on write');
            $this->assertTrue(Language::hasBookSupport($code));
        }
        finally {
            LanguageAttr::where('code', $code)->delete();
        }
    }

    /**
     * Writing one regional code must not collide with another sharing its first three
     * characters - the exact failure the truncation caused.
     */
    public function testTwoRegionalCodesWithTheSamePrefixCoexist(): void
    {
        $codes = ['qq_aa', 'qq_bb'];

        LanguageAttr::whereIn('code', $codes)->delete();

        $Method = new \ReflectionMethod(AppInstallTesting::class, '_setBookListSupported');
        $Command = new AppInstallTesting();

        try {
            foreach($codes as $code) {
                $Method->invoke($Command, $code);
            }

            $this->assertEquals(2, LanguageAttr::whereIn('code', $codes)->where('attribute', 'book_list')->count());
        }
        finally {
            LanguageAttr::whereIn('code', $codes)->delete();
        }
    }

    public function testCommandRefusesToRunAgainstAnUninstalledApplication(): void
    {
        config(['app.installed' => FALSE]);

        $this->artisan('app:install-testing')
            ->expectsOutputToContain('Application is not installed')
            ->assertExitCode(AppInstallTesting::FAILURE);
    }
}
