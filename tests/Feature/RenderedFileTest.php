<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RenderedFileTest extends TestCase
{

    protected $kjvcm = 'NOTICE: The RenderedFile tests assume that the end user has NOT changed any metadata on the KJV (Authorized King James Version) module';

    protected $csvesc = "\\"; //:todo, csv escape should be a config or ""

    /**
     * Testing the output of the CSV render
     * 
     */ 
    public function testRenderedCsv() 
    {
        $Renderer = new \App\Renderers\Csv('kjv');
        $success = $Renderer->renderIfNeeded();        
        $this->assertTrue($success);
        $this->assertFalse($Renderer->isRenderNeeded(TRUE), 'Already rendered, shoudnt need it here ' . __LINE__);

        $file_path = $Renderer->getRenderFilePath();
        $this->assertFileExists($file_path);

        $file_data = file($file_path);

        $this->assertIsArray($file_data);
        $this->assertNotEmpty($file_data);

        $row = str_getcsv($file_data[0], escape: $this->csvesc);
        $this->assertEquals('Authorized King James Version', $row[0], $this->kjvcm);
        
        // Blank rows
        $row = str_getcsv($file_data[1], escape: $this->csvesc);
        $this->assertEmpty($row[0]);        
        $row = str_getcsv($file_data[2], escape: $this->csvesc);
        $this->assertEmpty($row[0]);

        // Copyright
        $row = str_getcsv($file_data[3], escape: $this->csvesc);
        $this->assertStringContainsString('Public Domain in most parts of the world', $row[0], $this->kjvcm);
        $this->assertStringContainsString('Crown copyright', $row[0], $this->kjvcm);

        // Blank row
        $row = str_getcsv($file_data[4], escape: $this->csvesc);
        $this->assertEmpty($row[0]);

        // Column headers
        $row = str_getcsv($file_data[5], escape: $this->csvesc);
        $this->assertEquals(['Verse ID','Book Name', 'Book Number', 'Chapter', 'Verse', 'Text'], $row);

        // First Verse, Genesis 1:1
        $row = str_getcsv($file_data[6], escape: $this->csvesc);
        $this->assertEquals(1, $row[0]);
        $this->assertEquals('Genesis', $row[1]);
        $this->assertEquals(1, $row[2]);
        $this->assertEquals(1, $row[3]);
        $this->assertEquals(1, $row[4]);        
        $this->assertStringContainsString('In the beginning God', $row[5]);

        // Last Verse, Revelation 22:21
        $row = str_getcsv($file_data[31107], escape: $this->csvesc);
        $this->assertEquals(31102, $row[0]);
        $this->assertEquals('Revelation', $row[1]);
        $this->assertEquals(66, $row[2]);
        $this->assertEquals(22, $row[3]);
        $this->assertEquals(21, $row[4]);
        $this->assertStringContainsString('Amen', $row[5]);

        // Shouldn't be anything here
        $this->assertArrayNotHasKey(31108, $file_data);
        $this->assertCount(31108, $file_data);
    }

    /**
     *  @depen_ds testRenderedCsv
     */ 
    public function testRenderedCopyright() 
    {
        // Cache the existing config value
        $cache_deriv_cr = config('download.derivative_copyright_statement');
        $cache_bss_link = config('download.bss_link_enable');
        $cache_app_link = config('download.app_link_enable');
        
        // Set some test values
        $test_deriv_cr = 'Big test of copyright year YYYY 12343123'; // YYYY is replaced with current year
        
        $find_deriv_cr = 'Big test of copyright year ' . date('Y') . ' 12343123';
        $find_bss_url = 'www.BibleSuperSearch.com';
        $find_app_url = config('app.url');

        $Renderer = new \App\Renderers\Csv('kjv');

        // All configs disabled
        config([
            'download.derivative_copyright_statement' => '',
            'download.bss_link_enable' => FALSE,
            'download.app_link_enable' => FALSE,
        ]);

        $this->assertEquals('', config('download.derivative_copyright_statement'));
        $this->assertFalse(config('download.app_link_enable'));
        $this->assertFalse(config('download.bss_link_enable'));

        $this->assertTrue( $Renderer->render(TRUE, TRUE) ); // Force render
        $file_path = $Renderer->getRenderFilePath();
        $this->assertFileExists($file_path);
        $file_data = file($file_path);

        $this->assertIsArray($file_data);
        $this->assertArrayHasKey(3, $file_data);

        $cr = str_getcsv($file_data[3], escape: $this->csvesc)[0];

        $this->assertStringNotContainsString($find_deriv_cr, $cr);
        $this->assertStringNotContainsString($find_bss_url, $cr);
        $this->assertStringNotContainsString($find_app_url, $cr);
        $this->assertNotEmpty($cr);

        // Add the App url
        config([
            'download.derivative_copyright_statement' => '',
            'download.app_link_enable' => TRUE,
            'download.bss_link_enable' => FALSE,
        ]);

        $this->assertEquals('', config('download.derivative_copyright_statement'));
        $this->assertTrue(config('download.app_link_enable'));
        $this->assertFalse(config('download.bss_link_enable'));

        $this->assertTrue( $Renderer->render(TRUE, TRUE) ); // Force render
        $file_path = $Renderer->getRenderFilePath();
        $this->assertFileExists($file_path);
        $file_data = file($file_path);

        $this->assertIsArray($file_data);
        $this->assertArrayHasKey(3, $file_data);

        $cr = str_getcsv($file_data[3], escape: $this->csvesc)[0];

        $this->assertStringNotContainsString($find_deriv_cr, $cr);
        $this->assertStringNotContainsString($find_bss_url, $cr);
        $this->assertStringContainsString($find_app_url, $cr);

        // Add the BSS url
        config([
            'download.derivative_copyright_statement' => '',
            'download.app_link_enable' => TRUE,
            'download.bss_link_enable' => TRUE,
        ]);

        $this->assertEquals('', config('download.derivative_copyright_statement'));
        $this->assertTrue(config('download.app_link_enable'));
        $this->assertTrue(config('download.bss_link_enable'));

        $this->assertTrue( $Renderer->render(TRUE, TRUE) ); // Force render
        $file_path = $Renderer->getRenderFilePath();
        $this->assertFileExists($file_path);
        $file_data = file($file_path);

        $this->assertIsArray($file_data);
        $this->assertArrayHasKey(3, $file_data);

        $cr = str_getcsv($file_data[3], escape: $this->csvesc)[0];

        $this->assertStringNotContainsString($find_deriv_cr, $cr);
        $this->assertStringContainsString($find_bss_url, $cr);
        $this->assertStringContainsString($find_app_url, $cr);

        // Add a deriv copyright statement
        config(['download.derivative_copyright_statement' => $test_deriv_cr]);
        $this->assertEquals($test_deriv_cr, config('download.derivative_copyright_statement'));

        $this->assertTrue( $Renderer->render(TRUE, TRUE) ); // Force render
        $file_path = $Renderer->getRenderFilePath();
        $this->assertFileExists($file_path);
        $file_data = file($file_path);

        $this->assertIsArray($file_data);
        $this->assertArrayHasKey(3, $file_data);

        $cr = str_getcsv($file_data[3], escape: $this->csvesc)[0];

        $this->assertStringContainsString($find_deriv_cr, $cr);
        $this->assertStringContainsString($find_bss_url, $cr);
        $this->assertStringContainsString($find_app_url, $cr);

        // Revert to cached 
        config([
            'download.derivative_copyright_statement' => $cache_deriv_cr,
            'download.bss_link_enable' => $cache_bss_link,
            'download.app_link_enable' => $cache_app_link,
        ]);

        $this->assertTrue( $Renderer->render(TRUE, TRUE) ); // Force render
        $file_path = $Renderer->getRenderFilePath();
        $this->assertFileExists($file_path);
        $file_data = file($file_path);

        $this->assertIsArray($file_data);
        $this->assertArrayHasKey(3, $file_data);

        $cr = str_getcsv($file_data[3], escape: $this->csvesc)[0];

        if($cache_deriv_cr) {
            $this->assertStringNotContainsString($cache_deriv_cr, $cr);
        }

        if($cache_bss_link) {
            $this->assertStringContainsString($find_bss_url, $cr);
        }
        else {
            $this->assertStringNotContainsString($find_bss_url, $cr);
        }
        
        if($cache_app_link) {
            $this->assertStringContainsString($find_app_url, $cr);
        }
        else {
            $this->assertStringNotContainsString($find_app_url, $cr);
        }
    }

    public function testRenderedJson() 
    {
        $Renderer = new \App\Renderers\Json('kjv');
        $success = $Renderer->renderIfNeeded();        
        $this->assertTrue($success);
        $this->assertFalse($Renderer->isRenderNeeded(TRUE), 'Already rendered, shoudnt need it here ' . __LINE__);

        $file_path = $Renderer->getRenderFilePath();
        $this->assertFileExists($file_path);

        $file_data = file_get_contents($file_path);
        $this->assertNotEmpty($file_data);

        $file_data = json_decode($file_data);

        $this->assertIsObject($file_data);
        $this->assertIsObject($file_data->metadata);
        $this->assertEquals('Authorized King James Version', $file_data->metadata->name, $this->kjvcm);
        $this->assertEquals('KJV', $file_data->metadata->shortname);
        $this->assertEquals('en', $file_data->metadata->lang_short);
        $this->assertIsArray($file_data->verses);
        $this->assertCount(31102, $file_data->verses);

        // First Verse, Genesis 1:1
        $this->assertEquals('Genesis', $file_data->verses[0]->book_name);
        $this->assertEquals(1, $file_data->verses[0]->book);
        $this->assertEquals(1, $file_data->verses[0]->chapter);
        $this->assertEquals(1, $file_data->verses[0]->verse);      
        $this->assertStringContainsString('In the beginning God', $file_data->verses[0]->text);

        // Last Verse, Revelation 22:21
        $this->assertEquals('Revelation', $file_data->verses[31101]->book_name);
        $this->assertEquals(66, $file_data->verses[31101]->book);
        $this->assertEquals(22, $file_data->verses[31101]->chapter);
        $this->assertEquals(21, $file_data->verses[31101]->verse);
        $this->assertStringContainsString('Amen', $file_data->verses[31101]->text);
    }

    public function testMachineReadablePlainText() 
    {
        $Renderer = new \App\Renderers\MachineReadableText('kjv');
        $success = $Renderer->renderIfNeeded();        
        $this->assertTrue($success);
        $this->assertFalse($Renderer->isRenderNeeded(TRUE), 'Already rendered, shoudnt need it here ' . __LINE__);

        $file_path = $Renderer->getRenderFilePath();
        $this->assertFileExists($file_path);

        $file_data = file($file_path);

        $this->assertIsArray($file_data);
        $this->assertNotEmpty($file_data);

        $this->assertEquals('Authorized King James Version', trim($file_data[0]) );

        $this->assertEmpty( trim($file_data[1]) ); // blank line

        $this->assertStringContainsString('Public Domain in most parts of the world', $file_data[2], $this->kjvcm);
        $this->assertStringContainsString('Crown copyright', $file_data[2], $this->kjvcm);

        $this->assertEmpty( trim($file_data[3]) ); // blank line
        $this->assertEmpty( trim($file_data[4]) ); // blank line

        $this->assertCount(31107, $file_data);

        // This file might be 'machine readable' but this is not simple to parse!

        // First Verse, Genesis 1:1
        $verse = $this->_parsePlainText($file_data[5]);
        $this->assertEquals('Genesis', $verse['book_name']);
        $this->assertEquals(1, $verse['chapter']);
        $this->assertEquals(1, $verse['verse']);
        $this->assertStringContainsString('In the beginning God', $verse['text']);

        // Parse test: Song of Solomon 1:1
        $verse = $this->_parsePlainText($file_data[17543]);
        $this->assertEquals('Song of Solomon', $verse['book_name']);
        $this->assertEquals(1, $verse['chapter']);
        $this->assertEquals(1, $verse['verse']);
        $this->assertStringContainsString('The song of songs', $verse['text']);

        // Last Verse, Revelation 22:21
        $verse = $this->_parsePlainText($file_data[31106]);
        $this->assertEquals('Revelation', $verse['book_name']);
        $this->assertEquals(22, $verse['chapter']);
        $this->assertEquals(21, $verse['verse']);
        $this->assertStringContainsString('Amen', $verse['text']);
    }

    public function testSqlite() 
    {
        $Renderer = new \App\Renderers\SQLite3('kjv');
        $success = $Renderer->renderIfNeeded();        
        $this->assertTrue($success);
        $this->assertFalse($Renderer->isRenderNeeded(TRUE), 'Already rendered, shoudnt need it here ' . __LINE__);

        $file_path = $Renderer->getRenderFilePath();
        $this->assertFileExists($file_path);

        // Dynamically create 'sqlite_render_test' as a DB connection
        config(['database.connections.sqlite_render_test' => [
            'driver'   => 'sqlite',
            'database' => $file_path,
            'prefix'   => '',
        ]]);

        $meta_raw = \DB::connection('sqlite_render_test')
            ->table('meta')
            ->whereIn('field', ['name', 'shortname', 'lang_short', 'copyright_statement'])
            ->get();

        $meta = [];

        foreach($meta_raw as $m) {
            $meta[$m->field] = $m->value;
        }

        $this->assertEquals('Authorized King James Version', $meta['name']);
        $this->assertEquals('KJV', $meta['shortname']);
        $this->assertEquals('en', $meta['lang_short']);
        $this->assertStringContainsString('Public Domain in most parts of the world', $meta['copyright_statement']);
        $this->assertStringContainsString('Crown copyright', $meta['copyright_statement']);

        $this->assertEquals(31102, \DB::connection('sqlite_render_test')->table('verses')->count());

        // First Verse, Genesis 1:1
        $verse = \DB::connection('sqlite_render_test')->table('verses')->where('id', 1)->first();
        $this->assertEquals(1, $verse->book);
        $this->assertEquals(1, $verse->chapter);
        $this->assertEquals(1, $verse->verse);
        $this->assertStringContainsString('In the beginning God', $verse->text);

        // Last Verse, Revelation 22:21
        $verse = \DB::connection('sqlite_render_test')->table('verses')->where('id', 31102)->first();
        $this->assertEquals(66, $verse->book);
        $this->assertEquals(22, $verse->chapter);
        $this->assertEquals(21, $verse->verse);
        $this->assertStringContainsString('Amen', $verse->text);
    }

    private function _parsePlainText($row) 
    {
        // First, find chapter:verse
        preg_match('/[0-9]+:[0-9]+/', $row, $matches);

        // chapter:verse is in $matches[0]
        $p  = explode($matches[0], $row); // split the row string by chapter:verse
        $cv = explode(':', $matches[0]);  // extract the chapter and verse

        return [
            'book_name' => trim($p[0]),
            'chapter'   => $cv[0],
            'verse'     => $cv[1],
            'text'      => $p[1],
        ];
    }
}
