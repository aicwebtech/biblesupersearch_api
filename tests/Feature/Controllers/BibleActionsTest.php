<?php

// namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use App\User;
use App\Models\Bible;
use PHPUnit\Framework\Attributes\Depends;
// use Tests\TestCase;

/**
 * Tests of the BibleController
 */ 
class BibleActionsTest extends TestCase
{
    
    protected $quick_mode = TRUE;
    protected $User = NULL;

    // Note, the full files are removed from the official release to save space
    protected $files_full = [
        'csv'   => 'kjv_full.csv',
        'excel' => 'kjv_full.xlsx',
        'ods'   => 'kjv_full.ods', // DEAD DOG SLOW
    ];    

    protected $files_lite = [
        'csv'   => 'kjv_full.csv',  // No speed difference for CSV
        'excel' => 'kjv_min.xlsx',
        'ods'   => 'kjv_min.ods' ,
    ];

    protected $files = [];
    protected $UploadedFiles = [];

    public function __construct() 
    {
        parent::__construct(...func_get_args());
        $this->files = ($this->quick_mode) ? $this->files_lite : $this->files_full;
    }

    public function testInit() 
    {
        if(!$this->test_http) {
            $this->markTestSkipped('The .env config APP_TEST_HTTP must be true to run these tests.');
        }

        $User = User::find(1);

        $response = $this->actingAs($User)
                            ->withSession(['banned' => FALSE])
                            ->postJson('/admin/bibles');

        $response->assertStatus(200);

        $this->assertEquals('testing', config('app.env'));

        return ['User' => $User];
    }

    #[Depends('testInit')]
    public function testImportCheck(array $shared) 
    {
        $data = [
            'first_row_data' => 9,
            'col_A' => NULL,
            'col_B' => NULL,
            'col_C' => NULL,
            'col_D' => NULL,
            'col_E' => NULL,
            'col_F' => 'b',
            'col_G' => 'c',
            'col_H' => 'v',
            'col_I' => 't',
        ];

        $shared['import_settings'] = $data;
        $data = $this->_makeFakeImportTest('csv', $data);

        $response = $this->actingAs($shared['User'])
                            ->withSession(['banned' => FALSE])
                            ->postJson('/admin/bibles/importcheck', $data);

        $response->assertStatus(200)
                    ->assertJson([
                        'success' => TRUE,
                    ]);

        $shared['last_response'] = $response;
        $shared['importer'] = 'csv';

        return $shared;
    }

    #[Depends('testImportCheck')]
    public function testImport(array $shared) 
    {        
        $ts = time();

        $data = $shared['last_response']['bible'];
        $data['_file'] = $shared['last_response']['file'];
        $data['_importer'] = $shared['importer'];
        $data['_settings'] = json_encode($shared['import_settings']);
        $data['copyright_id'] = 1;
        $data['lang_short'] = 'en';
        $data['name'] = 'Test Bible ' . date('Y-m-d H:i:s', $ts);
        $data['shortname'] = 'Test ' . date('His', $ts);
        $data['year'] = date('Y', $ts);
        $shared['module'] = $data['module'] = 'test_bible_' . date('YmdHis', $ts);

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
        $shared['Bible'] = Bible::findByModule($shared['module']);
        return $shared;
    }

    #[Depends('testImport')]
    public function testTest(array $shared) 
    {

        $response = $this->actingAs($shared['User'])
                    ->withSession(['banned' => FALSE])
                    ->postJson('/admin/bibles/test/' . $shared['bible_id']);

        $response->assertStatus(200)
                    ->assertJson([
                        'success' => TRUE,
                    ]);

        return $shared;
    }    

    #[Depends('testTest')]
    public function testDisable(array $shared) 
    {

        $response = $this->actingAs($shared['User'])
                    ->withSession(['banned' => FALSE])
                    ->postJson('/admin/bibles/disable/' . $shared['bible_id']);

        $response->assertStatus(200)
                    ->assertJson([
                        'success' => TRUE,
                    ]);

        $shared['Bible']->refresh();
        $this->assertEquals(0, $shared['Bible']->enabled);

        return $shared;
    }    

    #[Depends('testDisable')]
    public function testEnable(array $shared) 
    {

        $response = $this->actingAs($shared['User'])
                    ->withSession(['banned' => FALSE])
                    ->postJson('/admin/bibles/enable/' . $shared['bible_id']);

        $response->assertStatus(200)
                    ->assertJson([
                        'success' => TRUE,
                    ]);

        $shared['Bible']->refresh();
        $this->assertEquals(1, $shared['Bible']->enabled);

        return $shared;
    }    

    #[Depends('testEnable')]
    public function testResearch(array $shared) 
    {

        $response = $this->actingAs($shared['User'])
                    ->withSession(['banned' => FALSE])
                    ->postJson('/admin/bibles/research/' . $shared['bible_id']);

        $response->assertStatus(200)
                    ->assertJson([
                        'success' => TRUE,
                    ]);

        $shared['Bible']->refresh();
        $this->assertEquals(1, $shared['Bible']->research);

        return $shared;
    }     

    #[Depends('testResearch')]
    public function testUnresearch(array $shared) 
    {

        $response = $this->actingAs($shared['User'])
                    ->withSession(['banned' => FALSE])
                    ->postJson('/admin/bibles/unresearch/' . $shared['bible_id']);

        $response->assertStatus(200)
                    ->assertJson([
                        'success' => TRUE,
                    ]);

        $shared['Bible']->refresh();
        $this->assertEquals(0, $shared['Bible']->research);

        return $shared;
    }    

