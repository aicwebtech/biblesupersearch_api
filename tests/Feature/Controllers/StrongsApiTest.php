<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use Tests\Support\StrongsDictionaryFixtures;
use App\Engines\EngineV3;
use App\Models\VolumeTypes\Strongs;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * /api/strongs: the 'version', 'language' and 'dictionary' parameters (BSS-152 phase 3).
 *
 * Legacy is the strongs_definitions table, modern the Strong's dictionary volumes.  v2 defaults
 * to legacy, so its responses are unchanged; v3 defaults to modern.  Asking for the version that
 * is not the default falls back to the default when the requested one cannot be resolved;
 * there is no other fallback.
 *
 * Only testGlobalDefaultAndNoDictionary() reads or changes the global Strong's default; see
 * StrongsResolveTest for why.  That includes the query action's global default cases, which
 * is why they are tested there rather than in QueryStrongsApiTest.
 */
class StrongsApiTest extends TestCase
{
    use StrongsDictionaryFixtures;

    private const LANGUAGE = 'qqs';

    private const MODERN_FIELDS = [
        'id', 'number', 'root_word', 'pronunciation', 'transliteration',
        'definition', 'short_definition', 'is_special', 'dictionary',
    ];

    private const LEGACY_FIELDS = ['id', 'number', 'root_word', 'transliteration', 'pronunciation', 'tvm', 'entry'];

    public function tearDown(): void
    {
        $this->removeStrongsDictionaries();
        $this->removeLanguageFixture(self::LANGUAGE);

        parent::tearDown();
    }

    protected function assertModern(TestResponse $response, ?string $module = null): void
    {
        $response->assertStatus(200);
        $this->assertSame(0, $response['error_level']);
        $this->assertEqualsCanonicalizing(self::MODERN_FIELDS, array_keys($response['results'][0]));
        
        if($module !== null) {
            $this->assertSame($module, $response['results'][0]['dictionary']);
        }
    }

    protected function assertLegacy(TestResponse $response): void
    {
        $response->assertStatus(200);
        $this->assertSame(0, $response['error_level']);
        $this->assertEqualsCanonicalizing(self::LEGACY_FIELDS, array_keys($response['results'][0]));
        $this->assertSame(1234, $response['results'][0]['id']);
    }

    protected function assertFatal(TestResponse $response, string $message): void
    {
        $this->assertSame(4, $response['error_level']);
        $this->assertStringContainsString($message, implode(' ', $response['errors']));
    }

    /**
     * A query response's error level, and which dictionary its Strong's definitions came from.
     * With no $module and a non-zero level, no definitions are expected.
     */
    protected function assertQueryStrongs(TestResponse $response, int $error_level, ?string $module = null): TestResponse
    {
        // Any error, fatal or not, answers 400
        $response->assertStatus($error_level ? 400 : 200);
        $this->assertSame($error_level, $response['error_level'], implode(' ', $response['errors']));

        if($module !== null) {
            $this->assertSame($module, $response['strongs'][0]['dictionary']);
        }
        elseif($error_level) {
            $this->assertSame([], $response['strongs']);
        }

        return $response;
    }

    /**
     * An Engine whose legacy table reports itself missing.  strongs_definitions is content data,
     * so the table itself is never touched.
     */
    protected function engineWithoutLegacy(): EngineV3
    {
        return new class extends EngineV3 {
            protected function _legacyStrongsAvailable(): bool
            {
                return FALSE;
            }
        };
    }

    /** v3 asking for legacy, with legacy missing, falls back to modern, silently. */
    public function testV3LegacyFallsBackToModern(): void
    {
        $Volume = $this->makeDictionary();
        $Engine = $this->engineWithoutLegacy();

        $results = $Engine->actionStrongs(['strongs' => 'H1234', 'version' => 'legacy', 'dictionary' => $Volume->module]);

        $this->assertFalse($Engine->hasErrors());
        $this->assertSame($Volume->module, $results[0]['dictionary']);

        $Engine->actionQuery([
            'bible' => 'kjv_strongs', 'search' => 'H1234', 'page_limit' => 1,
            'strongs_version' => 'legacy', 'strongs_dictionary' => $Volume->module,
        ]);

        $this->assertFalse($Engine->hasErrors());
        $this->assertSame($Volume->module, $Engine->getMetadata()->strongs[0]['dictionary']);
    }

