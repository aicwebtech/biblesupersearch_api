<?php

namespace Tests\Feature;

use App\AudioManager;
use App\Models\Bible;
use Tests\TestCase;

/**
 * audioEnabled(), ttsEnabled() and isTtsAI() are the gates every audio request passes
 * through. Each combines a global config switch with per-Bible flags, and a Bible may
 * disable audio without the global switch changing.
 *
 * They read config, so these are feature tests. The Bibles are unsaved in-memory instances -
 * only their own attributes are read, so no Bible is persisted. The one test that needs a
 * language behind a Bible creates a throwaway one and removes it again.
 */
class AudioGatesTest extends TestCase
{
    private function bible(bool $audio, bool $tts): Bible
    {
        $bible = new Bible();
        $bible->audio_enable = $audio;
        $bible->tts_enable   = $tts;

        return $bible;
    }

    // -----------------------------------------------------------------------
    // audioEnabled
    // -----------------------------------------------------------------------

    public function testAudioIsOffWhenTheGlobalSwitchIsOff(): void
    {
        config(['audio.enable' => false]);

        $this->assertFalse(AudioManager::audioEnabled());
        $this->assertFalse(AudioManager::audioEnabled($this->bible(true, true)));
    }

    public function testAudioIsOnGloballyWhenEnabledAndNoBibleIsGiven(): void
    {
        config(['audio.enable' => true]);

        $this->assertTrue(AudioManager::audioEnabled());
    }

    /**
     * A single Bible can opt out even while audio is enabled globally.
     */
    public function testABibleCanOptOutOfAudio(): void
    {
        config(['audio.enable' => true]);

        $this->assertTrue(AudioManager::audioEnabled($this->bible(true, true)));
        $this->assertFalse(AudioManager::audioEnabled($this->bible(false, true)));
    }

    // -----------------------------------------------------------------------
    // ttsEnabled
    // -----------------------------------------------------------------------

    public function testTtsIsOffWhenTheGlobalTtsSwitchIsOff(): void
    {
        config(['audio.tts_api_enable' => false]);

        $this->assertFalse(AudioManager::ttsEnabled());
        $this->assertFalse(AudioManager::ttsEnabled($this->bible(true, true)));
    }

    public function testTtsIsOnGloballyWhenEnabledAndNoBibleIsGiven(): void
    {
        config(['audio.tts_api_enable' => true]);

        $this->assertTrue(AudioManager::ttsEnabled());
    }

    /**
     * TTS needs both flags on the Bible: turning off audio altogether also turns off TTS,
     * so a Bible cannot end up generating speech it is not allowed to serve.
     */
    public function testTtsRequiresBothBibleFlags(): void
    {
        config(['audio.tts_api_enable' => true]);

        $this->assertTrue(AudioManager::ttsEnabled($this->bible(true, true)));
        $this->assertFalse(AudioManager::ttsEnabled($this->bible(true, false)));
        $this->assertFalse(AudioManager::ttsEnabled($this->bible(false, true)));
        $this->assertFalse(AudioManager::ttsEnabled($this->bible(false, false)));
    }

    // -----------------------------------------------------------------------
    // isTtsAI
    // -----------------------------------------------------------------------

    /**
     * Both registered providers are AI-based, so the gate reports true for either global
     * default. Asserted as an explicit expectation table rather than by re-deriving the value
     * from getMeta(), which would pass whatever the providers happened to declare.
     */
    public function testEachConfiguredProviderIsReportedAsAiBased(): void
    {
        foreach (['narakeet' => true, 'openai' => true] as $key => $expected) {
            config(['audio.tts_api' => $key]);

            $this->assertSame($expected, AudioManager::isTtsAI(), "provider {$key}");
        }
    }

    /**
     * The gate must resolve the provider the way the generation path does - a Bible naming its
     * own tts_api is synthesised with that provider, so the flag has to describe that provider
     * and not the global default. isTtsAI() read the global config only until BSS-285.
     */
    public function testABiblesOwnProviderOverridesTheGlobalDefault(): void
    {
        config(['audio.tts_api' => 'narakeet']);

        $bible = $this->bible(true, true);
        $bible->tts_api = 'openai';

        $this->assertSame(
            'openai',
            AudioManager::resolveTtsApiName($bible),
            'the Bible\'s own provider should win over the global default'
        );
        $this->assertTrue(AudioManager::isTtsAI($bible));
    }

