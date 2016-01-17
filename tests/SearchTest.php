<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Search;

class SearchTest extends TestCase {

    public function testMethodIsSpecial() {
        $this->assertFalse( Search::isSpecial('faith hope charity','and') );
        $this->assertFalse( Search::isSpecial('faith hope charity','or') );
        $this->assertFalse( Search::isSpecial('faith hope charity','phrase') );
        $this->assertFalse( Search::isSpecial('faith hope charity','regexp') );
        $this->assertFalse( Search::isSpecial('faith hope charity','boolean') );
        $this->assertFalse( Search::isSpecial('faith hope charity','strongs') ); // This may need to be special
        $this->assertTrue(  Search::isSpecial('faith hope charity','proximity') );
        $this->assertTrue(  Search::isSpecial('faith hope charity','chapter') );
        $this->assertTrue(  Search::isSpecial('faith hope charity','book') );
        $this->assertFalse( Search::isSpecial('faith CHAP hope charity','and') );
        $this->assertFalse( Search::isSpecial('faith CHAP hope PROX(4) charity','or') );
        $this->assertFalse( Search::isSpecial('faith chap hope charity','boolean') ); // Case sensitive, so false
        $this->assertTrue(  Search::isSpecial('faith CHAP hope charity','boolean') );
        $this->assertTrue(  Search::isSpecial('faith PROX(4) hope PROX(12) charity','boolean') );
        $this->assertTrue(  Search::isSpecial('faith BOOK hope CHAP charity','boolean') );
    }
}
