<?php

namespace 
{
    if (!function_exists('config')) {
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
            $GLOBALS['_config_overrides'] = [];
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
                // 'chapter_no_verse' => [
                //     'filename' => '01_002.mp3',
                //     'expected' => null,
                // ],
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
                // 'custom_chapter_no_match' => [
                //     'filename' => 'bible_003_Leviticus_012_025_extra.mp3',
                //     'expected' => null,
                // ],
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
}