<?php

namespace Tests\Feature\TextToSpeech;
use Tests\TestCase;
use App\Models\Bible;
use App\TextToSpeech\Elevenlabs;
use App\TextToSpeech\MurfAI;
use App\TextToSpeech\Narakeet;
use App\TextToSpeech\OpenAI;
use App\TextToSpeech\TtsAbstract;
use Illuminate\Support\Facades\Config;

class TtsAbstractTest extends TestCase
{
    protected $module = 'testmodule';
    protected $basePath;
    protected $modulePath;

    public function setUp(): void
    {
        parent::setUp();

        // Ensure a default voice exists so validateGenerateAudio passes
        Config::set('text_to_speech.narakeet.voice', 'brian');

        $this->basePath = TtsAbstract::getAudioBasePath();
        $this->modulePath = $this->basePath . $this->module . DIRECTORY_SEPARATOR;
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Minimal concrete implementation for testing abstract behavior.
     */
    public static function makeConcrete()
    {
        return new class extends TtsAbstract 
        {
            public static $label = 'Test TTS';
            public static $url = 'https://example.test';
            public static $voice_url = 'https://example.test/voices';
            public static $requires_voice = true;

            public function __construct()
            {
                // Bypass parent constructor: provide a minimal Bible-like object
                $this->Bible = (object)[
                    'module' => 'testmodule',
                    'lang_short' => 'en',
                ];
                $this->file_extension = 'mp3';
            }

            public function formatPublic(string $text)
            {
                return $this->_formatText($text);
            }

            protected function generateAudioHelper($text, $options, $file_handle)
            {
                // write the text to the provided file handle and return success
                fwrite($file_handle, $text);
                return true;
            }
        };
    }

    public function testGetMetaReturnsExpectedKeys()
    {
        $meta = (static::makeConcrete())::getMeta();

        $this->assertIsArray($meta);
        $this->assertArrayHasKey('name', $meta);
        $this->assertArrayHasKey('url', $meta);
        $this->assertArrayHasKey('voice_url', $meta);
        $this->assertArrayHasKey('requires_voice', $meta);

        $this->assertEquals('Test TTS', $meta['name']);
        $this->assertEquals('https://example.test', $meta['url']);
        $this->assertEquals('https://example.test/voices', $meta['voice_url']);
        $this->assertTrue($meta['requires_voice']);
    }

    public function testFormatTextStripsMarkersAndNormalizesWhitespace()
    {
        $concrete = static::makeConcrete();

        $input = "<p>Hello {STRONG} world[/] [bracket] ‹red› ¶ {123} } { extra</p>\n\nMore   spaces!";
        $expected = "Hello world bracket red extra More spaces!";

        $out = $concrete->formatPublic($input);

        $this->assertEquals($expected, $out);
    }

    public function testGetVoiceByLanguageUsesConfigAndFallsBackToDefault()
    {
        // ensure specific lang override is used when present
        Config::set('lang.en.text_to_speech.test_tts_class.voice', 'alice');
        $voice = (static::makeConcrete())::getVoiceByLanguage('en', 'test_tts_class');
        $this->assertEquals('alice', $voice);

        // when no lang-specific config is present, return NULL ... there is NO default TTS API if the selected API has no default voice ... 
        Config::set('lang.en.text_to_speech.test_tts_class.voice', null);
        Config::set('text_to_speech.narakeet.voice', 'brian-default');
        $voice2 = (static::makeConcrete())::getVoiceByLanguage('en', null);
        $this->assertNull($voice2);
    }

    /**
     * getApiKey() composes its config name from getIdent(), so every provider reads its own
     * credential and a renamed class silently detaches the provider from its configured key.
     */
    public function testEachProviderReadsItsOwnApiKeyFromConfig(): void
    {
        $Bible = Bible::findByModule('kjv');

        $this->assertNotNull($Bible, 'the kjv module is needed to construct a provider');

        $providers = [
            'openai'     => OpenAI::class,
            'narakeet'   => Narakeet::class,
            'elevenlabs' => Elevenlabs::class,
            'murfai'     => MurfAI::class,
        ];

        foreach($providers as $ident => $class) {
            Config::set('audio.tts_api_key_' . $ident, 'sentinel-' . $ident);
        }

        foreach($providers as $ident => $class) {
            $Tts = new $class($Bible);

            $this->assertEquals('sentinel-' . $ident, $Tts->getApiKey(), $class . ' must read audio.tts_api_key_' . $ident);
        }
    }
}
