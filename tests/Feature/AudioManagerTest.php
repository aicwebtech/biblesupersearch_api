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
        $rp->setAccessible(true);
        $this->originalTtsApis = $rp->getValue();
        // ensure config override container exists
    }

    public function tearDown(): void
    {
        $rp = new \ReflectionProperty(AudioManager::class, 'tts_apis');
        $rp->setAccessible(true);
        $rp->setValue($this->originalTtsApis);

        parent::tearDown();
    }

    public function testRenderAudioTTSReturnsAddTransErrorWhenTtsGloballyDisabled()
    {
        // ensure static tts_apis does not interfere
        $rp = new \ReflectionProperty(AudioManager::class, 'tts_apis');
        $rp->setAccessible(true);
        $rp->setValue([]);

        Config::set('audio.tts_api_enable', false);

        $Bible = new \stdClass();
        $Bible->tts_enable = true;
        $Bible->module = 'TEST';

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
        $method->setAccessible(true);

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
        $rp->setAccessible(true);
        $rp->setValue([]);

        $Bible = new \stdClass();
        $Bible->tts_enable = true;
        $Bible->module = 'TEST';

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
        $method->setAccessible(true);

        $result = $method->invokeArgs($mgr, [$Bible, &$verse, []]);
        $this->assertSame('ERR_TTS_API', $result);
    }
}
