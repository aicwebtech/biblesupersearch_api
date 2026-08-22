<?php

namespace Tests\Feature\Query;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Engine;
use App\Models\Bible;

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

    /**
     * actionStatistics() derives $multi_bibles from the Bible set it builds, so a reused Engine
     * (it is a singleton) must not carry the previous request's Bibles into one that names
     * none. This mirrors RequestTest::testReusedEngineDoesNotCarryStaleBibleSet, which covers
     * the separate reset in actionQuery().
     */
    public function testReusedEngineDoesNotCarryStaleBibleSet()
    {
        if(!Bible::isEnabled('bishops')) {
            $this->markTestSkipped('Bible bishops not installed or enabled');
        }

        $default = config('bss.defaults.bible');
        $Engine  = new Engine();

        // Request 1: an explicit multi-Bible request.
        $response = $Engine->actionStatistics(['bible' => ['kjv', 'bishops'], 'reference' => 'John 3:16']);

        $this->assertFalse($Engine->hasErrors(), implode(' | ', $Engine->getErrors()));
        $this->assertArrayHasKey('kjv', $response);
        $this->assertArrayHasKey('bishops', $response);

        // Request 2 on the SAME instance, naming no Bible. It must fall back to the configured
        // default rather than silently reusing kjv+bishops from request 1.
        $response = $Engine->actionStatistics(['reference' => 'John 3:16']);

        $this->assertFalse($Engine->hasErrors(), implode(' | ', $Engine->getErrors()));
        $this->assertArrayHasKey($default, $response);
        $this->assertArrayNotHasKey('bishops', $response, 'Reused Engine leaked the previous request\'s Bible set');
        $this->assertCount(1, $response, 'A request naming no Bible must not run against multiple Bibles');
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
