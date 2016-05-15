<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;

class ProximitySearchTest extends TestCase {
    public function testParenthensesMismatch() {
        $Engine = new Engine();
        return;
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith joy joy love joy', 'searchtype' => 'boolean']);
        //$this->assertTrue($Engine->hasErrors());
        //$errors = $Engine->getErrors();
        //$this->assertCount(1, $errors);
        //$this->assertEquals( trans('errors.no_results'), $errors[0]);
    }
    
    public function testQueryBinding() {
        // Cannot reuse named bindings with PDO extension?
        //return;
        
        $binddata = array('kjv','kjv');
        
        $Bibles = DB::table('bibles')->whereRaw('module = ? OR module_v2 = ?', $binddata)->get();
        $this->assertCount(1, $Bibles);
        $this->assertEquals('kjv', $Bibles[0]->module);
        
        // This is special :P
        $binddata = array(
            ':bible'  => 'kjv',
            ':bible2' => 'kjv',
        );
        
        //var_dump($binddata);
        
        $Bibles = DB::table('bibles')->whereRaw('module = :bible OR module_v2 = :bible2', $binddata)->get();
        $this->assertCount(1, $Bibles);
        $this->assertEquals('kjv', $Bibles[0]->module);
        
        $binddata = array(
            ':bible'  => 'kjv',
        );
        
        return;
        $Bibles = DB::table('bibles')->whereRaw('module = :bible OR module_v2 = :bible', $binddata)->get();
        $this->assertCount(1, $Bibles);
        $this->assertEquals('kjv', $Bibles[0]->module);
    }
    
}
