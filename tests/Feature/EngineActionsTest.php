<?php

namespace Tests\Feature;

use App\Engine;
use App\Models\Cache;
use App\RenderManager;
use Tests\TestCase;

/**
 * Covers the Engine actions the suite never reached: the two that delegate to a catalogue,
 * the shortcut lookup and its language fallback, the statics-changed timestamps, and the
 * cache read with its two failure paths.
 *
 * All of these are read-only against installed content. The one exception is the cache-read
 * success case, which creates a throwaway cache row and removes it in a finally.
 */
class EngineActionsTest extends TestCase
{
    private function engine(): Engine
    {
        return Engine::getInstance();
    }

    // -----------------------------------------------------------------------
    // Delegating actions
    // -----------------------------------------------------------------------

    public function testDownloadListMatchesTheRendererCatalogue(): void
    {
        $this->assertSame(RenderManager::getRendererList(), $this->engine()->actionDownloadlist([]));
    }

    public function testRequirementsReturnsTheInstallChecklist(): void
    {
        $requirements = $this->engine()->actionRequirements([]);

        $this->assertArrayHasKey('php_version', $requirements);
        $this->assertArrayHasKey('php_extensions_required', $requirements);
    }

    // -----------------------------------------------------------------------
    // Shortcuts
    // -----------------------------------------------------------------------

    public function testShortcutsAreReturnedForTheDefaultLanguage(): void
    {
        $shortcuts = $this->engine()->actionShortcuts([]);

        $this->assertNotEmpty($shortcuts);

        $attributes = $shortcuts[0]->getAttributes();

        $this->assertArrayHasKey('name', $attributes);
        $this->assertArrayHasKey('reference', $attributes);
        $this->assertNotEmpty($shortcuts[0]->name);
    }

    /**
     * Only shortcuts flagged for display are published.
     */
    public function testHiddenShortcutsAreNotReturned(): void
    {
        foreach ($this->engine()->actionShortcuts([]) as $shortcut) {
            $this->assertSame(1, (int) $shortcut->display);
        }
    }

    /**
     * A language with no shortcut class falls back to the default rather than failing - the
     * API is called with arbitrary language codes.
     */
    public function testAnUnknownLanguageFallsBackToTheDefault(): void
    {
        $fallback = $this->engine()->actionShortcuts(['language' => 'no_such_language']);

        $this->assertEquals($this->engine()->actionShortcuts([]), $fallback);
    }

    public function testShortcutsAreOrderedById(): void
    {
        $ids = array_map(fn ($s) => $s->id, $this->engine()->actionShortcuts([]));
        $sorted = $ids;
        sort($sorted);

        $this->assertSame($sorted, $ids);
    }

    // -----------------------------------------------------------------------
    // Statics-changed timestamps
    // -----------------------------------------------------------------------

    /**
     * Clients poll this to decide whether to refetch the static data, so every component
     * timestamp must be present and the summary must be the newest of them.
     */
    public function testStaticsChangedReportsEveryComponentTimestamp(): void
    {
        $response = $this->engine()->actionStaticsChanged([]);

        $this->assertTrue($response->success);
        $this->assertObjectHasProperty('bible', $response->dates);
        $this->assertObjectHasProperty('shortcuts', $response->dates);
        $this->assertObjectHasProperty('configs', $response->dates);
    }

    public function testStaticsChangedSummarisesTheNewestTimestamp(): void
    {
        $response = $this->engine()->actionStaticsChanged([]);

        $this->assertSame(max((array) $response->dates), $response->updated);
    }

    // -----------------------------------------------------------------------
    // Bible listing
    // -----------------------------------------------------------------------

