<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use App\User;

class AdminControllerTest extends TestCase
{

    public function testBible()
    {
        $response = $this->get('/admin/bibles');
        $response->assertStatus(302); // Because unauth user will be redirected to login page

        $User = User::find(1);

        $response = $this->actingAs($User)
                            ->get('/admin/bibles');

        $response->assertStatus(200);
    }    

    public function testAdminLandsOnFeatures(): void
    {
        $User = User::find(1);

        $this->actingAs($User)->get('/admin')->assertRedirect('/admin/features');
        $this->actingAs($User)->get('/admin/main')->assertRedirect(route('admin.features.index'));
    }

    public function testVolumes()
    {
        $response = $this->get('/admin/volumes');
        $response->assertStatus(302); // Because unauth user will be redirected to login page

        $User = User::find(1);

        $response = $this->actingAs($User)
                            ->get('/admin/volumes');

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
