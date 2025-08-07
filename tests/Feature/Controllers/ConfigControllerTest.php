<?php

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Post;
use App\User;
use App\ConfigManager;

// This class tests OptionManager, along with some option related controller actions.
class ConfigControllerTest extends TestCase
{
    
    protected $run_in_production = false;

    protected function _init() 
    {
        if(!$this->run_in_production && config('app.env') == 'production') {
            $this->markTestSkipped('This test skipped in production');
        }

        if(config('app.config_cache') && config('app.url') != config('app.url_env')) {
            $this->markTestSkipped('This test skipped when config caching is enabled');
        }
    }


    public function testSoftConfigs() 
    {
        $this->_init();

        $cache = ConfigManager::getConfigs();
        $test_post = [];
        $User = User::find(1);
        $this->assertGreaterThanOrEqual(100, $User->access_level);

        $test_config = [
            'app.client_url' => 'http://testurl.com',   // String
            'bss.daily_access_limit' => 456,            // Int
            // 'download.tab_enable' => 0,                 // Bool
            'bss.defaults.bible' => $cache['bss.defaults.bible'], // keep the same: this is intentional
            'app.phone_home' => $cache['app.phone_home'] ? 0 : 1, // Toggle a bool
        ];

        foreach($test_config as $key => $value) {
            $key = str_replace('.', '__', $key);
            $test_post[$key] = $value;
        }

        $response = $this->actingAs($User)
                            ->post('/admin/config', $test_post);

        $response->assertStatus(302); // this redirects back to GET /admin/tos

        $conf = ConfigManager::getConfigs();

        foreach($test_config as $key => $value) {
            $this->assertEquals($value, $conf[$key]);
        }

        ConfigManager::setGlobalConfigs($cache); // revert configs

        $configs3 = ConfigManager::getConfigs();

        foreach($test_config as $key => $value) {
            $this->assertEquals($cache[$key], $configs3[$key]);
        }
    }

    public function testTosSave()
    {
        $this->_init();

        $Post = Post::where('key', 'tos')->firstOrFail();
        $orig = $Post->content; // cache existing
        $User = User::find(1);
        $this->assertGreaterThanOrEqual(100, $User->access_level);

        $response = $this->actingAs($User)
                            ->post('/admin/tos', ['content' => 'Test TOS']);

        $response->assertStatus(302); // this redirects back to GET /admin/tos
        
        $Post->refresh();
        $this->assertEquals('Test TOS', $Post->content);

        $Post->content = $orig;
        $Post->save();
    }   

    public function testPrivacySave()
    {
        $this->_init();

        $Post = Post::where('key', 'privacy')->firstOrFail();
        $orig = $Post->content; // cache existing
        $User = User::find(1);
        $this->assertGreaterThanOrEqual(100, $User->access_level);

        $response = $this->actingAs($User)
                            ->post('/admin/privacy', ['content' => 'Test Privacy Stmn']);

        $response->assertStatus(302); // this redirects back to GET /admin/privacy
        
        $Post->refresh();
        $this->assertEquals('Test Privacy Stmn', $Post->content);

        $Post->content = $orig;
        $Post->save();
    }    

}