    /** After falling back, modern's own errors still apply: there is no falling back again. */
    public function testV3LegacyFallbackRaisesModernErrors(): void
    {
        $Engine = $this->engineWithoutLegacy();

        $this->assertFalse($Engine->actionStrongs(['strongs' => 'H1234', 'version' => 'legacy', 'dictionary' => 'no_such_dictionary']));
        $this->assertSame(4, $Engine->getErrorLevel());
    }

    /** With legacy present, v3 asking for legacy gets legacy, whatever dictionary is named. */
    public function testV3LegacyWithLegacyPresentIgnoresDictionary(): void
    {
        $this->assertLegacy($this->api('/api/v3/strongs', ['strongs' => 'H1234', 'version' => 'legacy', 'dictionary' => 'no_such_dictionary']));
    }

    public function testV2IsLegacyAndUnchanged(): void
    {
        $plain = $this->api('/api/strongs', ['strongs' => 'H1234']);
        $this->assertLegacy($plain);
        $this->assertStringContainsString('<i>cleave</i>', $plain['results'][0]['entry']);

        $this->assertLegacy($this->api('/api/v2/strongs', ['strongs' => 'H1234']));
        $this->assertSame($plain->json('results'), $this->api('/api/v2/strongs', ['strongs' => 'H1234'])->json('results'));
        $this->assertSame($plain->json('results'), $this->api('/api/strongs', ['strongs' => 'H1234', 'version' => 'legacy'])->json('results'));
    }

    /** 'dictionary' and 'language' only apply to modern; legacy ignores them. */
    public function testLegacyIgnoresDictionaryAndLanguage(): void
    {
        $response = $this->api('/api/strongs', ['strongs' => 'H1234', 'dictionary' => 'no_such_dictionary', 'language' => 'xx']);

        $this->assertLegacy($response);
    }

    public function testV3CanAskForLegacy(): void
    {
        $response = $this->api('/api/v3/strongs', ['strongs' => 'H1234', 'version' => 'legacy']);

        $this->assertLegacy($response);
        $this->assertStringContainsString('*cleave*', $response['results'][0]['entry']);
    }

    public function testModernIsDefaultForV3(): void
    {
        $response = $this->api('/api/v3/strongs', ['strongs' => 'H1234']);

        $this->assertModern($response, null);
        $this->assertNotNull($response['results'][0]['definition']);
    }

    public function testModernWithLanguageDefault(): void
    {
        $Language = $this->makeDictionary();
        $Explicit = $this->makeDictionary();

        $this->createLanguageFixture(self::LANGUAGE, 'Strongs API Fixture Language')
            ->setAttr(Strongs::LANGUAGE_ATTR, $Language->module);

        $this->assertModern($this->api('/api/v3/strongs', ['strongs' => 'H1234', 'language' => self::LANGUAGE]), $Language->module);

    }

    public function testModernWithLanguageDefaultAndExplicitDictionary(): void
    {
        $Language = $this->makeDictionary();
        $Explicit = $this->makeDictionary();

        $this->createLanguageFixture(self::LANGUAGE, 'Strongs API Fixture Language')
            ->setAttr(Strongs::LANGUAGE_ATTR, $Language->module);

        // An explicit dictionary beats the language default
        $this->assertModern(
            $this->api('/api/v3/strongs', ['strongs' => 'H1234', 'language' => self::LANGUAGE, 'dictionary' => $Explicit->module]),
            $Explicit->module
        );
    }

