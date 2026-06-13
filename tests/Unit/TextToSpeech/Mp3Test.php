<?php

namespace Tests\Unit\TextToSpeech;

use PHPUnit\Framework\TestCase;
use App\TextToSpeech\Mp3;

class Mp3Test extends TestCase
{
    public function testConstructorWithNoPathLeavesStrNull(): void
    {
        $mp3 = new Mp3();
        $this->assertNull($mp3->getStr());
    }

    public function testMergeBehindAppendsBinaryContent(): void
    {
        $a = new Mp3();
        $a->str = 'AABB';

        $b = new Mp3();
        $b->str = 'CCDD';

        $a->mergeBehind($b);
        $this->assertSame('AABBCCDD', $a->getStr());
    }

    public function testMergeBehindWithEmptySecondMp3(): void
    {
        $a = new Mp3();
        $a->str = 'DATA';

        $b = new Mp3();
        $b->str = '';

        $a->mergeBehind($b);
        $this->assertSame('DATA', $a->getStr());
    }

    public function testGetIdvEndReturnsFalseWhenNoTagAtEnd(): void
    {
        $mp3 = new Mp3();
        $mp3->str = str_repeat('X', 128); // 128 bytes but no 'TAG' prefix
        $this->assertFalse($mp3->getIdvEnd());
    }

    public function testGetIdvEndReturnsFalseForShortContent(): void
    {
        $mp3 = new Mp3();
        $mp3->str = 'too short';
        // strlen < 128, so substr returns less than 128 chars — TAG won't match
        $this->assertFalse($mp3->getIdvEnd());
    }

    public function testGetIdvEndReturnsByteStringWhenTagPresent(): void
    {
        // ID3v1 tag: 128 bytes starting with 'TAG'
        $tag = 'TAG' . str_repeat("\x00", 125);
        $mp3 = new Mp3();
        $mp3->str = str_repeat('X', 100) . $tag; // prepend arbitrary bytes so total > 128

        $result = $mp3->getIdvEnd();
        $this->assertNotFalse($result);
        $this->assertStringStartsWith('TAG', $result);
    }
}
