<?php

namespace Tests\Support;

use App\Models\VolumeTypes\Strongs;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Throwaway Strong's dictionaries for the strongs and query action tests.
 *
 * Every dictionary made here is uninstalled and deleted again by removeStrongsDictionaries(),
 * which the using test calls from tearDown().
 *
 * Nothing here touches the global default dictionary: every reader and writer of that flag
 * lives in StrongsApiTest::testGlobalDefaultAndNoDictionary(), so they cannot race when the
 * suite runs in parallel.
 */
trait StrongsDictionaryFixtures
{
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

    protected function removeStrongsDictionaries(): void
    {
        foreach($this->dictionaries as $Volume) {
            $Volume->content()->uninstall();
            $Volume->forceDelete();
        }

        $this->dictionaries = [];
    }

    protected function api(string $path, array $query): TestResponse
    {
        $response = $this->getJson($path . '?' . http_build_query($query));

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        return $response;
    }
}
