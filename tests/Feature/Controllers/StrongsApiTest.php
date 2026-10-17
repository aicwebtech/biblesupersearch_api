<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Models\VolumeTypes\Strongs;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * /api/strongs: the 'version', 'language' and 'dictionary' parameters (BSS-152 phase 3).
 *
 * Legacy is the strongs_definitions table, modern the Strong's dictionary volumes.  v2 defaults
 * to legacy, so its responses are unchanged; v3 defaults to modern.  Modern never falls back to
 * legacy.
 *
 * Only testGlobalDefaultAndNoDictionary() reads or changes the global Strong's default; see
 * StrongsResolveTest for why.
 */
class StrongsApiTest extends TestCase
{
    private const LANGUAGE = 'qqs';

    private const MODERN_FIELDS = [
        'id', 'number', 'root_word', 'pronunciation', 'transliteration',
        'definition', 'short_definition', 'is_special', 'dictionary',
    ];

    private const LEGACY_FIELDS = ['id', 'number', 'root_word', 'transliteration', 'pronunciation', 'tvm', 'entry'];

    /** @var Strongs[] */
    private array $dictionaries = [];

    /**
     * An installed dictionary holding H1234 and G5719.  Which dictionary answered shows in the
     * response's 'dictionary' field.
     */
    protected function makeDictionary(bool $enabled = TRUE): Strongs
    {
        $suffix = bin2hex(random_bytes(3));

        $Volume = new Strongs();
        $Volume->module    = 'vol_api_' . $suffix;
        $Volume->name      = 'Strongs API Fixture ' . $suffix;
        $Volume->shortname = 'StrApi ' . $suffix;
        $Volume->language  = 'en';
        $Volume->save();
        $Volume->install(FALSE, $enabled);

        DB::table($Volume->content()->getTable())->insert([
            [
                'number' => 'H1234', 'root_word' => "\u{05D1}\u{05BC}\u{05E7}\u{05E2}", 'transliteration' => 'ba\u{0302}qa\u{0303}',
                'pronunciation' => 'baw-kah\'', 'definition' => 'to <i>cleave</i>', 'is_special' => 0,
            ],
            [
                'number' => 'G5719', 'root_word' => NULL, 'transliteration' => NULL,
                'pronunciation' => NULL, 'definition' => '<b>Tense:</b> Present <br>', 'is_special' => 1,
            ],
        ]);

        return $this->dictionaries[] = $Volume;
    }

    public function tearDown(): void
    {
        foreach($this->dictionaries as $Volume) {
            $Volume->content()->uninstall();
            $Volume->forceDelete();
        }

        $this->dictionaries = [];
        $this->removeLanguageFixture(self::LANGUAGE);

        parent::tearDown();
    }

    protected function api(string $path, array $query): TestResponse
    {
        $response = $this->getJson($path . '?' . http_build_query($query));

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        return $response;
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

            // No default at all: modern is an error, never legacy
            DB::table('volumes')->where('type', 'strongs')->update(['is_default' => 0]);

            $this->assertNull(Strongs::resolveDefault(NULL));
            $this->assertFatal($this->api('/api/v3/strongs', ['strongs' => 'H1234']), __('errors.strongs_no_dictionary'));
            $this->assertFatal($this->api('/api/strongs', ['strongs' => 'H1234', 'version' => 'modern']), __('errors.strongs_no_dictionary'));

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
