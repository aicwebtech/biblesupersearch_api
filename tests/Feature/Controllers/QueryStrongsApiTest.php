<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use Tests\Support\StrongsDictionaryFixtures;
use App\Models\Bible;
use App\Models\Copyright;
use App\Models\VolumeAttr;
use App\Models\VolumeTypes\Strongs;
use Illuminate\Testing\TestResponse;

/**
 * The query action's Strong's definitions: 'strongs_version', 'strongs_dictionary' and 'language'
 * resolve them the same way the strongs action's 'version', 'dictionary' and 'language' do.
 *
 * Cases that read or change the global default dictionary are in
 * StrongsApiTest::testGlobalDefaultAndNoDictionary(); see StrongsResolveTest for why.
 */
class QueryStrongsApiTest extends TestCase
{
    use StrongsDictionaryFixtures;

    private const LANGUAGE = 'qqq';

    private const QUERY = ['bible' => 'kjv_strongs', 'search' => 'H1234', 'page_limit' => 1];

    public function tearDown(): void
    {
        $this->removeStrongsDictionaries();
        $this->removeLanguageFixture(self::LANGUAGE);

        parent::tearDown();
    }

    /**
     * A throwaway Bible holding one verse that tags H1234, as kjv_strongs does: a test that
     * gives it a default dictionary leaves the installed Bibles' settings alone.
     *
     * Deleted with a query, which skips the model's deleted hook, so its settings are removed
     * here too.
     */
    protected function makeQueryBibleFixture(): Bible
    {
        $module = $this->fixtureModule('test_bible_query_strongs_');

        $this->deferCleanup(function() use ($module) {
            $this->removeBibleModuleFixture($module);
            VolumeAttr::unsetAll(VolumeAttr::TYPE_BIBLE, $module);
        });

        $Bible = new Bible();
        $Bible->module       = $module;
        $Bible->name         = 'Query Strongs Fixture ' . $module;
        $Bible->shortname    = 'QryStr ' . $module;
        $Bible->year         = '2000';
        $Bible->lang_short   = 'en';
        $Bible->copyright_id = (int) Copyright::value('id');
        $Bible->save();

        // Returns nothing: the flag and the errors tell
        $Bible->install(TRUE);
        $this->assertFalse($Bible->hasErrors(), implode('; ', $Bible->getErrors()));
        $this->assertSame(1, (int) $Bible->installed);

        \DB::table('verses_' . $module)->insert([
            'book' => 1, 'chapter' => 7, 'verse' => 11, 'chapter_verse' => 7011,
            'text' => 'the fountains{H4599} of the great{H7227} deep{H8415} broken up{H1234}',
        ]);

        $Bible->enabled = 1;
        $Bible->save();

        return $Bible;
    }

    /**
     * A successful query whose Strong's definitions are in the legacy format.
     */
    protected function assertLegacyStrongs(TestResponse $response): void
    {
        $response->assertStatus(200);
        $this->assertSame(0, $response['error_level'], implode(' ', $response['errors']));
        $this->assertSame('H1234', $response['strongs'][0]['number']);
        $this->assertArrayHasKey('entry', $response['strongs'][0]);
        $this->assertArrayNotHasKey('dictionary', $response['strongs'][0]);
    }

    /**
     * A successful query whose Strong's definitions came from $module.
     */
    protected function assertModernStrongs(TestResponse $response, string $module): void
    {
        $response->assertStatus(200);
        $this->assertSame(0, $response['error_level'], implode(' ', $response['errors']));
        $this->assertSame('H1234', $response['strongs'][0]['number']);
        $this->assertSame($module, $response['strongs'][0]['dictionary']);
    }

    public function testV2DefaultIsLegacy(): void
    {
        $this->assertLegacyStrongs($this->api('/api', self::QUERY));
        $this->assertLegacyStrongs($this->api('/api', self::QUERY + ['strongs_version' => 'legacy']));
    }

    /** 'strongs_dictionary' and 'language' apply to legacy as to modern: an unknown language is ignored. */
    public function testLegacyHonoursDictionaryAndLanguage(): void
    {
        $this->assertLegacyStrongs($this->api('/api', self::QUERY + ['language' => 'xx']));

        $response = $this->api('/api', self::QUERY + ['strongs_dictionary' => 'no_such_dictionary']);
        $this->assertSame(4, $response['error_level']);
        $this->assertStringContainsString('no_such_dictionary', implode(' ', $response['errors']));
    }

