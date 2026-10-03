<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Models\VolumeContent\StrongsContent;

class StrongsContentTest extends TestCase
{
    /**
     * Legacy H8680, as stored in strongs_definitions
     */
    private const TVM_WITH_COUNT = "<b>Stem:</b> Aphel See H8817 <br><b>Mood:</b> Imperative See H8810 <br><b>Count:</b> 5<br>\n";

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function legacyRow(array $overrides = []): array
    {
        return array_merge([
            'id'              => 8855,
            'number'          => 'G2',
            'root_word'       => '&#913;&#787;&#945;&#961;&#969;&#769;&#957;',
            'transliteration' => 'Aaro&#772;n',
            'pronunciation'   => 'ah-ar-ohn\'',
            'tvm'             => NULL,
            'entry'           => 'Of Hebrew origin [H175]; <i>Aaron</i>, the brother of Moses: - Aaron.',
            'created_at'      => '2018-04-14 13:26:26',
            'updated_at'      => '2018-04-15 10:00:00',
        ], $overrides);
    }

    public function testEntryRowDecodesTextAndKeepsDefinitionHtml(): void
    {
        $row = StrongsContent::mapLegacyRow($this->legacyRow());

        $this->assertSame("\u{0391}\u{0313}\u{03B1}\u{03C1}\u{03C9}\u{0301}\u{03BD}", $row['root_word']);
        $this->assertSame("Aaro\u{0304}n", $row['transliteration']);
        $this->assertSame('ah-ar-ohn\'', $row['pronunciation']);
        $this->assertSame('Of Hebrew origin [H175]; <i>Aaron</i>, the brother of Moses: - Aaron.', $row['definition']);
        $this->assertSame(0, $row['is_special']);
    }

    public function testEntityDecodingCoversPronunciation(): void
    {
        $row = StrongsContent::mapLegacyRow($this->legacyRow(['pronunciation' => 'From &#954;&#959; kolumbos']));

        $this->assertSame('From κο kolumbos', $row['pronunciation']);
    }

    public function testTvmRowIsSpecialWithCountRemoved(): void
    {
        $row = StrongsContent::mapLegacyRow($this->legacyRow([
            'number' => 'H8680', 'root_word' => NULL, 'transliteration' => NULL, 'pronunciation' => NULL,
            'entry' => NULL, 'tvm' => self::TVM_WITH_COUNT,
        ]));

        $this->assertSame("<b>Stem:</b> Aphel See H8817 <br><b>Mood:</b> Imperative See H8810 <br>\n", $row['definition']);
        $this->assertSame(1, $row['is_special']);
        $this->assertNull($row['root_word']);
        $this->assertNull($row['transliteration']);
        $this->assertNull($row['pronunciation']);
    }

    public function testTvmWithoutCountIsUnchanged(): void
    {
        $tvm = '<b>Stem:</b> Qal See H8851 <br>';
        $row = StrongsContent::mapLegacyRow($this->legacyRow(['entry' => NULL, 'tvm' => $tvm]));

        $this->assertSame($tvm, $row['definition']);
        $this->assertSame(1, $row['is_special']);
    }

    public function testEntryIsPreferredOverTvm(): void
    {
        $row = StrongsContent::mapLegacyRow($this->legacyRow(['tvm' => self::TVM_WITH_COUNT]));

        $this->assertStringStartsWith('Of Hebrew origin', $row['definition']);
        $this->assertSame(0, $row['is_special']);
    }

    public function testEmptyEntryFallsBackToTvm(): void
    {
        $row = StrongsContent::mapLegacyRow($this->legacyRow(['entry' => '', 'tvm' => self::TVM_WITH_COUNT]));

        $this->assertStringStartsWith('<b>Stem:</b>', $row['definition']);
        $this->assertSame(1, $row['is_special']);
    }

    /**
     * 15 legacy rows have neither; they are copied as they are (legacy H6220)
     */
    public function testRowWithNeitherHasNoDefinition(): void
    {
        $row = StrongsContent::mapLegacyRow($this->legacyRow([
            'number' => 'H6220', 'entry' => NULL, 'tvm' => NULL,
            'pronunciation' => 'ash-vawth\' For H6219; bright ; Ashvath, an Israelite: - Ashvath.',
        ]));

        $this->assertNull($row['definition']);
        $this->assertSame(0, $row['is_special']);
        $this->assertSame('ash-vawth\' For H6219; bright ; Ashvath, an Israelite: - Ashvath.', $row['pronunciation']);

        $row = StrongsContent::mapLegacyRow($this->legacyRow(['entry' => '', 'tvm' => '']));
        $this->assertNull($row['definition']);
        $this->assertSame(0, $row['is_special']);
    }

    public function testIdTimestampsAndShortDefinition(): void
    {
        $row = StrongsContent::mapLegacyRow($this->legacyRow());

        $this->assertSame(8855, $row['id']);
        $this->assertSame('G2', $row['number']);
        $this->assertSame('2018-04-14 13:26:26', $row['created_at']);
        $this->assertSame('2018-04-15 10:00:00', $row['updated_at']);
        $this->assertNull($row['short_definition']);
    }

    public function testMappedColumnsMatchTheContentSchema(): void
    {
        $columns = array_keys(StrongsContent::mapLegacyRow($this->legacyRow()));
        sort($columns);

        $this->assertSame([
            'created_at', 'definition', 'id', 'is_special', 'number', 'pronunciation',
            'root_word', 'short_definition', 'transliteration', 'updated_at',
        ], $columns);
    }

    /**
     * Must match Engine::_formatStrongs(), which applies the same pattern to the legacy table
     */
    public function testStripTvmCountMatchesEngine(): void
    {
        $samples = [
            self::TVM_WITH_COUNT,
            "<b>Stem:</b> Aphel See H8817 <br><b>Mood:</b> Imperfect See H8811 <br><b>Count:</b> 36<br>\n",
            '<b>Stem:</b> Qal See H8851 <br>',
        ];

        foreach($samples as $tvm) {
            $this->assertSame(preg_replace('/<b>Count:<\/b> [0-9]+.*?<br>/', '', $tvm), StrongsContent::stripTvmCount($tvm));
        }
    }

    public function testStripTvmCountOfEmpty(): void
    {
        $this->assertNull(StrongsContent::stripTvmCount(NULL));
        $this->assertNull(StrongsContent::stripTvmCount(''));
    }
}
