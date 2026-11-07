<?php

namespace Tests\Feature\Controllers;

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


    /**
     * Puts the given configs back as they are now, in tearDown, however the test ends
     *
     * @param array<int, string> $keys
     * @return array<string, mixed> All configs as they are now
     */
    protected function restoreConfigsLater(array $keys): array
    {
        $cache = ConfigManager::getConfigs();
        $this->deferCleanup(fn() => ConfigManager::setGlobalConfigs(array_intersect_key($cache, array_flip($keys))));

        return $cache;
    }

    /**
     * Puts a post's content back as it is now, in tearDown, however the test ends.  The raw
     * column, not the sanitizing accessor's output, so the stored document is left untouched.
     */
    protected function restorePostLater(Post $Post): void
    {
        $content = $Post->getRawOriginal('content');
        $this->deferCleanup(fn() => Post::where('id', $Post->id)->update(['content' => $content]));
    }

    public function testSoftConfigs() 
    {
        $this->_init();

        $cache = $this->restoreConfigsLater(['app.client_url', 'bss.daily_access_limit', 'bss.defaults.bible', 'app.phone_home']);
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
        $this->restorePostLater($Post);
        $User = User::find(1);
        $this->assertGreaterThanOrEqual(100, $User->access_level);

        $response = $this->actingAs($User)
                            ->post('/admin/tos', ['content' => 'Test TOS']);

        $response->assertStatus(302); // this redirects back to GET /admin/tos
        
        $Post->refresh();
        $this->assertEquals('Test TOS', $Post->content);
    }   

    public function testPrivacySave()
    {
        $this->_init();

        $Post = Post::where('key', 'privacy')->firstOrFail();
        $this->restorePostLater($Post);
        $User = User::find(1);
        $this->assertGreaterThanOrEqual(100, $User->access_level);

        $response = $this->actingAs($User)
                            ->post('/admin/privacy', ['content' => 'Test Privacy Stmn']);

        $response->assertStatus(302); // this redirects back to GET /admin/privacy
        
        $Post->refresh();
        $this->assertEquals('Test Privacy Stmn', $Post->content);
    }    

    /**
     * `mail.sendmail` is a registered soft config whose value is handed to the
     * mail transport as a command line. The admin form renders it readonly, but
     * that is client-side only -- the server must reject it as well.
     */
    public function testSendmailPathCannotBeChangedOverHttp() 
    {
        $this->_init();

        $User = User::find(1);
        $this->assertGreaterThanOrEqual(100, $User->access_level);

        $original = $this->restoreConfigsLater(['mail.sendmail'])['mail.sendmail'];

        $response = $this->actingAs($User)
                            ->post('/admin/config', ['mail__sendmail' => '/tmp/pwned -bs']);

        $response->assertStatus(302);

        $this->assertEquals($original, ConfigManager::getConfigs()['mail.sendmail']);
        $this->assertNotEquals('/tmp/pwned -bs', config('mail.sendmail'));
    }

    /**
     * The block must be surgical: legitimate mail settings on the same form
     * still save.
     */
    public function testOtherMailConfigsStillSaveOverHttp() 
    {
        $this->_init();

        $User = User::find(1);
        $this->restoreConfigsLater(['mail.host']);

        $response = $this->actingAs($User)
                            ->post('/admin/config', ['mail__host' => 'smtp.testhost.com']);

        $response->assertStatus(302);
        $this->assertEquals('smtp.testhost.com', ConfigManager::getConfigs()['mail.host']);
    }
}