    /**
     * The listing's tts_ai flag is resolved per Bible - from the Bible's own provider first,
     * then its language's - so the listing query has to carry bibles.tts_api and eager load the
     * language.
     *
     * A column left out of a select resolves to null on the model without complaint, so the
     * per-Bible override was invisible here and every row fell through to its language, one
     * query at a time. Asserted against the query log because no installed Bible names a
     * provider of its own, which is exactly why the omission went unnoticed.
     */
    public function testTheBibleListingCarriesTheColumnsItsTtsFlagsAreResolvedFrom(): void
    {
        \DB::flushQueryLog();
        \DB::enableQueryLog();

        try {
            $bibles  = $this->engine()->actionBibles([]);

            // Identifier quoting is the grammar's business - backticks on MySQL, double quotes
            // on SQLite - so it is stripped before matching rather than assumed.
            $queries = array_map(
                fn (string $query): string => str_replace(['`', '"'], '', $query),
                array_column(\DB::getQueryLog(), 'query')
            );
        }
        finally {
            \DB::disableQueryLog();
            \DB::flushQueryLog();
        }

        $this->assertNotEmpty($bibles);

        $prefix = \DB::getTablePrefix();

        // The listing is the one bibles query joining languages; the engine reads single Bibles
        // by module elsewhere.
        $listing = array_values(array_filter(
            $queries,
            fn ($query) => str_contains($query, 'from ' . $prefix . 'bibles') && str_contains($query, 'left join')
        ));

        $this->assertNotEmpty($listing, 'the listing query was not logged');
        $this->assertStringContainsString($prefix . 'bibles.tts_api', $listing[0]);

        $by_language = array_filter($queries, fn ($query) => str_contains($query, 'from ' . $prefix . 'languages'));

        $this->assertLessThanOrEqual(
            1,
            count($by_language),
            'languages should be eager loaded once, not queried per Bible'
        );
    }

    /**
     * tts_api is selected to resolve tts_ai, not to be published - the response discloses that
     * a provider is AI-based, not which provider it is.
     */
    public function testTheBibleListingPublishesTheAiFlagButNotTheProvider(): void
    {
        $bibles = $this->engine()->actionBibles([]);
        $bible  = reset($bibles);

        $this->assertArrayHasKey('tts_ai', $bible);
        $this->assertArrayNotHasKey('tts_api', $bible);
        $this->assertArrayNotHasKey('id', $bible);
    }

    // -----------------------------------------------------------------------
    // Bible listing - language_float
    // -----------------------------------------------------------------------

    /**
     * The listing under an ordering that keeps language_float live.
     *
     * Explicitly 'lang_name', not the default: 'lang_native_name|rank' falls through to the
     * 'rank' case in actionBibles()'s switch, which nulls the float - see
     * testTheDefaultOrderingIgnoresTheLanguageFloat().
     *
     * @param array $input
     * @return array The listing, keyed by module
     */
    private function bibleListing(array $input = []): array
    {
        return $this->engine()->actionBibles($input + ['bible_order_by' => 'lang_name']);
    }

