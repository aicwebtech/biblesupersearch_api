<?php

namespace Tests\Feature;
use Tests\TestCase;
use App\AudioManager;
use PHPUnit\Framework\MockObject\MockObject;
use Illuminate\Support\Facades\Config;

class AudioManagerTest extends TestCase
{
    /** @var array */
    protected $originalTtsApis;

    public function setUp(): void
    {
        parent::setUp();
        
        $rp = new \ReflectionProperty(AudioManager::class, 'tts_apis');
        $this->originalTtsApis = $rp->getValue();
        // ensure config override container exists
    }

    public function tearDown(): void
    {
        $rp = new \ReflectionProperty(AudioManager::class, 'tts_apis');
        $rp->setValue(null, $this->originalTtsApis);

        parent::tearDown();
    }

    public function testRenderAudioTTSReturnsAddTransErrorWhenTtsGloballyDisabled()
    {
        // ensure static tts_apis does not interfere
        $rp = new \ReflectionProperty(AudioManager::class, 'tts_apis');
        $rp->setValue(null, []);

        Config::set('audio.tts_api_enable', false);

        $Bible = new \stdClass();
        $Bible->audio_enable = true;
        $Bible->tts_enable = true;
        $Bible->module = 'TEST';
        $Bible->lang_short = 'en';

        $verse = new \stdClass();
        $verse->book = 1;
        $verse->chapter = 1;
        $verse->verse = 1;
        $verse->text = 'In the beginning...';

        /** @var AudioManager|MockObject $mgr */
        $mgr = $this->getMockBuilder(AudioManager::class)
                    ->onlyMethods(['addTransError'])
                    ->getMock();

        $mgr->expects($this->once())
            ->method('addTransError')
            ->with('errors.audio.no_tts')
            ->willReturn('ERR_TTS_DISABLED');

        $method = new \ReflectionMethod(AudioManager::class, 'renderAudioTTS');

        $result = $method->invokeArgs($mgr, [$Bible, &$verse, []]);
        $this->assertSame('ERR_TTS_DISABLED', $result);
    }

    public function testRenderAudioTTSReturnsErrorWhenTtsApiNotSupported()
    {
        // make sure TTS enabled globally and for bible
        Config::set('audio.tts_api_enable', true);
        Config::set('audio.tts_api', 'nonexistent_api');

        // ensure no supported apis are registered
        $rp = new \ReflectionProperty(AudioManager::class, 'tts_apis');
        $rp->setValue(null, []);

        $Bible = new \stdClass();
        $Bible->audio_enable = true;
        $Bible->tts_enable = true;
        $Bible->module = 'TEST';
        $Bible->lang_short = 'en';

        $verse = new \stdClass();
        $verse->book = 1;
        $verse->chapter = 1;
        $verse->verse = 1;
        $verse->text = 'In the beginning...';

        /** @var AudioManager|MockObject $mgr */
        $mgr = $this->getMockBuilder(AudioManager::class)
                    ->onlyMethods(['addError'])
                    ->getMock();

        $mgr->expects($this->once())
            ->method('addError')
            ->with('TTS API NOT supported: ' . 'nonexistent_api')
            ->willReturn('ERR_TTS_API');

        $method = new \ReflectionMethod(AudioManager::class, 'renderAudioTTS');

        $result = $method->invokeArgs($mgr, [$Bible, &$verse, []]);
        $this->assertSame('ERR_TTS_API', $result);
    }

