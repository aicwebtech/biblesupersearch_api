<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;

class ProximitySearchTest extends TestCase {
    public function testParenthensesMismatch() {
        $Engine = new Engine();
        return;
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith joy joy', 'searchtype' => 'boolean']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.no_results'), $errors[0]);
    }
    
}