    /**
     * The most-installed language code, so floating it has something to move and something to
     * move it past.
     *
     * Derived from the listing rather than named, because which Bibles an install carries is
     * not the suite's to assume.
     *
     * @param array $listing
     * @return string
     */
    private function floatableLanguage(array $listing): string
    {
        $counts = array_count_values(array_column($listing, 'lang_short'));

        if(count($counts) < 2) {
            $this->markTestSkipped('language_float needs at least two installed languages');
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * BSS-290: the float used to lose every Bible it matched.
     *
     * actionBibles() moved each matching Bible into $bibles_floated and unset() it out of
     * $bibles, then returned $bibles - so the floated language was not moved to the front, it
     * was deleted from the response. Five of this install's sixteen Bibles vanished from
     * /api/bibles for any caller who asked for 'en' first.
     *
     * Nothing caught it because no test had ever passed a language_float, and under the default
     * ordering the float is nulled before it is read.
     */
    public function testTheLanguageFloatKeepsEveryBibleInTheListing(): void
    {
        $plain    = $this->bibleListing();
        $language = $this->floatableLanguage($plain);
        $floated  = $this->bibleListing(['language_float' => $language]);

        $this->assertCount(count($plain), $floated);
        $this->assertEqualsCanonicalizing(array_keys($plain), array_keys($floated));
    }

    /** And the Bibles it matched are the ones at the front of it. */
    public function testTheLanguageFloatMovesItsLanguageToTheFront(): void
    {
        $plain    = $this->bibleListing();
        $language = $this->floatableLanguage($plain);
        $floated  = $this->bibleListing(['language_float' => $language]);

        $languages = array_column($floated, 'lang_short');
        $expected  = count(array_keys($languages, $language, TRUE));

        $this->assertGreaterThan(0, $expected, 'nothing to float');

        // Contiguous, and at the head: the block is the first $expected entries and the
        // language appears nowhere after them.
        $this->assertSame(array_fill(0, $expected, $language), array_slice($languages, 0, $expected));
        $this->assertNotContains($language, array_slice($languages, $expected));
    }

    /**
     * The float reorders the listing, it does not re-sort it - the ordering the query applied
     * still holds inside the floated block and inside what follows it.
     */
    public function testTheLanguageFloatPreservesTheOrderWithinEachGroup(): void
    {
        $plain    = $this->bibleListing();
        $language = $this->floatableLanguage($plain);
        $floated  = $this->bibleListing(['language_float' => $language]);

        $matched = array_keys(array_filter($plain, fn (array $bible): bool => $bible['lang_short'] === $language));
        $rest    = array_keys(array_filter($plain, fn (array $bible): bool => $bible['lang_short'] !== $language));

        $this->assertSame(array_merge($matched, $rest), array_keys($floated));
    }

    /** A code no installed Bible carries floats nothing and loses nothing. */
    public function testAnUnmatchedLanguageFloatLeavesTheListingAlone(): void
    {
        $plain   = $this->bibleListing();
        $floated = $this->bibleListing(['language_float' => 'not-a-language-code']);

        $this->assertSame(array_keys($plain), array_keys($floated));
    }

    /**
     * Three of the orderings null the float on their way through the switch - a listing sorted
     * by rank or by name is not one a language block can be lifted out of without contradicting
     * the sort the caller asked for.
     */
    public function testTheOrderingsThatDiscardTheLanguageFloatReturnTheSameListing(): void
    {
        $language = $this->floatableLanguage($this->bibleListing());

        foreach(['rank', 'name', 'shortname'] as $order_by) {
            $plain   = $this->engine()->actionBibles(['bible_order_by' => $order_by]);
            $floated = $this->engine()->actionBibles(['bible_order_by' => $order_by, 'language_float' => $language]);

            $this->assertSame(array_keys($plain), array_keys($floated), $order_by);
        }
    }

    /**
     * Including the default ordering, which ends in 'rank' - so a caller who sends a
     * language_float and no bible_order_by is answered as though they had not sent one.
     *
     * Pinning what the code does today, not endorsing it: the float is undocumented and its
     * being silently dropped under the default ordering is the reason the bug above survived.
     */
    public function testTheDefaultOrderingIgnoresTheLanguageFloat(): void
    {
        $plain    = $this->engine()->actionBibles([]);
        $language = $this->floatableLanguage($plain);
        $floated  = $this->engine()->actionBibles(['language_float' => $language]);

        $this->assertSame(array_keys($plain), array_keys($floated));
    }

    // -----------------------------------------------------------------------
    // Cache read
    // -----------------------------------------------------------------------

    public function testReadingACacheWithoutAHashIsAnError(): void
    {
        $engine = $this->engine();
        $engine->resetErrors();

        $this->assertNull($engine->actionReadcache([]));
        $this->assertTrue($engine->hasErrors());
    }

    public function testReadingAMissingCacheIsAnError(): void
    {
        $engine = $this->engine();
        $engine->resetErrors();

        $this->assertNull($engine->actionReadcache(['hash' => 'no_such_cache_hash']));
        $this->assertTrue($engine->hasErrors());
    }

    /**
     * The stored form data is JSON in the column but must come back decoded, since callers
     * replay it as request input.
     */
    public function testAStoredCacheIsReturnedWithItsFormDataDecoded(): void
    {
        $hash = 'bss284testcache';

        Cache::where('hash', $hash)->delete();

        $cache = Cache::create([
            'hash'      => $hash,
            'hash_long' => $hash . '_long',
            'form_data' => json_encode(['bible' => 'kjv', 'reference' => 'John 3:16']),
            'preserve'  => 0,
        ]);

        try {
            $engine = $this->engine();
            $engine->resetErrors();

            $read = $engine->actionReadcache(['hash' => $hash]);

            $this->assertFalse($engine->hasErrors());
            $this->assertSame($hash, $read['hash']);
            $this->assertSame(['bible' => 'kjv', 'reference' => 'John 3:16'], $read['form_data']);
        } finally {
            Cache::where('hash', $hash)->delete();
        }
    }
}
