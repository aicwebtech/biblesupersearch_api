<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;

class ProximitySearchTest extends TestCase {
    public function testParenthensesMismatch() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '(faith PROX(2) joy joy love joy', 'search_type' => 'boolean']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.prox_paren_mismatch'), $errors[0]);
    }
    public function testNotBoolean() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith PROX(2) joy', 'search_type' => 'any']);
        $this->assertTRUE($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.prox_operator_not_allowed'), $errors[0]);
    }
    
    public function testQueryBinding() {
        // Cannot reuse named bindings with PDO extension?
        // This is special :P
        $binddata = array('kjv','kjv');
        
        $Bibles = DB::table('bibles')->whereRaw('module = ? OR module_v2 = ?', $binddata)->get();
        $this->assertCount(1, $Bibles);
        $this->assertEquals('kjv', $Bibles[0]->module);
        
        $binddata = array(
            ':bible'  => 'kjv',
            ':bible2' => 'kjv',
        );
        
        $Bibles = DB::table('bibles')->whereRaw('module = :bible OR module_v2 = :bible2', $binddata)->get();
        $this->assertCount(1, $Bibles);
        $this->assertEquals('kjv', $Bibles[0]->module);
        
        $binddata = array(
            ':bible'  => 'kjv',
        );
        
        //$Bibles = DB::table('bibles')->whereRaw('module = :bible OR module_v2 = :bible', $binddata)->get();
        //$this->assertCount(1, $Bibles);
        //$this->assertEquals('kjv', $Bibles[0]->module);
    }
    
}
