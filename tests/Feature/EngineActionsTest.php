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
            $queries = array_column(\DB::getQueryLog(), 'query');
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
            fn ($query) => str_contains($query, 'from `' . $prefix . 'bibles`') && str_contains($query, 'left join')
        ));

        $this->assertNotEmpty($listing, 'the listing query was not logged');
        $this->assertStringContainsString('`' . $prefix . 'bibles`.`tts_api`', $listing[0]);

        $by_language = array_filter($queries, fn ($query) => str_contains($query, 'from `' . $prefix . 'languages`'));

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
