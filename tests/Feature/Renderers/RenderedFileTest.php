<?php

namespace Tests\Feature\Renderers;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;

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
     *
     * The copyright block is assembled by _getCopyrightStatement(), so the config permutations
     * are asserted against that directly. Force-rendering the whole KJV for each permutation
     * costs ~300 chunked queries and a 5MB file write, to read one line back. A single real
     * render still runs at the end, pinning the statement to its position in the file.
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

        $cr = $this->_copyrightStatement($Renderer);

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

        $cr = $this->_copyrightStatement($Renderer);

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

        $cr = $this->_copyrightStatement($Renderer);

        $this->assertStringNotContainsString($find_deriv_cr, $cr);

        // Guard retained from the original test: under config caching a runtime config()
        // override may not reach the renderer.
        if(!config('app.config_cache')) {
            $this->assertStringContainsString($find_bss_url, $cr);
            $this->assertStringContainsString($find_app_url, $cr);
        }

        // Add a deriv copyright statement
        config(['download.derivative_copyright_statement' => $test_deriv_cr]);
        $this->assertEquals($test_deriv_cr, config('download.derivative_copyright_statement'));

        $cr = $this->_copyrightStatement($Renderer);

        $this->assertStringContainsString($find_deriv_cr, $cr);
        $this->assertStringContainsString($find_bss_url, $cr);
        $this->assertStringContainsString($find_app_url, $cr);

        // Revert to cached
        config([
            'download.derivative_copyright_statement' => $cache_deriv_cr,
            'download.bss_link_enable' => $cache_bss_link,
            'download.app_link_enable' => $cache_app_link,
        ]);

        // The one real render: proves the statement asserted on above is exactly what lands on
        // line 4 of the rendered file, so the direct calls above are testing the right string.
        $this->assertTrue( $Renderer->render(TRUE, TRUE) ); // Force render
        $file_path = $Renderer->getRenderFilePath();
        $this->assertFileExists($file_path);
        $file_data = file($file_path);

        $this->assertIsArray($file_data);
        $this->assertArrayHasKey(3, $file_data);

        $cr = str_getcsv($file_data[3], escape: $this->csvesc)[0];

        $this->assertEquals($this->_copyrightStatement($Renderer), $cr,
            'The rendered copyright line must match _getCopyrightStatement()');

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

    /**
     * The SQLite renderer batches a whole chunk of verses into one INSERT, so the batch has to
     * fit the bound-variable ceiling of the SQLite build writing the file - 999 before 3.32,
     * 32766 from 3.32 on. The chunk size is therefore derived from the render connection rather
     * than hard-coded.
     */
    public function testSqliteChunkSizeFitsTheBoundVariableCeiling() 
    {
        $Renderer = $this->_scratchSqliteRenderer();

        $Start = new \ReflectionMethod($Renderer, '_renderStart');
        $this->assertTrue($Start->invoke($Renderer));

        $connection = $Renderer->renderConnectionName();
        $chunk_size = (new \ReflectionProperty($Renderer, 'chunk_size'))->getValue($Renderer);

        // 4 columns are bound per verse row (include_book_name is FALSE on this renderer).
        $columns = 4;
        $max     = \App\Helpers::getMaxBoundVariables($connection);

        $this->assertGreaterThan(0, $chunk_size);
        $this->assertLessThanOrEqual($max, $chunk_size * $columns);
        $this->assertEquals(\App\Helpers::getInsertChunkSize($columns, $connection), $chunk_size);

        // Modern SQLite has headroom for the full batch; only pre-3.32 builds get less.
        if($max >= 1000 * $columns) {
            $this->assertEquals(1000, $chunk_size);
        }

        $Renderer->cleanUp();
    }

    /**
     * RenderManager catches a per-Bible render failure and carries on with the next Bible, so a
     * throw mid-render must not leave the render transaction open - it would hold a write lock
     * and a journal file for the rest of the process.
     */
    public function testSqliteRollsBackTheRenderTransactionWhenAChunkFails() 
    {
        $Renderer = $this->_scratchSqliteRenderer(TRUE);

        try {
            $Renderer->render(TRUE);
            $this->fail('Expected the failing verse chunk to propagate out of render()');
        }
        catch(\RuntimeException $e) {
            $this->assertEquals('Simulated chunk insert failure', $e->getMessage());
        }

        $connection = $Renderer->renderConnectionName();

        $this->assertEquals(0, \DB::connection($connection)->transactionLevel(), 'Render transaction was left open');

        // No lock survives the rollback, so the file is still writable.
        \DB::connection($connection)->table('verses')->insert([
            'book' => 1, 'chapter' => 1, 'verse' => 1, 'text' => 'post-rollback write',
        ]);

        $this->assertEquals(1, \DB::connection($connection)->table('verses')->count());

        $Renderer->cleanUp();
    }

    /**
     * A SQLite3 renderer writing to a scratch file instead of the shared rendered/ directory,
     * optionally failing on its first verse chunk.
     */
    private function _scratchSqliteRenderer(bool $fail_on_chunk = FALSE) 
    {
        $Renderer = new class('kjv') extends \App\Renderers\SQLite3 {
            public $scratch_path;
            public $fail_on_chunk = FALSE;

            public function getRenderFilePath($create_dir = FALSE, $relative = false) 
            {
                return $this->scratch_path;
            }

            public function renderConnectionName() 
            {
                return $this->getDbConnectionName('render');
            }

            public function cleanUp() 
            {
                \DB::disconnect($this->renderConnectionName());

                foreach(['', '-journal', '-wal', '-shm'] as $suffix) {
                    if(is_file($this->scratch_path . $suffix)) {
                        unlink($this->scratch_path . $suffix);
                    }
                }
            }

            protected function _renderVerseChunk() 
            {
                if($this->fail_on_chunk) {
                    throw new \RuntimeException('Simulated chunk insert failure');
                }

                parent::_renderVerseChunk();
            }
        };

        $Renderer->scratch_path  = tempnam(sys_get_temp_dir(), 'bss_render_') . '.sqlite';
        $Renderer->fail_on_chunk = $fail_on_chunk;

        return $Renderer;
    }

    /**
     * Read a renderer's copyright block without widening its visibility in production code.
     * No setAccessible() call: reflection ignores visibility from PHP 8.1, and the method is
     * deprecated in 8.5.
     */
    private function _copyrightStatement($Renderer) 
    {
        $Method = new \ReflectionMethod($Renderer, '_getCopyrightStatement');

        return $Method->invoke($Renderer, TRUE, '  ');
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
