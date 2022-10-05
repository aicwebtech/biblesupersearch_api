<?php

use App\Engine;
use App\Passage;
use App\Models\Verses\VerseStandard;

class LookupLanguagesTest extends TestCase 
{
    function testParseChinese()
    {
        $reference = '历代志下';
        $bcv = [
            'book' => '历代志下',
            'chapter_verse' => null,
        ];
        
        $this->assertTrue(Passage::isAlpha($reference));

        $references = Passage::explodeReferences($reference);
        $this->assertIsArray($references);
        $this->assertCount(1, $references);
        $this->assertEquals($reference, $references[0]);        

        $references = Passage::explodeReferences($reference, true);
        $this->assertIsArray($references);
        $this->assertCount(1, $references);
        $this->assertEquals($bcv, $references[0]);

        $Passages = Passage::parseReferences($reference, ['zh']);
        $this->assertIsArray($Passages);
        $this->assertCount(1, $Passages);
        $this->assertFalse($Passages[0]->hasErrors());

        $reference = '历代志下 5';
        $bcv = [
            'book' => '历代志下',
            'chapter_verse' => 5,
        ];


        $references = Passage::explodeReferences($reference);
        $this->assertIsArray($references);
        $this->assertCount(1, $references);
        $this->assertEquals($reference, $references[0]);        

        $references = Passage::explodeReferences($reference, true);
        $this->assertIsArray($references);
        $this->assertCount(1, $references);
        $this->assertEquals($bcv, $references[0]);

        $Passages = Passage::parseReferences($reference, ['zh']);
        $this->assertIsArray($Passages);
        $this->assertCount(1, $Passages);
        $this->assertFalse($Passages[0]->hasErrors());
    }

    function testParseChineseTraditional()
    {
        $reference = '歷代志下 5';
        $bcv = [
            'book' => '歷代志下',
            'chapter_verse' => 5,
        ];

        $references = Passage::explodeReferences($reference);
        $this->assertIsArray($references);
        $this->assertCount(1, $references);
        $this->assertEquals($reference, $references[0]);        

        $references = Passage::explodeReferences($reference, true);
        $this->assertIsArray($references);
        $this->assertCount(1, $references);
        $this->assertEquals($bcv, $references[0]);

        $Passages = Passage::parseReferences($reference, ['zh_TW']);
        $this->assertIsArray($Passages);
        $this->assertCount(1, $Passages);
        $this->assertFalse($Passages[0]->hasErrors());
    }    

    function testParseChineseSimplified()
    {
        $reference = '历代志下 5';
        $bcv = [
            'book' => '历代志下',
            'chapter_verse' => 5,
        ];

        $references = Passage::explodeReferences($reference);
        $this->assertIsArray($references);
        $this->assertCount(1, $references);
        $this->assertEquals($reference, $references[0]);        

        $references = Passage::explodeReferences($reference, true);
        $this->assertIsArray($references);
        $this->assertCount(1, $references);
        $this->assertEquals($bcv, $references[0]);

        $Passages = Passage::parseReferences($reference, ['zh_CN']);
        $this->assertIsArray($Passages);
        $this->assertCount(1, $Passages);
        $this->assertFalse($Passages[0]->hasErrors());
    }

    function testChineseTraditional()
    {
        if(!Engine::isBibleEnabled('chinese_union_trad')) {
            $this->markTestSkipped('Bible chinese_union_trad not installed or enabled');
        }

        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        $results = $Engine->actionQuery(['bible' => 'chinese_union_trad', 'reference' => '歷代志下', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());        

        $results = $Engine->actionQuery(['bible' => 'chinese_union_trad', 'reference' => '歷代志下 5', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());        

        $results = $Engine->actionQuery(['bible' => 'chinese_union_trad', 'reference' => '歷代志下 5:1-10', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(10, $results['chinese_union_trad']);        

        $results = $Engine->actionQuery(['bible' => 'chinese_union_trad', 'reference' => '約一 1', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(10, $results['chinese_union_trad']);        
    }    

    function testChineseSimplified()
    {
        if(!Engine::isBibleEnabled('chinese_union_trad')) {
            $this->markTestSkipped('Bible chinese_union_trad not installed or enabled');
        }

        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        $results = $Engine->actionQuery(['bible' => 'chinese_union_trad', 'reference' => '历代志下', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());        

        $results = $Engine->actionQuery(['bible' => 'chinese_union_trad', 'reference' => '历代志下 5', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());        

        $results = $Engine->actionQuery(['bible' => 'chinese_union_trad', 'reference' => '历代志下 5:1-10', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(10, $results['chinese_union_trad']);        

        $results = $Engine->actionQuery(['bible' => 'chinese_union_trad', 'reference' => '约翰一书 1', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(10, $results['chinese_union_trad']);        
    }
}