    #[Depends('testUnresearch')]
    public function testExport(array $shared) 
    {
        $response = $this->actingAs($shared['User'])
                    ->withSession(['banned' => FALSE])
                    ->postJson('/admin/bibles/export/' . $shared['bible_id']);

        $response->assertStatus(200)
                    ->assertJson([
                        'success' => TRUE,
                    ]);

        $this->assertTrue($shared['Bible']->hasModuleFile());

        return $shared;
    }        

    #[Depends('testExport')]
    public function testUninstall(array $shared) 
    {

        $response = $this->actingAs($shared['User'])
                    ->withSession(['banned' => FALSE])
                    ->postJson('/admin/bibles/uninstall/' . $shared['bible_id']);

        $response->assertStatus(200)
                    ->assertJson([
                        'success' => TRUE,
                    ]);

        $shared['Bible']->refresh();
        $this->assertEquals(0, $shared['Bible']->installed);
        $this->assertNull($shared['Bible']->installed_at);

        return $shared;
    }     

    #[Depends('testUninstall')]
    public function testInstall(array $shared) 
    {

        $response = $this->actingAs($shared['User'])
                    ->withSession(['banned' => FALSE])
                    ->postJson('/admin/bibles/install/' . $shared['bible_id'], ['enable' => 1]);

        $response->assertStatus(200)
                    ->assertJson([
                        'success' => TRUE,
                    ]);

        $shared['Bible']->refresh();
        $this->assertEquals(1, $shared['Bible']->installed);
        $this->assertNotNull($shared['Bible']->installed_at);

        return $shared;
    }    

    #[Depends('testInstall')]
    public function testEdit(array $shared) 
    {
        // Attempt to READ the Bible data
        $get_resp = $this->actingAs($shared['User'])
                    ->withSession(['banned' => FALSE])
                    ->getJson('/admin/bibles/' . $shared['bible_id']);

        $get_resp->assertStatus(200)
                    ->assertJson([
                        'success' => TRUE,
                    ]);

        // Make some edits
        $bible = $get_resp['Bible'];

        $edits = [
            'name' => $bible['name'] . ' AND WONKY',
            'description' => 'And this one was edited',
            'year' => '1776',
            'rank' => 9999,
        ];

        $bible = array_replace($bible, $edits);

        $response = $this->actingAs($shared['User'])
                    ->withSession(['banned' => FALSE])
                    ->putJson('/admin/bibles/' . $shared['bible_id'], $bible);

        $response->assertStatus(200)
                    ->assertJson([
                        'success' => TRUE,
                    ]);

        $shared['Bible']->refresh();

        foreach($edits as $key => $value) {
            $this->assertEquals($value, $shared['Bible']->$key);
        }

        return $shared;
    }

    #[Depends('testEdit')]
    public function testUpdate(array $shared) 
    {

        $response = $this->actingAs($shared['User'])
                    ->withSession(['banned' => FALSE])
                    ->postJson('/admin/bibles/update/' . $shared['bible_id']);

        // Should NOT need an update, will return 422
        $response->assertStatus(422)
                    ->assertJson([
                        'success' => FALSE,
                    ]);

        return $shared;
    }    

    #[Depends('testUpdate')]
    public function testUpdateModule(array $shared) 
    {

        $response = $this->actingAs($shared['User'])
                    ->withSession(['banned' => FALSE])
                    ->postJson('/admin/bibles/meta/' . $shared['bible_id'], ['create_new' => 0]);

        $response->assertStatus(200)
                    ->assertJson([
                        'success' => TRUE,
                    ]);

        $shared['Bible']->refresh();

        return $shared;
    }        

    #[Depends('testUpdateModule')]
    public function testRevert(array $shared) 
    {
        $this->assertTrue(TRUE);

        // Since this is called after self::testUpdateModule, the metadata on the module file will match that on the Bible record

        $shared['Bible']->refresh();
        $orig = $shared['Bible']->attributesToArray();

        $edits = [
            'name' => 'TEST ' . time(),
            'description' => 'And this one was poorly edited and needs to be reverted',
            'year' => '1945',
            'rank' => 1234,
        ];

        $shared['Bible']->fill($edits);
        $shared['Bible']->save();

        foreach($edits as $key => $new_value) {
            $this->assertEquals($new_value, $shared['Bible']->$key);
            $this->assertNotEquals($orig[$key], $shared['Bible']->$key);
        }

        $response = $this->actingAs($shared['User'])
                    ->withSession(['banned' => FALSE])
                    ->postJson('/admin/bibles/revert/' . $shared['bible_id']);

        $response->assertStatus(200)
                    ->assertJson([
                        'success' => TRUE,
                    ]);

        $shared['Bible']->refresh();

        foreach($edits as $key => $new_value) {
            $this->assertNotEquals($new_value, $shared['Bible']->$key);
            $this->assertEquals($orig[$key], $shared['Bible']->$key);
        }

        return $shared;
    }        

    #[Depends('testRevert')]
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

        $this->expectException(Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $shared['Bible']->refresh(); // This will throw a ModelNotFoundException

        return $shared;
    }    

    protected function _makeFakeImportTest($importer, $data) 
    {
        $data['file'] = $this->_generateUploadedFile($importer);
        $data['importer'] = array_key_exists($importer, $this->files) ? $importer : NULL;  
        return $data;
    }

    protected function _generateUploadedFile($importer) 
    {
        $file_name = $this->files[ $importer ];
        $file_path = dirname(__FILE__) . '/../test_spreadsheets/' . $file_name;
        return new UploadedFile($file_path, $file_name, NULL, NULL, TRUE);
    }
}
