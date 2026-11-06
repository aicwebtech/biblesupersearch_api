<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use Tests\Support\StrongsDictionaryFixtures;
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