    public function testModernCanAskForLegacy(): void
    {
        $Volume = $this->makeDictionary();

        $response = $this->api('/api/v3/strongs', ['strongs' => 'H1234', 'version' => 'legacy']);

        $this->assertLegacy($response);
        $this->assertStringContainsString('*cleave*', $response['results'][0]['entry']);
    }

    public function testExplicitDictionary(): void
    {
        $Volume = $this->makeDictionary();

        $v3 = $this->api('/api/v3/strongs', ['strongs' => 'H1234', 'dictionary' => $Volume->module]);
        $this->assertModern($v3, $Volume->module);
        $this->assertSame('to *cleave*', $v3['results'][0]['definition']);
        $this->assertSame("\u{05D1}\u{05BC}\u{05E7}\u{05E2}", $v3['results'][0]['root_word']);
        $this->assertSame(0, $v3['results'][0]['is_special']);

        $v2 = $this->api('/api/strongs', ['strongs' => 'H1234', 'version' => 'modern', 'dictionary' => $Volume->module]);
        $this->assertModern($v2, $Volume->module);
        $this->assertSame('to <i>cleave</i>', $v2['results'][0]['definition']);
    }

    public function testSpecialEntries(): void
    {
        $Volume = $this->makeDictionary();

        $response = $this->api('/api/v3/strongs', ['strongs' => 'G5719', 'dictionary' => $Volume->module]);

        $this->assertModern($response, $Volume->module);
        $this->assertSame(1, $response['results'][0]['is_special']);
        $this->assertStringContainsString('**Tense:**', $response['results'][0]['definition']);
        $this->assertNull($response['results'][0]['root_word']);
    }

    /** Numbers are parsed as for legacy: any case, padding zeros dropped, any separator. */
    public function testNumberParsing(): void
    {
        $Volume = $this->makeDictionary();

        $response = $this->api('/api/v3/strongs', ['strongs' => 'h01234; g5719', 'dictionary' => $Volume->module]);

        $this->assertModern($response, $Volume->module);
        $this->assertSame(['H1234', 'G5719'], array_column($response['results'], 'number'));
    }

    public function testNumberNotFound(): void
    {
        $Volume = $this->makeDictionary();

        $response = $this->api('/api/v3/strongs', ['strongs' => 'H1234 H9999', 'dictionary' => $Volume->module]);

        $this->assertSame(['H1234'], array_column($response['results'], 'number'));
        $this->assertStringContainsString(__('errors.strongs_not_found') . ': H9999', implode(' ', $response['errors']));
    }

    public function testLanguageDefault(): void
    {
        $Language = $this->makeDictionary();
        $Explicit = $this->makeDictionary();

        $this->createLanguageFixture(self::LANGUAGE, 'Strongs API Fixture Language')
            ->setAttr(Strongs::LANGUAGE_ATTR, $Language->module);

        $this->assertModern($this->api('/api/v3/strongs', ['strongs' => 'H1234', 'language' => self::LANGUAGE]), $Language->module);

        // An explicit dictionary beats the language default
        $this->assertModern(
            $this->api('/api/v3/strongs', ['strongs' => 'H1234', 'language' => self::LANGUAGE, 'dictionary' => $Explicit->module]),
            $Explicit->module
        );
    }

    /** An explicit dictionary that cannot be used is an error, whatever defaults exist. */
    public function testUnavailableDictionaryIsAnError(): void
    {
        $Disabled = $this->makeDictionary(FALSE);
        $Language = $this->makeDictionary();

        $this->createLanguageFixture(self::LANGUAGE, 'Strongs API Fixture Language')
            ->setAttr(Strongs::LANGUAGE_ATTR, $Language->module);

        foreach([$Disabled->module, 'no_such_dictionary'] as $module) {
            foreach(['/api/v3/strongs' => [], '/api/strongs' => ['version' => 'modern']] as $path => $extra) {
                $response = $this->api($path, ['strongs' => 'H1234', 'dictionary' => $module, 'language' => self::LANGUAGE] + $extra);

                $this->assertFatal($response, __('errors.strongs_dictionary_unavailable', ['dictionary' => $module]));
                $this->assertEmpty($response['results'] ?? []);
            }
        }
    }

