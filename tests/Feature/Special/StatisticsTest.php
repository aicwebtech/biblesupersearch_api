<?php

namespace Tests\Feature\Query;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Engine;

class StatisticsTest extends TestCase 
{

    public function testBasicVerse()
    {
        $Engine = Engine::getInstance();

        $response = $Engine->actionStatistics(['bible' => 'kjv', 'reference' => 'John 3:16']);
        $this->assertFalse($Engine->hasErrors());

        // var_dump($response);

        $this->assertIsArray($response['kjv']);
        $this->assertIsArray($response['kjv']['passage']);
        $this->assertNotEmpty($response['kjv']['passage']);        
        $this->assertIsArray($response['kjv']['chapter']);
        $this->assertNotEmpty($response['kjv']['chapter']);
        $this->assertIsArray($response['kjv']['book']);
        $this->assertNotEmpty($response['kjv']['book']);

        $this->assertEquals(1, $response['kjv']['passage']['num_verses']);

        $this->assertEquals(36, $response['kjv']['chapter']['num_verses']);
        $this->assertEquals(1, $response['kjv']['chapter']['num_chapters']);
        $this->assertEquals(1, $response['kjv']['chapter']['num_books']);
        $this->assertEquals(1000, $response['kjv']['chapter']['chapter_position']);

        // $this->assertEquals(433, $response['kjv']['book']['num_verses']);
        $this->assertEquals(21, $response['kjv']['book']['num_chapters']);
        $this->assertEquals(1, $response['kjv']['book']['num_books']);

        $this->assertEquals(31102, $response['kjv']['full']['num_verses']);
        $this->assertEquals(1189, $response['kjv']['full']['num_chapters']);
        $this->assertEquals(66, $response['kjv']['full']['num_books']);
    }    

    public function testBasicPassage()
    {
        $Engine = Engine::getInstance();

        $response = $Engine->actionStatistics(['bible' => 'kjv', 'reference' => 'Romans 5:8-10']);
        $this->assertFalse($Engine->hasErrors());

        // var_dump($response);

        $this->assertIsArray($response['kjv']);
        $this->assertIsArray($response['kjv']['passage']);
        $this->assertNotEmpty($response['kjv']['passage']);        
        $this->assertIsArray($response['kjv']['chapter']);
        $this->assertNotEmpty($response['kjv']['chapter']);
        $this->assertIsArray($response['kjv']['book']);
        $this->assertNotEmpty($response['kjv']['book']);

        $this->assertEquals(3, $response['kjv']['passage']['num_verses']);

        $this->assertEquals(21, $response['kjv']['chapter']['num_verses']);
        $this->assertEquals(1, $response['kjv']['chapter']['num_chapters']);
        $this->assertEquals(1, $response['kjv']['chapter']['num_books']);
        $this->assertEquals(1051, $response['kjv']['chapter']['chapter_position']);

        $this->assertEquals(433, $response['kjv']['book']['num_verses']);
        $this->assertEquals(16, $response['kjv']['book']['num_chapters']);
        $this->assertEquals(1, $response['kjv']['book']['num_books']);

        $this->assertEquals(31102, $response['kjv']['full']['num_verses']);
        $this->assertEquals(1189, $response['kjv']['full']['num_chapters']);
        $this->assertEquals(66, $response['kjv']['full']['num_books']);
    }

    public function testErrors() 
    {
        $Engine = Engine::getInstance();

        // Empty Request
        $response = $Engine->actionStatistics([]);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals(trans('errors.no_query'), $errors[0]);

        // Semi-empty Request
        $response = $Engine->actionStatistics(['bible' => 'kjv']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals(trans('errors.no_query'), $errors[0]);

        // Non-existant book
        $response = $Engine->actionStatistics(['bible' => 'kjv', 'reference' => '2 Hesitations 3:2']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals(trans('errors.book.not_found', ['book' => '2 Hesitations']), $errors[0]);

        // Non-existant book AND existing book
        $response = $Engine->actionStatistics(['bible' => 'kjv', 'reference' => 'Romans 5:8; 2 Hesitations 3:2']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals(trans('errors.book.not_found', ['book' => '2 Hesitations']), $errors[0]);
    }

}
