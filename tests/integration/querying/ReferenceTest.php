<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;

class ReferenceTest extends TestCase {
    public function testBasic() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom 1', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(32, $results[0]['verses_count']);
        
        // This should pull exact results as above, for the chapter is auto set to 1
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(32, $results[0]['verses_count']);
    }
}
