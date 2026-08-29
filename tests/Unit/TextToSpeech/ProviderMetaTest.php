<?php

namespace Tests\Unit\TextToSpeech;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\TextToSpeech\Elevenlabs;
use App\TextToSpeech\MurfAI;
use App\TextToSpeech\Narakeet;
use App\TextToSpeech\OpenAI;
use App\TextToSpeech\TtsAbstract;

/**
 * Every TTS provider advertises itself through getIdent() and getMeta(). Both read only
 * static declarations on the class, so they resolve with no application booted.
 *
 * getIdent() in particular is load-bearing: getApiKey() builds the config key
 * 'audio.tts_api_key_' . getIdent(), so a change in the class short name silently detaches a
 * provider from its configured credentials. That lookup needs config(), so it is asserted in
 * Tests\Feature\TextToSpeech\TtsAbstractTest instead.
 */
class ProviderMetaTest extends TestCase
{
    /**
     * @return array<string, array{class-string<TtsAbstract>, string}>
     */
    public static function providerIdentProvider(): array
    {
        return [
            'OpenAI'     => [OpenAI::class, 'openai'],
            'Narakeet'   => [Narakeet::class, 'narakeet'],
            'Elevenlabs' => [Elevenlabs::class, 'elevenlabs'],
            'MurfAI'     => [MurfAI::class, 'murfai'],
        ];
    }

    #[DataProvider('providerIdentProvider')]
    public function testIdentIsTheLowercasedClassName(string $class, string $expected): void
    {
        $this->assertSame($expected, $class::getIdent());
    }

    #[DataProvider('providerIdentProvider')]
    public function testMetaExposesTheKeysTheAdminUiReads(string $class): void
    {
        $meta = $class::getMeta();

        $this->assertArrayHasKey('name', $meta);
        $this->assertArrayHasKey('url', $meta);
        $this->assertArrayHasKey('voice_url', $meta);
        $this->assertArrayHasKey('requires_voice', $meta);
        $this->assertArrayHasKey('is_ai_based', $meta);
    }

    public function testNarakeetDeclaresItselfAiBasedAndVoiceRequiring(): void
    {
        $meta = Narakeet::getMeta();

        $this->assertSame('Narakeet', $meta['name']);
        $this->assertTrue($meta['is_ai_based']);
        $this->assertTrue($meta['requires_voice']);
    }

    public function testOpenAiIsLabelled(): void
    {
        $this->assertSame('OpenAI', OpenAI::getMeta()['name']);
    }

    /**
     * Voice selection is required by default, so a provider that does not opt out inherits
     * the stricter behaviour.
     */
    public function testVoiceIsRequiredByDefault(): void
    {
        $this->assertTrue(OpenAI::getMeta()['requires_voice']);
    }

    public function testAudioBasePathIsSharedByEveryProvider(): void
    {
        $this->assertSame(TtsAbstract::getAudioBasePath(), Narakeet::getAudioBasePath());
        $this->assertStringEndsWith('/bibles/audio/', OpenAI::getAudioBasePath());
    }

    /**
     * A module's audio directory is the base path plus the module name. The relative form
     * drops the base path and never touches the filesystem, which is what the API returns to
     * clients.
     */
    public function testStaticAudioPathAppendsTheModule(): void
    {
        $this->assertSame(
            TtsAbstract::getAudioBasePath() . 'kjv',
            TtsAbstract::getAudioFilePathStatic('kjv')
        );
    }

    public function testRelativeStaticAudioPathIsJustTheModule(): void
    {
        $this->assertSame('kjv', TtsAbstract::getAudioFilePathStatic('kjv', false, true));
    }
}
