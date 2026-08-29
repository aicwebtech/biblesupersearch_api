<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\AudioManager;

/**
 * Audio files are stored under a fixed internal name that encodes book, chapter and verse.
 * parseInternalFilename() reads that name back, and getFilenameMatchesList() describes the
 * upload-matching options to the admin UI.
 *
 * Both are pure. The upload and scan paths that consume them touch the filesystem and the
 * database and are covered in Tests\Feature\AudioManagerTest.
 *
 * Note this file is named AudioFilenameTest rather than AudioManagerTest so it does not
 * collide with the existing Tests\Unit\AudioManagerTest.
 */
class AudioFilenameTest extends TestCase
{
    /**
     * The stored name is zero-padded: two digits of book, three of chapter, three of verse.
     *
     * @return array<string, array{string, int, int, int}>
     */
    public static function verseFilenameProvider(): array
    {
        return [
            'Genesis 1:1'     => ['01_001_001.mp3', 1, 1, 1],
            'John 3:16'       => ['43_003_016.mp3', 43, 3, 16],
            'Psalm 119:176'   => ['19_119_176.mp3', 19, 119, 176],
            'Revelation 22:21' => ['66_022_021.mp3', 66, 22, 21],
        ];
    }

    #[DataProvider('verseFilenameProvider')]
    public function testAVerseFilenameIsParsed(string $filename, int $book, int $chapter, int $verse): void
    {
        $parsed = (new AudioManager())->parseInternalFilename($filename);

        $this->assertSame('verse', $parsed['type']);
        $this->assertSame($book, $parsed['book']);
        $this->assertSame($chapter, $parsed['chapter']);
        $this->assertSame($verse, $parsed['verse']);
    }

    /**
     * A chapter-level recording has no verse segment, and must report a null verse rather
     * than zero - zero would be a valid-looking verse number.
     */
    public function testAChapterFilenameIsParsedWithNoVerse(): void
    {
        $parsed = (new AudioManager())->parseInternalFilename('43_003.mp3');

        $this->assertSame('chapter', $parsed['type']);
        $this->assertSame(43, $parsed['book']);
        $this->assertSame(3, $parsed['chapter']);
        $this->assertNull($parsed['verse']);
    }

    /**
     * Zero padding must be stripped, not carried into the numbers - '01' is book 1, and an
     * octal-looking '008' is chapter 8.
     */
    public function testZeroPaddingIsStripped(): void
    {
        $parsed = (new AudioManager())->parseInternalFilename('09_008_007.mp3');

        $this->assertSame(9, $parsed['book']);
        $this->assertSame(8, $parsed['chapter']);
        $this->assertSame(7, $parsed['verse']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unparseableFilenameProvider(): array
    {
        return [
            'wrong extension'     => ['43_003_016.wav'],
            'no extension'        => ['43_003_016'],
            'too few digits'      => ['4_3_16.mp3'],
            'too many segments'   => ['43_003_016_002.mp3'],
            'original upload name' => ['John_03.mp3'],
            'empty'               => [''],
            'chapter wrong width' => ['43_03.mp3'],
        ];
    }

    /**
     * Anything not matching the internal scheme returns null, so a stray file in the audio
     * directory is skipped rather than mis-filed against a real verse.
     */
    #[DataProvider('unparseableFilenameProvider')]
    public function testAnUnrecognisedFilenameReturnsNull(string $filename): void
    {
        $this->assertNull((new AudioManager())->parseInternalFilename($filename));
    }

    // -----------------------------------------------------------------------
    // Upload matching options
    // -----------------------------------------------------------------------

    public function testEveryMatchOptionIsListedWithItsKey(): void
    {
        $list = AudioManager::getFilenameMatchesList();

        $this->assertSame(array_keys(AudioManager::$filename_matches), array_column($list, 'key'));
    }

    public function testEveryMatchOptionIsLabelledAndTyped(): void
    {
        foreach (AudioManager::getFilenameMatchesList() as $item) {
            $this->assertNotEmpty($item['label'], "option {$item['key']} should be labelled");
            $this->assertNotEmpty($item['type'], "option {$item['key']} should declare a type");
            $this->assertArrayHasKey('auto', $item);
        }
    }

    /**
     * The patterns are stored as PHP regexes but published to the UI without their
     * delimiters, so the admin sees the bare expression.
     */
    public function testPatternsArePublishedWithoutTheirDelimiters(): void
    {
        $list = array_column(AudioManager::getFilenameMatchesList(), null, 'key');

        $this->assertStringStartsNotWith('/', $list['verse']['pattern']);
        $this->assertStringEndsNotWith('/', $list['verse']['pattern']);
    }

    /**
     * Trimming the delimiters must not damage the expression itself.
     */
    public function testTrimmedPatternsStillCompile(): void
    {
        foreach (AudioManager::getFilenameMatchesList() as $item) {
            if ($item['pattern'] === '') {
                continue;
            }

            $this->assertNotFalse(
                @preg_match('/' . $item['pattern'] . '/', '01-01-GEN.mp3'),
                "pattern for {$item['key']} should still compile once re-delimited"
            );
        }
    }

    /**
     * Auto-detect is a placeholder rather than a real pattern, and must not be offered as one
     * of the patterns the scanner tries automatically.
     */
    public function testAutoDetectIsAPlaceholderAndNotItselfAutomatic(): void
    {
        $list = array_column(AudioManager::getFilenameMatchesList(), null, 'key');

        $this->assertSame('', $list['auto']['pattern']);
        $this->assertFalse($list['auto']['auto']);
    }

    public function testAtLeastOneOptionIsTriedAutomatically(): void
    {
        $auto = array_filter(AudioManager::getFilenameMatchesList(), fn ($item) => $item['auto']);

        $this->assertNotEmpty($auto, 'auto-detect needs at least one pattern to try');
    }
}