    public function testUnsupportedVersionFallsBackWithANotice(): void
    {
        $response = $this->api('/api/strongs', ['strongs' => 'H1234', 'version' => 'v4']);

        $this->assertSame(3, $response['error_level']);
        $this->assertStringContainsString('v4', implode(' ', $response['errors']));
        $this->assertSame(1234, $response['results'][0]['id']);
        $this->assertArrayHasKey('entry', $response['results'][0]);
    }

    /**
     * The global default, and what happens without one.  The one test that changes the Strong's
     * default; whatever was the default before is restored.
     */
    public function testGlobalDefaultAndNoDictionary(): void
    {
        $original = DB::table('volumes')->where('type', 'strongs')->where('is_default', 1)->pluck('id')->all();

        try {
            $Default  = $this->makeDictionary();
            $Disabled = $this->makeDictionary(FALSE);
            $this->assertTrue($Default->makeDefault());

            $this->assertModern($this->api('/api/v3/strongs', ['strongs' => 'H1234']), $Default->module);
            $this->assertModern($this->api('/api/strongs', ['strongs' => 'H1234', 'version' => 'modern']), $Default->module);

            // A language whose dictionary cannot be used falls to the global default
            $this->createLanguageFixture(self::LANGUAGE, 'Strongs API Fixture Language')
                ->setAttr(Strongs::LANGUAGE_ATTR, $Disabled->module);

            $this->assertModern($this->api('/api/v3/strongs', ['strongs' => 'H1234', 'language' => self::LANGUAGE]), $Default->module);
            $this->assertSame($Default->id, Strongs::resolveDefault(self::LANGUAGE)?->id);

            // The query action resolves the same way
            $query = ['bible' => 'kjv_strongs', 'search' => 'H1234', 'page_limit' => 1];
            $this->assertQueryStrongs($this->api('/api/v3', $query), 0, $Default->module);
            $this->assertQueryStrongs($this->api('/api', $query + ['strongs_version' => 'modern']), 0, $Default->module);
            $this->assertQueryStrongs($this->api('/api/v3', $query + ['language' => self::LANGUAGE]), 0, $Default->module);

            // No default at all
            DB::table('volumes')->where('type', 'strongs')->update(['is_default' => 0]);
            $this->assertNull(Strongs::resolveDefault(NULL));

            // Modern as the default (v3) is an error, never legacy
            $this->assertFatal($this->api('/api/v3/strongs', ['strongs' => 'H1234']), __('errors.strongs_no_dictionary'));

            // Modern asked for on v2 falls back to legacy, silently
            $this->assertLegacy($this->api('/api/strongs', ['strongs' => 'H1234', 'version' => 'modern']));

            // Query: a non-fatal error, search results still returned, no definitions
            $response = $this->assertQueryStrongs($this->api('/api/v3', $query), 3);
            $this->assertStringContainsString(__('errors.strongs_no_dictionary'), implode(' ', $response['errors']));
            $this->assertNotEmpty($response['results']);

            $response = $this->assertQueryStrongs($this->api('/api', $query + ['strongs_version' => 'modern']), 0);
            $this->assertSame('H1234', $response['strongs'][0]['number']);
            $this->assertArrayHasKey('entry', $response['strongs'][0]);

            // Legacy is unaffected
            $this->assertLegacy($this->api('/api/v3/strongs', ['strongs' => 'H1234', 'version' => 'legacy']));
            $this->assertLegacy($this->api('/api/strongs', ['strongs' => 'H1234']));
        }
        finally {
            DB::table('volumes')->where('type', 'strongs')->update(['is_default' => 0]);
            DB::table('volumes')->whereIn('id', $original)->update(['is_default' => 1]);
        }
    }
}
