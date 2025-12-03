<?php

namespace Tests\Feature\TextToSpeech;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\TextToSpeech\Narakeet;
use App\Models\Language;
use Illuminate\Support\Facades\Config;


class NarakeetTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Provide a predictable API key used by the service under test.
        config()->set('services.narakeet.key', 'test-api-key');
    }

    public function testVoiceByLanguage()
    {
        $languages = Language::select('languages.code', 'languages.name')
                    // join on bible only if bible is official
                    ->join('bibles', function ($join) {
                        $join->on('bibles.lang_short', '=', 'languages.code')
                             ->where('bibles.official', '=', 1);
                    })

                    ->groupBy('languages.id')->orderBy('languages.name');

        foreach($languages->get() as $lang) {
            $voice1 = Narakeet::getVoiceByLanguage($lang->code);
            $this->assertNotNull($voice1, 'No Narakeet voice for language: ' . $lang->name . ' : ' . $lang->code);
        }
    }

    public function testSpecificLanguageVoice()
    {
        $voice = Narakeet::getVoiceByLanguage('fr');
        $this->assertEquals('guillaume', $voice, 'Unexpected Narakeet voice for French.');

        $voice = Narakeet::getVoiceByLanguage('de');
        $this->assertEquals('bruno', $voice, 'Unexpected Narakeet voice for German.');
    }

    public function testVoiceOverrideByLanguage()
    {
        $Lang = Language::findByCode('es');
        $this->assertNotNull($Lang, 'Language es not found in database.');

        $cache = $Lang->tts_voice;

        $Lang->tts_voice = 'custom_spanish_voice';
        $Lang->save();
    
        $voice = Narakeet::getVoiceByLanguage('es');
        $this->assertEquals('custom_spanish_voice', $voice, 'Voice override for Spanish did not work.');

        // Restore original voice setting
        $Lang->tts_voice = $cache;
        $Lang->save();
    }

    public function testApiDefaultLanguage()
    {
        $voice = Narakeet::getVoiceByLanguage('xx'); // assuming 'xx' is not a real language code
        $this->assertNull($voice, 'Expected no voice for unknown language code.');// by default, our Narakeet handling does NOT have a default voice ...
        $this->assertNull(config('text_to_speech.narakeet.voice'), 'Expected no default Narakeet voice in config.');

        $default_voice = 'default_narakeet_voice';
        Config::set('text_to_speech.narakeet.voice', $default_voice); 
        $this->assertEquals($default_voice, config('text_to_speech.narakeet.voice'), 'Failed to set default Narakeet voice in config.');

        $voice = Narakeet::getVoiceByLanguage('xx'); // assuming 'xx' is not a real language code
        $this->assertEquals($default_voice, $voice, 'Did not return default Narakeet voice for unknown language.');

        // Clean up config
        Config::set('text_to_speech.narakeet.voice', null);
    }
}