<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Models\Language;

class LanguageTest extends TestCase
{
    public function testRtlCheck() 
    {
        $this->assertTrue( Language::isRtl('he') );
        $this->assertTrue( Language::isRtl('ar') );
        $this->assertFalse( Language::isRtl('en') );
        $this->assertFalse( Language::isRtl('es') );
        $this->assertFalse( Language::isRtl('dne') ); // does not exist => should return FALSE
    }
}
