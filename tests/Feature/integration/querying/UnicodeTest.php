<?php

//namespace Tests\Feature\integration\querying;

//use Tests\TestCase;
use App\Engine;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UnicodeTest extends TestCase {

    public function testSpanish() {
        if(!Engine::isBibleEnabled('rvg')) {
            $this->markTestSkipped('Bible rvg not installed or enabled');
        }

        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'rvg', 'request' => 'Señor', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
    }

    public function testItalian() {
        if(!Engine::isBibleEnabled('diodati')) {
            $this->markTestSkipped('Bible diodati not installed or enabled');
        }

        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'diodati', 'request' => 'l’uomo', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'diodati', 'request' => '(l’uomo) (alla)', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors(), 'Failed on using implied AND');
    }

    public function testHebrew() {
        if(!Engine::isBibleEnabled('wlc')) {
            $this->markTestSkipped('Bible wlc not installed or enabled');
        }

        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'wlc', 'request' => 'בְּרֵאשִׁית', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
    }

    public function testArabic() {
        if(!Engine::isBibleEnabled('svd')) {
            $this->markTestSkipped('Bible svd (Smith Van Dyke) not installed or enabled');
        }

        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'svd', 'request' => 'المسيح ', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
    }

    public function testThai() {
        if(!Engine::isBibleEnabled('thaikjv')) {
            $this->markTestSkipped('Bible thaikjv not installed or enabled');
        }

        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'thaikjv', 'request' => 'ประการแรก', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'thaikjv', 'request' => '(ประการแรก) (เพราะว่า)', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
    }

    public function testFrenchLookup() {
        if(!Engine::isBibleEnabled('martin')) {
            $this->markTestSkipped('Bible martin not installed or enabled');
        }

        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'martin', 'request' => 'Ésaïe 31', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
    }

}
