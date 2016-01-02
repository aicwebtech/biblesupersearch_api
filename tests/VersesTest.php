<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Models\Bible;

class VersesTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        //$this->assertTrue(true);
    }
	
	public function testInstall() {
	   $Bible = Bible::findByModule('kjv');
       $Bible->uninstall();
       $this->assertEquals(0, $Bible->installed);
       $Bible->install();
       $this->assertEquals(1, $Bible->installed);
       $this->assertTrue( Schema::hasTable('verses_kjv') );
	}
}
