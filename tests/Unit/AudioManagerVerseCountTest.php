<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\AudioManager;

/**
 * The per-request generation cap exists because each verse *without* audio costs
 * one external TTS call. Counting every verse in the passage instead refused an
 * audio check over any already-generated book larger than the cap, even though
 * that request makes no provider calls at all.
 */
class AudioManagerVerseCountTest extends TestCase
{
    /** @var string */
    private $audio_path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->audio_path = sys_get_temp_dir() . '/bss_audio_' . bin2hex(random_bytes(6));
        mkdir($this->audio_path);
    }

    protected function tearDown(): void
    {
        foreach(glob($this->audio_path . '/*') as $file) {
            unlink($file);
        }

        rmdir($this->audio_path);

        parent::tearDown();
    }

    /**
     * @param  string|null  $file_name
     * @return \stdClass
     */
    private function verse(?string $file_name): \stdClass
    {
        return (object) ['file_name' => $file_name];
    }

    /**
     * @param  string  $file_name
     * @return \stdClass
     */
    private function generatedVerse(string $file_name): \stdClass
    {
        file_put_contents($this->audio_path . '/' . $file_name, 'mp3');

        return $this->verse($file_name);
    }

    public function testGeneratedVersesDoNotCountTowardTheCap(): void
    {
        $verses = [];

        for($i = 1; $i <= 250; $i++) {
            $verses[] = $this->generatedVerse('verse_' . $i . '.mp3');
        }

        $this->assertSame(0, AudioManager::countVersesNeedingAudio($verses, $this->audio_path));
    }

    public function testOnlyVersesWithoutAudioAreCounted(): void
    {
        $verses = [
            $this->generatedVerse('gen_1_1.mp3'),
            $this->verse(null),
            $this->generatedVerse('gen_1_3.mp3'),
            $this->verse(''),
        ];

        $this->assertSame(2, AudioManager::countVersesNeedingAudio($verses, $this->audio_path));
    }

    /**
     * A row can name a file that is no longer on disk; that verse still has to
     * be generated, so it must be charged against the cap.
     */
    public function testRecordedFilenameWithNoFileOnDiskStillCounts(): void
    {
        $verses = [
            $this->generatedVerse('present.mp3'),
            $this->verse('missing.mp3'),
        ];

        $this->assertSame(1, AudioManager::countVersesNeedingAudio($verses, $this->audio_path));
    }

    public function testVerseAudioFileExists(): void
    {
        $this->assertTrue(AudioManager::verseAudioFileExists($this->generatedVerse('x.mp3'), $this->audio_path));
        $this->assertFalse(AudioManager::verseAudioFileExists($this->verse('x.mp3'), $this->audio_path . '/nope'));
        $this->assertFalse(AudioManager::verseAudioFileExists($this->verse(null), $this->audio_path));
    }

    public function testEmptyPassageNeedsNothing(): void
    {
        $this->assertSame(0, AudioManager::countVersesNeedingAudio([], $this->audio_path));
    }
}
