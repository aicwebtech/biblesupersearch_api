<?php

use App\Engine;
use App\Models\Verses\VerseStandard;

class LookupLanguagesTest extends TestCase 
{
    function testChinese()
    {
        if(!Engine::isBibleEnabled('chinese_union_trad')) {
            $this->markTestSkipped('Bible chinese_union_trad not installed or enabled');
        }

        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'chinese_union_trad', 'request' => '歷代志下 5', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
    }
}