    public function testRenderAudioTTSReturnsErrorWhenNoVoiceForLanguage()
    {
        // make sure TTS enabled globally and for bible
        Config::set('audio.tts_api_enable', true);
        Config::set('audio.tts_api', 'narakeet');

        $Bible = new \App\Models\Bible();
        $Bible->audio_enable = true;
        $Bible->tts_enable = true;
        $Bible->module = 'TEST';
        $Bible->lang_short = 'xx'; // fake language with no voices

        $verse = new \stdClass();
        $verse->book = 1;
        $verse->chapter = 1;
        $verse->verse = 1;
        $verse->text = 'In the beginning...';

        /** @var AudioManager|MockObject $mgr */
        $mgr = $this->getMockBuilder(AudioManager::class)
                    ->onlyMethods(['addTransError'])
                    ->getMock();

        // This mock isn't working ... 
        // $mgr->expects($this->once())
        //     ->method('addTransError')
        //     ->with('errors.audio.no_tts_voice', ['api' => 'Narakeet', 'language' => 'xx'])
        //     ->willReturn('ERR_NO_VOICE');

        $method = new \ReflectionMethod(AudioManager::class, 'renderAudioTTS');

        // Checking actual error message instead of using mock return
        $trans_error = trans('errors.audio.no_tts_voice', ['api' => 'Narakeet', 'language' => 'xx']);

        $result = $method->invokeArgs($mgr, [$Bible, &$verse, []]);
        
        $this->assertFalse($result);
        $this->assertTrue($mgr->hasErrors());

        $this->assertSame($trans_error, $mgr->getErrors()[0]);
    }

    public function testAudioGenerateWhenBibleHasTtsDisabled()
    {
        $Bible = new \App\Models\Bible();
        $Bible->audio_enable = true;
        $Bible->tts_enable = false; // TTS disabled for this bible
        $Bible->module = 'TEST';
        $Bible->lang_short = 'en';

        $verse = new \stdClass();
        $verse->book = 1;
        $verse->chapter = 1;
        $verse->verse = 1;
        $verse->text = 'In the beginning...';

        /** @var AudioManager|MockObject $mgr */
        $mgr = $this->getMockBuilder(AudioManager::class)
                    ->onlyMethods(['addTransError'])
                    ->getMock();

        $mgr->expects($this->once())
            ->method('addTransError')
            ->with('errors.audio.no_tts_bible')
            ->willReturn('ERR_BIBLE_TTS_DISABLED');

        $method = new \ReflectionMethod(AudioManager::class, 'renderAudioTTS');

        $result = $method->invokeArgs($mgr, [$Bible, &$verse, []]);
        $this->assertSame('ERR_BIBLE_TTS_DISABLED', $result);
    }

    public function testAudioGenerateWhenBibleHasAudioDisabled()
    {
        $Bible = new \App\Models\Bible();
        $Bible->audio_enable = false; // Audio disabled for this bible
        $Bible->tts_enable = true; // ignored when audio disabled ... 
        $Bible->module = 'TEST';
        $Bible->lang_short = 'en';

        $verse = new \stdClass();
        $verse->book = 1;
        $verse->chapter = 1;
        $verse->verse = 1;
        $verse->text = 'In the beginning...';

        /** @var AudioManager|MockObject $mgr */
        $mgr = $this->getMockBuilder(AudioManager::class)
                    ->onlyMethods(['addTransError'])
                    ->getMock();

        $mgr->expects($this->once())
            ->method('addTransError')
            ->with('errors.audio.bible_no_audio')
            ->willReturn('ERR_BIBLE_AUDIO_DISABLED');

        $method = new \ReflectionMethod(AudioManager::class, 'renderAudioTTS');

        $result = $method->invokeArgs($mgr, [$Bible, &$verse, []]);
        $this->assertSame('ERR_BIBLE_AUDIO_DISABLED', $result);
    }

    public function testGetAudioWhenBibleHasAudioDisabled()
    {
        $AudioManager = new AudioManager();
        
        $Passage = new \App\Passage();
        $Passage->setBook(1);
        $Passage->setChapterVerse('1');

        $Bible = new \App\Models\Bible();
        $Bible->audio_enable = false; // Audio disabled for this bible
        $Bible->tts_enable = true; // ignored when audio disabled ...
        $Bible->module = 'TEST';
        $Bible->lang_short = 'en';

        $input = [
            'bible' => $Bible->module,
            'book' => 1,
            'chapter_verse' => '1',
        ];

        $result = $AudioManager->getAudio($Passage, $Bible, $input, 'check');

        $this->assertTrue($AudioManager->hasErrors());
        $this->assertEquals(trans('errors.audio.bible_no_audio', ['module' => $Bible->module]), $AudioManager->getErrors()[0]);

    }
}
