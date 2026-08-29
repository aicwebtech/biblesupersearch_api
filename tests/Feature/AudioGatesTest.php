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
 * only the two flags are read, so nothing is persisted.
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
     * isTtsAI reports whatever the configured provider declares, so the answer follows the
     * provider rather than being hard-coded here.
     *
     * Worth flagging separately: App\TextToSpeech\OpenAI does not declare $is_ai_based, so it
     * inherits FALSE from TtsAbstract - whose own comment describes the flag as "Indicates
     * AI-based TTS (like OpenAI)". With audio.tts_api set to openai this gate therefore
     * reports not-AI. Reported rather than fixed; this ticket does not change production code.
     */
    public function testIsTtsAiFollowsTheConfiguredProvidersDeclaration(): void
    {
        foreach (AudioManager::$tts_apis as $key => $class) {
            config(['audio.tts_api' => $key]);

            $this->assertSame(
                $class::getMeta()['is_ai_based'],
                AudioManager::isTtsAI(),
                "isTtsAI should mirror {$class}'s declaration"
            );
        }
    }

    public function testNarakeetIsReportedAsAiBased(): void
    {
        config(['audio.tts_api' => 'narakeet']);

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
