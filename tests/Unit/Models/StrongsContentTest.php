<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\Attributes\DataProvider;
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
     * Also what Engine::_formatStrongs() applies to the legacy table
     */
    public function testStripTvmCountRemovesOnlyTheCount(): void
    {
        $samples = [
            self::TVM_WITH_COUNT => "<b>Stem:</b> Aphel See H8817 <br><b>Mood:</b> Imperative See H8810 <br>\n",
            "<b>Stem:</b> Aphel See H8817 <br><b>Mood:</b> Imperfect See H8811 <br><b>Count:</b> 36<br>\n"
                => "<b>Stem:</b> Aphel See H8817 <br><b>Mood:</b> Imperfect See H8811 <br>\n",
            '<b>Stem:</b> Qal See H8851 <br>' => '<b>Stem:</b> Qal See H8851 <br>',
        ];

        foreach($samples as $tvm => $expected) {
            $this->assertSame($expected, StrongsContent::stripTvmCount($tvm));
        }
    }

    public function testStripTvmCountOfEmpty(): void
    {
        $this->assertNull(StrongsContent::stripTvmCount(NULL));
        $this->assertNull(StrongsContent::stripTvmCount(''));
    }

    public function testValidateImportRowRequiresNumberAndDefinition(): void
    {
        $errors = StrongsContent::validateImportRow([]);

        $this->assertContains(StrongsContent::getImportFields()['number'] . ' is required', $errors);
        $this->assertContains('Definition is required', $errors);
        $this->assertSame([], StrongsContent::validateImportRow(['number' => 'H1', 'definition' => 'One']));
    }

    public function testValidateImportRowChecksTheNumberAndLengths(): void
    {
        // A variant letter included: the API could never look it up
        foreach(['H0001', 'X1', 'h1', 'H0', 'G123456', 'H1234a', 'H1234A'] as $number) {
            $this->assertNotSame([], StrongsContent::validateImportRow(['number' => $number, 'definition' => 'x']), $number);
        }

        $errors = StrongsContent::validateImportRow(['number' => 'H1', 'definition' => 'x', 'root_word' => str_repeat('a', 256)]);
        $this->assertSame(['Root word may not be longer than 255 characters'], $errors);
    }

    public function testPrepareImportRowKeepsOnlyImportableColumns(): void
    {
        $row = StrongsContent::prepareImportRow(['number' => 'H1', 'definition' => 'One', 'root_word' => '', 'number_hg' => 'H', 'id' => 9]);

        $this->assertSame([
            'number'           => 'H1',
            'root_word'        => NULL,
            'transliteration'  => NULL,
            'pronunciation'    => NULL,
            'definition'       => 'One',
            'short_definition' => NULL,
            'is_special'       => 0,
        ], $row);
    }

    public function testPrepareImportRowKeepsIsSpecial(): void
    {
        $this->assertSame(1, StrongsContent::prepareImportRow(['number' => 'H1', 'definition' => 'x', 'is_special' => 1])['is_special']);
        $this->assertSame(0, StrongsContent::prepareImportRow(['number' => 'H1', 'definition' => 'x'])['is_special']);
    }

    public function testImportFieldIsMappedHonoursAlternatives(): void
    {
        $this->assertTrue(StrongsContent::importFieldIsMapped('number', ['number']));
        $this->assertTrue(StrongsContent::importFieldIsMapped('number', ['number_int', 'number_hg']));
        $this->assertFalse(StrongsContent::importFieldIsMapped('number', ['number_int']));
        $this->assertFalse(StrongsContent::importFieldIsMapped('definition', ['short_definition']));
    }

    public function testImportFieldListFlagsRequiredFields(): void
    {
        $list = array_column(StrongsContent::getImportFieldList(), 'required', 'value');

        $this->assertTrue($list['number']);
        $this->assertTrue($list['definition']);
        $this->assertFalse($list['number_hg']);
        $this->assertFalse($list['root_word']);
    }

    public function testNumberFieldsAreOfferedTogether(): void
    {
        $this->assertSame(
            ['number', 'number_hg', 'number_int', 'root_word', 'transliteration', 'pronunciation', 'definition', 'short_definition', 'tvm'],
            array_column(StrongsContent::getImportFieldList(), 'value')
        );
    }

    /**
     * Listed fields lead in the given order and the rest keep theirs.  An unknown key is
     * ignored, and an extra field stays out unless extras are asked for.
     */
    #[DataProvider('importFieldOrderProvider')]
    public function testImportFieldOrderPositionsAnyField(bool $include_extra, array $expected): void
    {
        $Content = new class extends StrongsContent {
            public const IMPORT_FIELD_ORDER = ['definition', 'bacon', 'number_int', 'number'];
        };

        $this->assertSame($expected, array_keys($Content::getImportFields($include_extra)));
    }

    /**
     * An ordinary entry goes back to entry, with tvm empty
     */
    public function testMapToLegacyRowRoundTripsAnEntry(): void
    {
        $legacy = StrongsContent::mapToLegacyRow(StrongsContent::mapLegacyRow($this->legacyRow()));

        $this->assertSame(['id', 'number', 'root_word', 'transliteration', 'pronunciation', 'tvm', 'entry'], array_keys($legacy));
        $this->assertSame(8855, $legacy['id']);
        $this->assertSame('G2', $legacy['number']);
        $this->assertSame($this->legacyRow()['entry'], $legacy['entry']);
        $this->assertNull($legacy['tvm']);

        // Plain text stays decoded
        $this->assertSame("\u{0391}\u{0313}\u{03B1}\u{03C1}\u{03C9}\u{0301}\u{03BD}", $legacy['root_word']);
        $this->assertSame("Aaro\u{0304}n", $legacy['transliteration']);
    }

    /**
     * A tense/voice/mood entry goes back to tvm, with entry empty and the count still removed
     */
    public function testMapToLegacyRowRoundTripsATvmEntry(): void
    {
        $legacy = StrongsContent::mapToLegacyRow(StrongsContent::mapLegacyRow($this->legacyRow(['entry' => NULL, 'tvm' => self::TVM_WITH_COUNT])));

        $this->assertNull($legacy['entry']);
        $this->assertSame(StrongsContent::stripTvmCount(self::TVM_WITH_COUNT), $legacy['tvm']);
        $this->assertStringNotContainsString('Count', $legacy['tvm']);
    }

    public function testMapToLegacyRowFillsMissingColumnsWithNull(): void
    {
        $legacy = StrongsContent::mapToLegacyRow(['number' => 'H1', 'definition' => 'x']);

        $this->assertNull($legacy['id']);
        $this->assertNull($legacy['root_word']);
        $this->assertSame('x', $legacy['entry']);
    }

    public static function importFieldOrderProvider(): array
    {
        return [
            'with extras' => [TRUE, ['definition', 'number_int', 'number', 'root_word', 'transliteration', 'pronunciation', 'short_definition', 'number_hg', 'tvm']],
            'without'     => [FALSE, ['definition', 'number', 'root_word', 'transliteration', 'pronunciation', 'short_definition']],
        ];
    }
}
