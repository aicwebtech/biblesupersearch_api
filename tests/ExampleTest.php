<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\Bible;

class ExampleTest extends TestCase
{
    public function testFirst() 
    {
        $success = TRUE;
        $KJV = Bible::findByModule('kjv');

        if(!$KJV || !$KJV->installed || !$KJV->enabled) {
            $success = FALSE;
        }

        $success = $success ? Schema::hasTable('verses_kjv') : FALSE;

        if(!$success) {
            echo(PHP_EOL . 'NOTICE: KJV Bible module must be INSTALLED and ENABLED to run this unit test suite!' . PHP_EOL . PHP_EOL);
            exit(1); // If we don't have KJV, exit all tests
        }

        $this->assertTrue($success);
    }

    /**
     * A basic functional test example.
     *
     * @return void
     */
    public function testBasicLoad()
    {
        $this->assertTrue(TRUE);
        $response = $this->get('/');
        $response->assertStatus(200);

        // This no longer works!
        // $this->assertContains('Documentation', $response);
        // $response->assertContains('Documentation')
        //      ->see('Documentation');
    }
}
