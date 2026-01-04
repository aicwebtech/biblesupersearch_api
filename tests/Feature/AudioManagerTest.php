<?php

namespace Tests\Feature;
use Tests\TestCase;
use App\AudioManager;
use PHPUnit\Framework\MockObject\MockObject;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;

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

    #[DataProvider('parseFilenameAutoDataProvider')]
    public function testParseFilenameAuto($filename, $expected)
    {
        $this->parseFilenameHelper($filename, $expected, true);
    }

    public static function parseFilenameAutoDataProvider()
    {
        return [
            'simple_verse_match_1' => [
                'filename' => '03_002_001.mp3',
                'expected' => [
                    'type' => 'verse',
                    'book' => 3,
                    'chapter' => 2,
                    'verse' => 1,
                ],
            ],
            'simple_verse_match_2' => [
                'filename' => 'verse_40_005_010.mp3',
                'expected' => [
                    'type' => 'verse',
                    'book' => 40,
                    'chapter' => 5,
                    'verse' => 10,
                ],
            ],
            'complex_verse_match_1' => [
                'filename' => 'bible_003_Leviticus_012_025_extra.mp3',
                'expected' => [
                    'type' => 'verse',
                    'book' => 3,
                    'chapter' => 12,
                    'verse' => 25,
                ],
            ],
            'complex_verse_match_1' => [
                'filename' => 'bible_20_Proverbs_12_03_extra.mp3',
                'expected' => [
                    'type' => 'verse',
                    'book' => 20,
                    'chapter' => 12,
                    'verse' => 3,
                ],
            ],
            'simple_chapter_match' => [
                'filename' => '05_010.mp3',
                'expected' => [
                    'type' => 'chapter',
                    'book' => 5,
                    'chapter' => 10,
                    'verse' => null,
                ],
            ],
            'simple_chapter_match_leading_zeros' => [
                'filename' => '001_002.mp3',
                'expected' => [
                    'type' => 'chapter',
                    'book' => 1,
                    'chapter' => 2,
                    'verse' => null,
                ],
            ],
            'complex_chapter_match' => [
                'filename' => 'bible_022_SongOfSolomon_003.mp3',
                'expected' => [
                    'type' => 'chapter',
                    'book' => 22,
                    'chapter' => 3,
                    'verse' => null,
                ],
            ],
            'complex_chapter_match_2' => [
                'filename' => '55_II_Timothy_03.mp3',
                'expected' => [
                    'type' => 'chapter',
                    'book' => 55,
                    'chapter' => 3,
                    'verse' => null,
                ],
            ],
            'custom_chapter_match_1' => [
                'filename' => 'C02-01-GEN-03.mp3',
                // the expected here is is not the desired result
                // Since this auto-detect checkds for verse first and this has 3 numbers
                // it will be interpreted as verse

                // Auto-detect cannot currently handle this format properly ... 
                
                'expected' => [
                    'type' => 'verse',
                    'book' => 2,
                    'chapter' => 1,
                    'verse' => 3,
                ],
            ],
            'no_match' => [
                'filename' => 'randomfile.mp3',
                'expected' => null,
            ],
            'no_match_not_enough_digits' => [
                'filename' => '1_2_3.mp3',
                'expected' => null,
            ],
            'no_match_non_numeric' => [
                'filename' => 'Genesis_One_Two_Three.mp3',
                'expected' => null,
            ],
            'no_match_invalid_book' => [
                'filename' => '67_100_1.mp3',
                'expected' => null,
            ],
            'no_match_invalid_book_2' => [
                'filename' => '5089b273-048d-4c68-9a7f-26b88c04f85a.mp3',
                'expected' => null,
            ],
            'no_match_invalid_chapter_1' => [
                'filename' => '01_51.mp3',
                'expected' => null,
            ],
            'no_match_invalid_chapter_2' => [
                'filename' => '04_85.mp3',
                'expected' => null,
            ],
            'chapter_no_verse' => [
                'filename' => '01_002.mp3',
                'expected' => [
                    'type' => 'chapter',
                    'book' => 1,
                    'chapter' => 2,
                    'verse' => null,
                ],
            ],
        ];
    }

    #[DataProvider('parseFilenameChapterCustomDataProvider')]
    public function testParseFilenameChapterCustomMatch($filename, $expected)
    {
        $this->parseFilenameHelper($filename, $expected, 'chapter_custom_lv');
    }

    public static function parseFilenameChapterCustomDataProvider()
    {
        return [
            'custom_chapter_match_1' => [
                'filename' => 'C02-01-GEN-03.mp3',
                'expected' => [
                    'type' => 'chapter',
                    'book' => 1,
                    'chapter' => 3,
                    'verse' => null,
                ],
            ],
            'custom_chapter_match_2' => [
                'filename' => 'C02-19-PSA-01.mp3',
                'expected' => [
                    'type' => 'chapter',
                    'book' => 19,
                    'chapter' => 1,
                    'verse' => null,
                ],
            ],
            'custom_chapter_match_3' => [
                'filename' => 'C02-19-PSA-100.mp3',
                'expected' => [
                    'type' => 'chapter',
                    'book' => 19,
                    'chapter' => 100,
                    'verse' => null,
                ],
            ],
            'custom_chapter_match_4' => [
                'filename' => 'randomprefix_C02-40-MAT-28_randomsuffix.mp3',
                'expected' => [
                    'type' => 'chapter',
                    'book' => 40,
                    'chapter' => 28,
                    'verse' => null,
                ],
            ],
            'custom_chapter_match_5' => [
                'filename' => 'bible_003_Leviticus_012_025_extra.mp3',
                'expected' => [
                    'type' => 'chapter',
                    'book' => 12,
                    'chapter' => 25,
                    'verse' => null,
                ]
            ],
        ];
    }

    #[DataProvider('parseFilenameChapterDataProvider')]
    public function testParseFilenameChapterMatch($filename, $expected)
    {
        $this->parseFilenameHelper($filename, $expected, 'chapter');
    }

    public static function parseFilenameChapterDataProvider()
    {
        return [
            'simple_chapter_match' => [
                'filename' => '05_010.mp3',
                'expected' => [
                    'type' => 'chapter',
                    'book' => 5,
                    'chapter' => 10,
                    'verse' => null,
                ],
            ],
            'simple_chapter_match_leading_zeros' => [
                'filename' => '001_002.mp3',
                'expected' => [
                    'type' => 'chapter',
                    'book' => 1,
                    'chapter' => 2,
                    'verse' => null,
                ],
            ],
            'complex_chapter_match' => [
                'filename' => 'bible_022_SongOfSolomon_003.mp3',
                'expected' => [
                    'type' => 'chapter',
                    'book' => 22,
                    'chapter' => 3,
                    'verse' => null,
                ],
            ],
            'no_match_not_enough_digits' => [
                'filename' => '1.mp3',
                'expected' => null,
            ],
            'mismatch_verse_instead_1' => [
                'filename' => '01_002_003.mp3',
                'expected' => [
                    'type' => 'chapter',
                    'book' => 2,
                    'chapter' => 3,
                    'verse' => null,
                ]
            ],
            'mismatch_verse_instead_2' => [
                'filename' => '01_02_03.mp3',
                'expected' => [
                    'type' => 'chapter',
                    'book' => 2,
                    'chapter' => 3,
                    'verse' => null,
                ]
            ],
        ];
    }
    
    protected function parseFilenameHelper($filename, $expected, $matchType)
    {
        $result = AudioManager::parseFilenameVerse($filename, $matchType);
        
        if($expected === null) {
            $this->assertNull($result);
            return;
        } else {
            $this->assertIsArray($result);
            $this->assertEquals($expected, $result);
            return;
        }
    }
}