    /**
     * Below the Bible's own provider sits its language's, and below that the global default.
     * The language is read through the Bible's language relation, so a caller listing many
     * Bibles can eager load it instead of paying a query per Bible.
     */
    public function testTheLanguagesProviderAppliesWhenTheBibleNamesNone(): void
    {
        config(['audio.tts_api' => 'narakeet']);

        $code = 'qqx';

        try {
            $Language = $this->createLanguageFixture($code, 'Tts Provider Test');

            $bible = $this->bible(true, true);
            $bible->lang_short = $code;

            $this->assertSame(
                'narakeet',
                AudioManager::resolveTtsApiName($bible),
                'a language naming no provider leaves the global default in place'
            );

            $Language->tts_api = 'openai';
            $Language->save();

            // A fresh instance: the relation is cached on the model it was read through.
            $bible = $this->bible(true, true);
            $bible->lang_short = $code;

            $this->assertSame('openai', AudioManager::resolveTtsApiName($bible));

            $bible->tts_api = 'narakeet';

            $this->assertSame(
                'narakeet',
                AudioManager::resolveTtsApiName($bible),
                'the Bible\'s own provider should win over its language\'s'
            );

            // Eager loading is what keeps a listing of many Bibles from paying a languages query
            // apiece: with the relation already loaded, resolving must not go back to the
            // database. It called Language::findByCode() regardless until BSS-285.
            $bible = $this->bible(true, true);
            $bible->lang_short = $code;
            $bible->setRelation('language', $Language);

            \DB::flushQueryLog();
            \DB::enableQueryLog();

            try {
                $this->assertSame('openai', AudioManager::resolveTtsApiName($bible));
                $this->assertSame([], \DB::getQueryLog(), 'an eager loaded language was re-queried');
            }
            finally {
                \DB::disableQueryLog();
                \DB::flushQueryLog();
            }
        }
        finally {
            $this->removeLanguageFixture($code);
        }
    }

    /**
     * An unrecognised provider on the Bible must not fatal, and must not silently fall back to
     * the global default - the gate reports not-AI because the class cannot be resolved.
     */
    public function testABibleNamingAnUnknownProviderIsReportedAsNotAi(): void
    {
        config(['audio.tts_api' => 'narakeet']);

        $bible = $this->bible(true, true);
        $bible->tts_api = 'no_such_provider';

        $this->assertFalse(AudioManager::isTtsAI($bible));
    }

    public function testTheGlobalDefaultAppliesWhenNoBibleIsGiven(): void
    {
        config(['audio.tts_api' => 'narakeet']);

        $this->assertSame('narakeet', AudioManager::resolveTtsApiName());
        $this->assertTrue(AudioManager::isTtsAI());
    }

    /**
     * A Bible with audio or TTS off is never AI-based, whatever the provider is.
     */
    public function testABibleWithTtsOffIsNeverReportedAsAi(): void
    {
        config(['audio.tts_api' => 'narakeet']);

        $this->assertFalse(AudioManager::isTtsAI($this->bible(true, false)));
        $this->assertFalse(AudioManager::isTtsAI($this->bible(false, true)));
    }

    /**
     * An unrecognised provider must not fatal on a missing class - the gate simply reports
     * not-AI.
     */
    public function testAnUnknownProviderIsReportedAsNotAi(): void
    {
        config(['audio.tts_api' => 'no_such_provider']);

        $this->assertFalse(AudioManager::isTtsAI());
    }

    // -----------------------------------------------------------------------
    // Provider catalogue
    // -----------------------------------------------------------------------

    /**
     * Provider keys are stored in a database column capped at 100 characters, as the comment
     * on $tts_apis notes.
     */
    public function testProviderKeysFitTheDatabaseColumn(): void
    {
        foreach (array_keys(AudioManager::$tts_apis) as $key) {
            $this->assertLessThanOrEqual(100, strlen($key));
        }
    }

    public function testEveryListedProviderResolvesToATtsClass(): void
    {
        foreach (AudioManager::getTtsApisList() as $entry) {
            $this->assertArrayHasKey('key', $entry);
            $this->assertArrayHasKey($entry['key'], AudioManager::$tts_apis);
            $this->assertTrue(is_subclass_of(
                AudioManager::$tts_apis[$entry['key']],
                \App\TextToSpeech\TtsAbstract::class
            ));
        }
    }
}
