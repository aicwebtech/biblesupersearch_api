<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\AppInstallTesting;
use App\Models\Books\BookAbstract;
use App\Features\FeatureDefinitions;
use App\Models\Bible;
use App\Models\Feature;
use App\Models\Language;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
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
    /**
     * Languages whose book lists PassageTest walks, mirroring its $map_request_languages. The
     * installer has to provide every one, or those data sets quietly lose their coverage.
     */
    private const REQUIRED_BOOK_LIST_LANGUAGES = ['ru', 'lt', 'pl', 'hi', 'zh', 'ja', 'lv'];

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
     * Every CSV in dumps/bible_books that is not a language of its own must be skipped, or the
     * command creates an orphan books_<file> table that no model class can ever resolve, and
     * (for the regional codes) flags book support the rest of the application cannot honour.
     */
    public function testBookListLanguagesExcludeNonLanguageFiles(): void
    {
        $languages = $this->bookListLanguages();

        $this->assertNotEmpty($languages);

        foreach(['template', 'art', 'zh_cn', 'zh_tw'] as $excluded) {
            // Guards against the exclusion silently going stale if the CSV is renamed.
            $this->assertFileExists(database_path('dumps/bible_books/' . $excluded . '.csv'));
            $this->assertNotContains($excluded, $languages);
        }

        // Every entry must name a real CSV, since each becomes a table and an import.
        foreach($languages as $language) {
            $this->assertFileExists(database_path('dumps/bible_books/' . $language . '.csv'));
        }
    }

    /**
     * Each language the command flags as supported has to be queryable afterwards - a book
     * model class must resolve, or the tests that walk its book names skip on the class check.
     *
     * This asserts rather than skips: CI runs app:install-testing before PHPUnit, so a missing
     * book list here means the installer did not do its job. Skipping would make the run green
     * at precisely the moment the coverage disappeared - the failure mode this whole test class
     * exists to catch.
     */
    public function testEveryRequiredLanguageResolvesABookModelClass(): void
    {
        foreach(self::REQUIRED_BOOK_LIST_LANGUAGES as $language) {
            $this->assertTrue(
                Language::hasBookSupport($language),
                "Language '{$language}' book list is not installed; run php artisan app:install-testing"
            );

            $this->assertNotFalse(
                BookAbstract::getClassNameByLanguageStrict($language),
                "No book model class resolves for '{$language}'; run php artisan app:install-testing"
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
     * A code with no row in `languages` must not be flagged as having book support. Language
     * lookups, book queries and Extras rendering all resolve book support back through
     * `languages`, so a flag written without one advertises a language that cannot be loaded.
     */
    public function testACodeWithNoLanguageRowIsNeverFlagged(): void
    {
        $code = 'qq_zz';

        $this->assertNull(Language::findByCode($code), 'Test fixture code unexpectedly exists as a language');

        $Method = new \ReflectionMethod(AppInstallTesting::class, '_setBookListSupported');
        $flagged = $Method->invoke(new AppInstallTesting(), $code);

        $this->assertFalse($flagged, 'A code with no language row was reported as flagged');
        $this->assertFalse(Language::hasBookSupport($code));
        $this->assertNotContains($code, Language::haveBookSupport());
    }

    /**
     * Every language the command flags must be resolvable through `languages`, since that is
     * how the rest of the application turns a book-support flag back into a language.
     */
    public function testEveryFlaggedLanguageResolvesThroughTheLanguagesTable(): void
    {
        foreach(Language::haveBookSupport() as $code) {
            $this->assertNotNull(
                Language::findByCode($code),
                "Language code '{$code}' is flagged as having book support but has no `languages` row"
            );
        }
    }

    public function testCommandRefusesToRunAgainstAnUninstalledApplication(): void
    {
        config(['app.installed' => FALSE]);

        $this->artisan('app:install-testing')
            ->expectsOutputToContain('Application is not installed')
            ->assertExitCode(AppInstallTesting::FAILURE);
    }

    /**
     * A partially populated database must not report success. Every install step swallows its
     * own per-item failures so one bad language or feature cannot cost the rest of the run, and
     * the suite then skips whatever is missing rather than failing on it - so the exit code is
     * the only place a gap can surface before the coverage silently disappears.
     */
    #[DataProvider('partialInstallProvider')]
    public function testHandleFailsWhenAnyStepDidNotComplete(bool $bibles, bool $books, bool $features, int $expected_code, string $expected_output): void
    {
        [$code, $output] = $this->runCommandWithStepResults($bibles, $books, $features);

        $this->assertSame($expected_code, $code);
        $this->assertStringContainsString($expected_output, $output);
    }

    /**
     * @return array<string, array{0: bool, 1: bool, 2: bool, 3: int, 4: string}>
     */
    public static function partialInstallProvider(): array
    {
        return [
            'everything installed' => [TRUE,  TRUE,  TRUE,  AppInstallTesting::SUCCESS, 'Testing data installed'],
            'Bibles incomplete'    => [FALSE, TRUE,  TRUE,  AppInstallTesting::FAILURE, 'Bibles'],
            'book lists incomplete'=> [TRUE,  FALSE, TRUE,  AppInstallTesting::FAILURE, 'book lists'],
            'features incomplete'  => [TRUE,  TRUE,  FALSE, AppInstallTesting::FAILURE, 'features'],
            'nothing installed'    => [FALSE, FALSE, FALSE, AppInstallTesting::FAILURE, 'Bibles, book lists, features'],
        ];
    }

    /**
     * Runs handle() with each install step stubbed to the given outcome, so the aggregation can
     * be exercised without the command rewriting the whole database.
     *
     * @return array{0: int, 1: string} the exit code and everything the command printed
     */
    private function runCommandWithStepResults(bool $bibles, bool $books, bool $features): array
    {
        config(['app.installed' => TRUE]);

        $Command = new class extends AppInstallTesting {
            public bool $bibles_installed = TRUE;
            public bool $book_lists_installed = TRUE;
            public bool $features_installed = TRUE;

            protected function _installBibles(): bool
            {
                return $this->bibles_installed;
            }

            protected function _installBookLists(): bool
            {
                return $this->book_lists_installed;
            }

            protected function _installFeatures(): bool
            {
                return $this->features_installed;
            }
        };

        $Command->bibles_installed     = $bibles;
        $Command->book_lists_installed = $books;
        $Command->features_installed   = $features;
        $Command->setLaravel($this->app);

        $Output = new BufferedOutput();
        $code   = $Command->run(new ArrayInput([]), $Output);

        return [$code, $Output->fetch()];
    }

    /**
     * bible:install returns NULL from every path, so _installBibles() verifies its work against
     * config('bible.testing') rather than trusting an exit code. Every module listed there has
     * to end up both installed and enabled, or the tests that use it skip themselves.
     */
    public function testEveryConfiguredTestingBibleIsInstalledAndEnabled(): void
    {
        $expected = config('bible.testing');

        $this->assertNotEmpty($expected, 'No testing Bibles are configured');

        $ready = Bible::whereIn('module', $expected)
                -> where('installed', 1)
                -> where('enabled', 1)
                -> pluck('module')
                -> all();

        // Asserted, not skipped, for the same reason as the book lists above.
        $this->assertSame(
            [],
            array_values(array_diff($expected, $ready)),
            'Testing Bibles are not installed and enabled; run php artisan app:install-testing'
        );
    }

    /**
     * createTableAndMigrateFromCsv() returns TRUE the moment the table exists, without looking
     * at its contents, so an import interrupted part way leaves a short table that still
     * resolves a class. Flagging that as supported is the silent coverage gap this command is
     * meant to close, so the row count is checked against the CSV.
     */
    public function testBookListRowCountMatchesTheCsvForEveryRequiredLanguage(): void
    {
        $Method = new \ReflectionMethod(AppInstallTesting::class, '_countBookListRows');
        $Command = new AppInstallTesting();

        foreach(self::REQUIRED_BOOK_LIST_LANGUAGES as $language) {
            $expected = $Method->invoke($Command, $language);

            $this->assertGreaterThan(0, $expected, "No book rows are defined in the '{$language}' CSV");

            $class_name = BookAbstract::getClassNameByLanguageStrict($language);

            $this->assertNotFalse($class_name, "No book model class resolves for '{$language}'");
            $this->assertGreaterThanOrEqual(
                $expected,
                $class_name::count(),
                "The '{$language}' book table holds fewer rows than its CSV defines; run php artisan app:install-testing"
            );
        }
    }

    /**
     * The row count has to come from the CSV's own data rows - the header excluded, and any row
     * the importer would drop for having no id excluded with it.
     */
    public function testCountBookListRowsCountsOnlyImportableRows(): void
    {
        $Method = new \ReflectionMethod(AppInstallTesting::class, '_countBookListRows');
        $Command = new AppInstallTesting();

        // A standard 66-book list, so the header is not being counted.
        $this->assertEquals(66, $Method->invoke($Command, config('bss.defaults.language_short')));

        // template.csv carries ids but no names; a language with no CSV at all has nothing.
        $this->assertEquals(0, $Method->invoke($Command, 'no_such_language'));
    }

    /**
     * syncFeatures() never prunes a row whose definition was removed, and Feature::install()
     * returns FALSE for one of those. Installing every historical row would fail this command -
     * and CI with it - permanently, on any database that ever held the old feature.
     */
    public function testFeatureInstallSkipsRowsWithNoCurrentDefinition(): void
    {
        $identifiers = array_column(FeatureDefinitions::all(), 'identifier');

        $this->assertNotEmpty($identifiers);

        $stale = Feature::whereNotIn('identifier', $identifiers)->pluck('identifier')->unique()->all();

        // Every row the command will try to install must have a definition behind it.
        foreach(Feature::whereIn('identifier', $identifiers)->get() as $Feature) {
            $this->assertNotNull(
                FeatureDefinitions::find($Feature->identifier),
                "Feature '{$Feature->identifier}' would be installed but has no definition"
            );
            $this->assertNotContains($Feature->identifier, $stale);
        }
    }
}
