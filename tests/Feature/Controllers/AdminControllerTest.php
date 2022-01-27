<?php

//namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\User;
// use Tests\TestCase;

class AdminControllerTest extends TestCase
{
//     Route::get('/admin/debug', 'AdminController@debug')->name('admin.debug');
//     Route::get('/admin/update', 'AdminController@softwareUpdate')->name('admin.update')->middleware('install');
//     Route::get('/admin/uninstall', 'AdminController@uninstallPage')->name('admin.uninstall')->middleware('install');
//     Route::get('/admin/tos', 'Admin\PostConfigController@tos')->name('admin.tos')->middleware('install');
// Route::post('/admin/tos', 'Admin\PostConfigController@saveTos');
// Route::get('/admin/privacy', 'Admin\PostConfigController@privacy')->name('admin.privacy')->middleware('install');
// Route::post('/admin/privacy', 'Admin\PostConfigController@savePrivacy');

// Route::resource('/admin/bibles', 'Admin\BibleController', ['as' => 'admin']);

    public function testBible()
    {
        $response = $this->get('/admin/bibles');
        $response->assertStatus(302); // Because unauth user will be redirected to login page

        $User = User::find(1);

        $response = $this->actingAs($User)
                            ->get('/admin/bibles');

        $response->assertStatus(200);
    }    

    public function testDebug()
    {
        $response = $this->get('/admin/debug');
        $response->assertStatus(302); // Because unauth user will be redirected to login page

        // Note: We don't test with an authenticatd user here because will pull phpinfo into CLI when testing
    }    

    public function testUpdate()
    {
        $response = $this->get('/admin/update');
        $response->assertStatus(302); // Because unauth user will be redirected to login page

        $User = User::find(1);

        $response = $this->actingAs($User)
                            ->get('/admin/update');

        $response->assertStatus(200);
    }    

    public function testUninstall()
    {
        $response = $this->get('/admin/uninstall');
        $response->assertStatus(302); // Because unauth user will be redirected to login page

        $User = User::find(1);

        $response = $this->actingAs($User)
                            ->get('/admin/uninstall');

        $response->assertStatus(200);
    }   


    public function testConfig()
    {
        $response = $this->get('/admin/config');
        $response->assertStatus(302); // Because unauth user will be redirected to login page

        $User = User::find(1);

        $response = $this->actingAs($User)
                            ->get('/admin/config');

        $response->assertStatus(200);
    }    

    public function testTos()
    {
        $response = $this->get('/admin/tos');
        $response->assertStatus(302); // Because unauth user will be redirected to login page

        $User = User::find(1);

        $response = $this->actingAs($User)
                            ->get('/admin/tos');

        $response->assertStatus(200);
    }    

    public function testPrivacy()
    {
        $response = $this->get('/admin/privacy');
        $response->assertStatus(302); // Because unauth user will be redirected to login page

        $User = User::find(1);

        $response = $this->actingAs($User)
                            ->get('/admin/privacy');

        $response->assertStatus(200);
    }
}
