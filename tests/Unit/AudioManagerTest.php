<?php

namespace 
{
    if (!function_exists('config')) {
        // this does NOT work
        // somehow, the helpers are loaded even though the Laravel app is not bootstrapped
        // so, the config method is already defined by the time we get here
        function config($key, $default = null) {
            $overrides = $GLOBALS['_config_overrides'] ?? [];
            return array_key_exists($key, $overrides) ? $overrides[$key] : $default;
        }
    }
}

namespace App\TextToSpeech 
{
    class TestTtsA {
        public static function getMeta() {
            return ['name' => 'Tts A', 'vendor' => 'A'];
        }
    }

    class TestTtsB {
        public static function getMeta() {
            return ['name' => 'Tts B', 'vendor' => 'B'];
        }
    }
}

namespace Tests\Unit
{
    use PHPUnit\Framework\TestCase;
    use App\AudioManager;
    use PHPUnit\Framework\MockObject\MockObject;
    use PHPUnit\Framework\Attributes\DataProvider;

    class AudioManagerTest extends TestCase
    {
        /** @var array */
        protected $originalTtsApis;

        protected function setUp(): void
        {
            $rp = new \ReflectionProperty(AudioManager::class, 'tts_apis');
            $this->originalTtsApis = $rp->getValue();
            // ensure config override container exists
            $GLOBALS['_config_overrides'] = [
                'bible.total_books' => 66,
            ];
        }

        protected function tearDown(): void
        {
            $rp = new \ReflectionProperty(AudioManager::class, 'tts_apis');
            $rp->setValue(null, $this->originalTtsApis);
            unset($GLOBALS['_config_overrides']);
        }

        public function testGetTtsApisListReturnsMetaWithKey()
        {
            $rp = new \ReflectionProperty(AudioManager::class, 'tts_apis');
            $rp->setValue(null, [
                'a' => \App\TextToSpeech\TestTtsA::class,
                'b' => \App\TextToSpeech\TestTtsB::class,
            ]);

            $list = AudioManager::getTtsApisList();

            $this->assertIsArray($list);
            $this->assertCount(2, $list);

            $keys = array_column($list, 'key');
            sort($keys);
            $this->assertEquals(['a', 'b'], $keys);

            // check meta preserved
            $names = array_column($list, 'name', 'key');
            $this->assertEquals('Tts A', $names['a']);
            $this->assertEquals('Tts B', $names['b']);
        }

        public function testGetTtsApiClassesReturnsStaticArray()
        {
            $expected = ['x' => 'SomeClass', 'y' => 'OtherClass'];
            $rp = new \ReflectionProperty(AudioManager::class, 'tts_apis');
            $rp->setValue(null, $expected);

            $got = AudioManager::getTtsApiClasses();
            $this->assertSame($expected, $got);
        }

        public function testCheckAndDownloadCallGetAudioByInputWithCorrectModes()
        {
            /** @var AudioManager|MockObject $mgr */
            $mgr = $this->getMockBuilder(AudioManager::class)
                        ->onlyMethods(['getAudioByInput'])
                        ->getMock();

            $input = ['book' => '1', 'chapter_verse' => '1:1', 'bible' => 'KJV'];

            $mgr->expects($this->exactly(1))
                ->method('getAudioByInput')
                ->with($input, 'generate', null)
                ->willReturn('GEN_CALLED');

            $this->assertSame('GEN_CALLED', $mgr->checkAudioByInput($input));

            $mgr2 = $this->getMockBuilder(AudioManager::class)
                         ->onlyMethods(['getAudioByInput'])
                         ->getMock();

            $mgr2->expects($this->exactly(1))
                 ->method('getAudioByInput')
                 ->with($input, 'get', null)
                 ->willReturn('GET_CALLED');

            $this->assertSame('GET_CALLED', $mgr2->downloadAudioByInput($input));
        }

        public function testGetAudioReturnsAddTransErrorWhenBibleHasNoAudio()
        {
            // create a bible-like object with audio disabled
            $Bible = new \stdClass();
            $Bible->audio_enable = false;
            $Bible->module = 'TEST';

            /** @var AudioManager|MockObject $mgr */
            $mgr = $this->getMockBuilder(AudioManager::class)
                        ->onlyMethods(['addTransError'])
                        ->getMock();

            $mgr->expects($this->once())
                ->method('addTransError')
                ->with('errors.audio.bible_no_audio', ['module' => 'TEST'], 4)
                ->willReturn('ERR_NO_AUDIO');

            $passage = new \stdClass(); // dummy passage, not inspected for this branch

            $result = $mgr->getAudio($passage, $Bible, [], null);
            $this->assertSame('ERR_NO_AUDIO', $result);
        }

 
    }
}