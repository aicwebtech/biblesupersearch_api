<?php

namespace Tests\Feature\Import;

use Tests\TestCase;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use App\Engine;
use App\ImportManager;
use PHPUnit\Framework\Attributes\Depends;

class Base extends TestCase 
{
    protected $file_name = NULL;
    protected $importer = NULL;
    protected $run_in_production = false;

    protected function _init() 
    {
        if(!$this->run_in_production && config('app.env') == 'production') {
            $this->markTestSkipped('This test skipped in production');
        }
    }

    public function testInit() 
    {
        $this->_init();

        $this->assertNotNull($this->file_name);
        $this->assertNotNull($this->importer);
        $User = \App\User::find(1);
        $this->assertGreaterThanOrEqual(100, $User->access_level);
        return ['User' => $User];
    }

    #[Depends('testInit')]
    public function testImportCheck(array $shared) 
    {
        if(config('app.config_cache') && config('app.url') != config('app.url_env')) {
            $this->markTestSkipped('This test skipped when config caching is enabled');
        }

        $data = [];

        $data = $this->_makeFakeImportTest($data);

        $response = $this->actingAs($shared['User'])
                            ->withSession(['banned' => FALSE])
                            ->postJson('/admin/bibles/importcheck', $data);

        $response->assertStatus(200)
                    ->assertJson([
                        'success' => TRUE,
                    ]);

        $shared['last_response'] = $response;
        $shared['importer'] = $data['importer'];
        $shared['import_settings'] = $data;

        return $shared;
    }

    #[Depends('testImportCheck')]
    public function testImport(array $shared) 
    {        
        $ts_u = microtime(TRUE);
        $ts = (int) $ts_u;
        $us =       ($ts_u - $ts) * 1000000;
        $us = (int) $us;

        $data = $shared['last_response']['bible'];
        $data['_file'] = $shared['last_response']['file'];
        $data['_importer'] = $shared['importer'];
        $data['_settings'] = json_encode($shared['import_settings']);
        $data['copyright_id'] = 1;
        $data['lang_short'] = 'en';
        $data['name'] = 'Test Bible ' . date('Y-m-d H:i:s', $ts) . ' ' . $us;
        $data['shortname'] = 'Test ' . date('His', $ts) . ' ' . $us;
        $data['year'] = date('Y', $ts);
        $shared['module'] = $data['module'] = 'test_bible_' . date('YmdHis', $ts) . '_' . $us;

        $response = $this->actingAs($shared['User'])
                            ->withSession(['banned' => FALSE])
                            ->postJson('/admin/bibles/import', $data);

        $response->assertStatus(200)
                    ->assertJson([
                        'success' => TRUE,
                    ]);

        unset($shared['last_response']);
        unset($shared['import_settings']);
        $shared['bible_id'] = $response['bible']['id'];
        $shared['Bible'] = \App\Models\Bible::findByModule($shared['module']);
        return $shared;
    }

    #[Depends('testImport')]
    public function testTest(array $shared) 
    {
        $module = $shared['Bible']->module;

        $response = $this->actingAs($shared['User'])
                    ->withSession(['banned' => FALSE])
                    ->postJson('/admin/bibles/test/' . $shared['bible_id']);

        $response->assertStatus(200)
                    ->assertJson([
                        'success' => TRUE,
                    ]);

        $Verses = $shared['Bible']->verses();
        $this->assertTrue( \Schema::hasTable($Verses->getTable()), 'No table for module: ' . $module . ', table:' . $Verses->getTable() );
        $verses_class_static = \App\Models\Bible::getVerseClassNameByModule($module);
        $verses_class = $shared['Bible']->getVerseClassName();
        $this->assertInstanceOf('App\Models\Bible', $shared['Bible']);
        $this->assertEquals($verses_class_static, $verses_class, 'Static and dynamic verses classes do not match.');

        // Grab a few verses from the database
        $verses = $Verses->orderBy('id', 'asc')->take(10)->get();
        $this->assertCount(10, $verses, $module . ' has empty table');
        $this->assertTrue(in_array($verses[0]->book, [1, 40]), 'Test verese did not come from Genesis or Matthew');
        $this->assertEquals(1, $verses[0]->id, $module . ' verses are misnumbered');
        $this->assertNotEmpty($verses[0]->text);

        return $shared;
    }    

    #[Depends('testTest')]
    public function testDelete(array $shared) 
    {

        $response = $this->actingAs($shared['User'])
                    ->withSession(['banned' => FALSE])
                    ->postJson('/admin/bibles/delete/' . $shared['bible_id']);

        $response->assertStatus(200)
                    ->assertJson([
                        'success' => TRUE,
                    ]);

        $this->assertFalse($shared['Bible']->hasModuleFile());

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $shared['Bible']->refresh(); // This will throw a ModelNotFoundException

        return $shared;
    }    


    protected function _makeFakeImportTest($data) 
    {
        $data['file'] = $this->_generateUploadedFile($this->file_name);
        $data['importer'] = $this->importer;
        return $data;
    }

    protected function _generateUploadedFile($file_name) 
    {
        $file_path = dirname(__FILE__) . '/' . $file_name;
        return new UploadedFile($file_path, $file_name, NULL, NULL, TRUE);
    }
}