    public function testV2CanAskForModern(): void
    {
        $Volume = $this->makeDictionary();

        $response = $this->api('/api', self::QUERY + ['strongs_version' => 'modern', 'strongs_dictionary' => $Volume->module]);

        $this->assertModernStrongs($response, $Volume->module);
        $this->assertSame('to <i>cleave</i>', $response['strongs'][0]['definition']);
    }

    public function testV3ExplicitDictionary(): void
    {
        $Volume = $this->makeDictionary();

        $response = $this->api('/api/v3', self::QUERY + ['strongs_dictionary' => $Volume->module]);

        $this->assertModernStrongs($response, $Volume->module);
        $this->assertSame('to *cleave*', $response['strongs'][0]['definition']);
    }

    public function testV3CanAskForLegacy(): void
    {
        $response = $this->api('/api/v3', self::QUERY + ['strongs_version' => 'legacy']);

        $this->assertLegacyStrongs($response);
        $this->assertStringContainsString('*cleave*', $response['strongs'][0]['entry']);
    }

    public function testLanguageDefault(): void
    {
        $Language = $this->makeDictionary();
        $Explicit = $this->makeDictionary();

        $this->createLanguageFixture(self::LANGUAGE, 'Query Strongs Fixture Language')
            ->setAttr(Strongs::LANGUAGE_ATTR, $Language->module);

        $this->assertModernStrongs($this->api('/api/v3', self::QUERY + ['language' => self::LANGUAGE]), $Language->module);

        // An explicit dictionary beats the language default
        $this->assertModernStrongs(
            $this->api('/api/v3', self::QUERY + ['language' => self::LANGUAGE, 'strongs_dictionary' => $Explicit->module]),
            $Explicit->module
        );
    }

    /**
     * strongs_use_bible: the primary Bible's default dictionary comes before the interface
     * language's.  Off (the default), the Bible's is ignored; an explicit dictionary still wins.
     */
    public function testStrongsUseBible(): void
    {
        $Fixture  = $this->makeQueryBibleFixture();
        $Bible    = $this->makeDictionary();
        $Language = $this->makeDictionary();
        $Explicit = $this->makeDictionary();

        $this->createLanguageFixture(self::LANGUAGE, 'Query Strongs Fixture Language')
            ->setAttr(Strongs::LANGUAGE_ATTR, $Language->module);

        VolumeAttr::setAttr(VolumeAttr::TYPE_BIBLE, $Fixture->module, Strongs::BIBLE_ATTR, $Bible->module);

        $query = ['bible' => $Fixture->module] + self::QUERY + ['language' => self::LANGUAGE];

        $this->assertModernStrongs($this->api('/api/v3', $query + ['strongs_use_bible' => 1]), $Bible->module);
        $this->assertModernStrongs($this->api('/api/v3', $query), $Language->module);
        $this->assertModernStrongs($this->api('/api/v3', $query + ['strongs_use_bible' => 0]), $Language->module);

        $this->assertModernStrongs(
            $this->api('/api/v3', $query + ['strongs_use_bible' => 1, 'strongs_dictionary' => $Explicit->module]),
            $Explicit->module
        );
    }

    /**
     * With several Bibles, strongs_use_bible reads the first one requested, whichever order
     * they come in
     */
    public function testStrongsUseBibleReadsTheFirstBibleRequested(): void
    {
        $First       = $this->makeQueryBibleFixture();
        $Second      = $this->makeQueryBibleFixture();
        $FirstDict   = $this->makeDictionary();
        $SecondDict  = $this->makeDictionary();

        VolumeAttr::setAttr(VolumeAttr::TYPE_BIBLE, $First->module, Strongs::BIBLE_ATTR, $FirstDict->module);
        VolumeAttr::setAttr(VolumeAttr::TYPE_BIBLE, $Second->module, Strongs::BIBLE_ATTR, $SecondDict->module);

        $query = ['strongs_use_bible' => 1] + self::QUERY;

        $this->assertModernStrongs($this->api('/api/v3', ['bible' => [$First->module, $Second->module]] + $query), $FirstDict->module);
        $this->assertModernStrongs($this->api('/api/v3', ['bible' => json_encode([$Second->module, $First->module])] + $query), $SecondDict->module);
    }

