<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Models\VolumeContent\ContentBase;

/**
 * The contents.txt row format: one row per line, '|' separated, each value trimmed and then
 * backslash-escaped (\\ \n \r \|).
 */
class VolumeContentCodecTest extends TestCase
{
    #[DataProvider('roundTripProvider')]
    public function testFieldsRoundTrip(string $value): void
    {
        $line = ContentBase::encodeRow([$value, 'after']);

        $this->assertStringNotContainsString("\n", $line);
        $this->assertStringNotContainsString("\r", $line);
        $this->assertSame([$value, 'after'], ContentBase::decodeRow($line));
    }

    public static function roundTripProvider(): array
    {
        return [
            'plain'             => ['to <i>cleave</i>'],
            'inner newline'     => ["<b>Stem:</b> Aphel <br>\n<b>Mood:</b> Imperative"],
            'carriage return'   => ["one\r\ntwo"],
            'pipe'              => ['a|b'],
            'backslash'         => ['a\\b'],
            'escape lookalike'  => ['literal \\n and \\| text'],
            'trailing backslash'=> ['ends with \\'],
            'everything'        => ["x\\|y\n\\n|\rz"], // not ending in whitespace: that is trimmed
            'unicode'           => ["\u{0391}\u{0313}\u{03B1}\u{03C1}\u{03C9}\u{0301}\u{03BD}"],
            'zero'              => ['0'],
        ];
    }

    public function testValuesAreTrimmed(): void
    {
        $this->assertSame('defn', ContentBase::encodeField("  defn \n"));
        $this->assertSame(['a', 'b'], ContentBase::decodeRow(ContentBase::encodeRow([" a\n", "\tb "])));
    }

    public function testNullIsTheEmptyField(): void
    {
        $this->assertSame('', ContentBase::encodeField(NULL));
        $this->assertSame('a||c', ContentBase::encodeRow(['a', NULL, 'c']));
        $this->assertSame(['a', '', 'c'], ContentBase::decodeRow('a||c'));
    }

    /** empty() would lose these - the Bible exporter's bug */
    public function testZeroIsKept(): void
    {
        $this->assertSame('0', ContentBase::encodeField(0));
        $this->assertSame('0', ContentBase::encodeField('0'));
        $this->assertSame(['H1', '0'], ContentBase::decodeRow(ContentBase::encodeRow(['H1', 0])));
    }

    public function testEscapesAreAsDocumented(): void
    {
        $this->assertSame('a\\|b\\nc\\rd\\\\e', ContentBase::encodeField("a|b\nc\rd\\e"));
    }

    public function testUnknownEscapeAndLoneBackslashAreKept(): void
    {
        $this->assertSame(['a\\xb'], ContentBase::decodeRow('a\\xb'));
        $this->assertSame(['ab\\'], ContentBase::decodeRow('ab\\'));
    }

    public function testFieldCountFollowsTheDelimiters(): void
    {
        $this->assertSame(['only'], ContentBase::decodeRow('only'));
        $this->assertSame(['', ''], ContentBase::decodeRow('|'));
        $this->assertSame(['a', 'b', 'c', 'd'], ContentBase::decodeRow('a|b|c|d'));
        $this->assertSame(['a|b', 'c'], ContentBase::decodeRow('a\\|b|c'));
    }
}