    /**
     * The Engine is a singleton: a request whose Bibles all fail must not keep the previous
     * request's primary Bible
     */
    public function testAFailedBibleListClearsThePrimaryBible(): void
    {
        $Engine  = new \App\Engine();
        $primary = new \ReflectionProperty(\App\Engine::class, 'Bible_Primary');

        $Engine->setBibles(['kjv_strongs']);
        $this->assertSame('kjv_strongs', $primary->getValue($Engine)?->module);

        $Engine->setBibles(['no_such_bible']);
        $this->assertNull($primary->getValue($Engine));
    }

    /** An explicit dictionary that cannot be used fails the query, whatever defaults exist. */
    public function testUnavailableDictionaryIsFatal(): void
    {
        $Disabled = $this->makeDictionary(FALSE);
        $Language = $this->makeDictionary();

        $this->createLanguageFixture(self::LANGUAGE, 'Query Strongs Fixture Language')
            ->setAttr(Strongs::LANGUAGE_ATTR, $Language->module);

        foreach([$Disabled->module, 'no_such_dictionary'] as $module) {
            foreach(['/api/v3' => [], '/api' => ['strongs_version' => 'modern']] as $path => $extra) {
                $response = $this->api($path, self::QUERY + ['strongs_dictionary' => $module, 'language' => self::LANGUAGE] + $extra);

                $this->assertSame(4, $response['error_level']);
                $this->assertStringContainsString(
                    __('errors.strongs_dictionary_unavailable', ['dictionary' => $module]),
                    implode(' ', $response['errors'])
                );
                $this->assertEmpty($response['results'] ?? []);
            }
        }
    }

    public function testUnsupportedVersionFallsBackWithANotice(): void
    {
        $response = $this->api('/api', self::QUERY + ['strongs_version' => 'v4']);

        $this->assertSame(3, $response['error_level']);
        $this->assertStringContainsString('v4', implode(' ', $response['errors']));
        $this->assertArrayHasKey('entry', $response['strongs'][0]);
        $this->assertNotEmpty($response['results']);
    }

    /** A search without Strong's numbers resolves nothing, so a bad dictionary goes unnoticed. */
    public function testNoStrongsNumbersNoLookup(): void
    {
        $response = $this->api('/api/v3', ['bible' => 'kjv_strongs', 'search' => 'faith', 'page_limit' => 1, 'strongs_dictionary' => 'no_such_dictionary', 'strongs_version' => 'v4']);

        $response->assertStatus(200);
        $this->assertSame(0, $response['error_level'], implode(' ', $response['errors']));
        $this->assertSame([], $response['strongs']);
    }

    /**
     * Numbers are normalized as in the strongs action: any case, padding zeros dropped.
     *
     * Only the definitions are asserted: the verse search itself does not match a padded
     * number, so 'h01234' finds no verses.
     */
    public function testNumberParsing(): void
    {
        $Volume = $this->makeDictionary();

        foreach(['h1234', 'h01234', 'H01234'] as $search) {
            $legacy = $this->api('/api', ['search' => $search] + self::QUERY);
            $this->assertSame(['H1234'], array_column($legacy['strongs'], 'number'));
            $this->assertArrayHasKey('entry', $legacy['strongs'][0]);

            $modern = $this->api('/api/v3', ['search' => $search, 'strongs_dictionary' => $Volume->module] + self::QUERY);
            $this->assertSame(['H1234'], array_column($modern['strongs'], 'number'));
            $this->assertSame($Volume->module, $modern['strongs'][0]['dictionary']);
        }
    }

    /** A number the dictionary lacks is left out, without an error, as legacy always has. */
    public function testNumberNotFoundIsSilent(): void
    {
        $Volume = $this->makeDictionary();

        $response = $this->api('/api/v3', ['search' => 'H1234 H1235', 'search_type' => 'or', 'strongs_dictionary' => $Volume->module] + self::QUERY);

        $this->assertSame(0, $response['error_level'], implode(' ', $response['errors']));
        $this->assertSame(['H1234'], array_column($response['strongs'], 'number'));
    }
}
