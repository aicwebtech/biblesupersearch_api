<?php

namespace Tests\Feature\Console;

use App\Console\Commands\TestCleanup;
use App\Models\ApiKey;
use App\Models\Bible;
use App\Models\Cache;
use App\Models\IpAccess;
use App\Models\Volume;
use App\Models\VolumeTypes\Strongs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * test:cleanup removes stale test leftovers and nothing else.
 *
 * Every fixture here is made by this test, and removed again in tearDown whatever the sweep did.
 * The sweep runs with --only: the full sweep also removes the throwaway languages, which have no
 * creation time to tell a leftover from a fixture another parallel worker is using right now.
 */
class TestCleanupTest extends TestCase
{
    /**
     * Categories safe to sweep while other tests run: everything with a creation time
     */
    private const SAFE = 'bibles,volumes,fixture-tables,render-log,api-keys,ip-access,cache';

    private string $stale = '';

    public function setUp(): void
    {
        parent::setUp();

        $this->stale = date('Y-m-d H:i:s', time() - 7200);
    }

    protected function makeVolume(bool $stale): Strongs
    {
        $module = $this->fixtureModule('vol_fixture_');
        $this->deferCleanup(fn() => $this->removeVolumeModuleFixture('strongs', $module));

        $Volume = new Strongs();
        $Volume->module    = $module;
        $Volume->name      = 'Cleanup Fixture ' . $module;
        $Volume->shortname = 'Cln ' . $module;
        $Volume->language  = 'en';
        $Volume->save();
        $this->assertTrue($Volume->install(TRUE));

        if($stale) {
            Volume::where('id', $Volume->id)->update(['created_at' => $this->stale]);
        }

        return $Volume;
    }

    protected function makeBible(bool $stale): string
    {
        $module = $this->fixtureModule('offlag_');
        $this->deferCleanup(fn() => $this->removeBibleModuleFixture($module));

        $Bible = new Bible();
        $Bible->module     = $module;
        $Bible->name       = 'Cleanup Fixture ' . $module;
        $Bible->shortname  = $module;
        $Bible->year       = '2000';
        $Bible->lang_short = 'en';
        $Bible->save();

        if($stale) {
            Bible::where('id', $Bible->id)->update(['created_at' => $this->stale]);
        }

        return $module;
    }

    protected function makeKey(bool $stale): string
    {
        $hash = ApiKey::generateKeyHash();
        $this->deferCleanup(fn() => ApiKey::withTrashed()->where('key', $hash)->forceDelete());

        DB::table('api_keys')->insert([
            'key' => $hash, 'user_id' => TestCleanup::API_KEY_USER_ID, 'access_level_id' => 2,
            'created_at' => $stale ? $this->stale : date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $hash;
    }

    protected function makeCacheRow(bool $stale): string
    {
        $hash = TestCleanup::CACHE_HASH_PREFIX . bin2hex(random_bytes(4));
        $this->deferCleanup(fn() => Cache::where('hash', $hash)->delete());

        DB::table('cache')->insert([
            'hash' => $hash, 'hash_long' => $hash . '_long', 'form_data' => '{}', 'preserve' => 0,
            'created_at' => $stale ? $this->stale : date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $hash;
    }

    protected function makeIpAccess(bool $stale): string
    {
        $ip = random_int(256, 999) . '.' . random_int(1, 255) . '.' . random_int(1, 255) . '.' . random_int(1, 255);
        $this->deferCleanup(fn() => $this->removeIpAccessFixture($ip));

        $Access = IpAccess::findOrCreateByIpOrDomain($ip);

        if($stale && Schema::hasColumn('ip_access', 'created_at')) {
            IpAccess::where('id', $Access->id)->update(['created_at' => $this->stale]);
        }

        return $ip;
    }

    protected function makeTable(string $table): string
    {
        $this->deferCleanup(fn() => Schema::dropIfExists($table));

        Schema::create($table, function($Table) {
            $Table->increments('id');
        });

        return $table;
    }

    public function testStaleLeftoversAreRemovedAndFreshFixturesKept(): void
    {
        $StaleVolume = $this->makeVolume(TRUE);
        $FreshVolume = $this->makeVolume(FALSE);
        $stale_table = $StaleVolume->content()->getTable();
        $fresh_table = $FreshVolume->content()->getTable();

        // A content table whose volume row is gone is a leftover, whatever its age
        $orphan_module = $this->fixtureModule('vol_fixture_');
        $this->deferCleanup(fn() => $this->removeVolumeModuleFixture('strongs', $orphan_module));
        $orphan = $this->makeTable(\App\Models\VolumeContent\StrongsContent::getContentTableName('strongs', $orphan_module));

        $stale_bible = $this->makeBible(TRUE);
        $fresh_bible = $this->makeBible(FALSE);
        $stale_key   = $this->makeKey(TRUE);
        $fresh_key   = $this->makeKey(FALSE);
        $stale_hash  = $this->makeCacheRow(TRUE);
        $fresh_hash  = $this->makeCacheRow(FALSE);
        $stale_ip    = $this->makeIpAccess(TRUE);
        $fresh_ip    = $this->makeIpAccess(FALSE);

        // Fixture tables: one whose worker has exited (no pid this high), one of a live process
        $dead_table = $this->makeTable('extras_dump_cleanup_4194999');
        $live_table = $this->makeTable($this->fixtureTableName('extras_dump_cleanup'));

        $this->artisan('test:cleanup', ['--only' => self::SAFE])->assertExitCode(0);

        $this->assertNull(Volume::find($StaleVolume->id), 'Stale volume kept');
        $this->assertFalse(Schema::hasTable($stale_table), 'Stale volume table kept');
        $this->assertFalse(Schema::hasTable($orphan), 'Orphan content table kept');
        $this->assertNull(Bible::findByModule($stale_bible), 'Stale Bible kept');
        $this->assertSame(0, ApiKey::withTrashed()->where('key', $stale_key)->count(), 'Stale key kept');
        $this->assertSame(0, Cache::where('hash', $stale_hash)->count(), 'Stale cache row kept');
        $this->assertFalse(Schema::hasTable($dead_table), 'Dead worker\'s fixture table kept');

        if(Schema::hasColumn('ip_access', 'created_at')) {
            $this->assertSame(0, IpAccess::where('ip_address', $stale_ip)->count(), 'Stale IP bucket kept');
            $this->assertSame(1, IpAccess::where('ip_address', $fresh_ip)->count(), 'Fresh IP bucket removed');
        }

        $this->assertNotNull(Volume::find($FreshVolume->id), 'Fresh volume removed');
        $this->assertTrue(Schema::hasTable($fresh_table), 'Fresh volume table removed');
        $this->assertNotNull(Bible::findByModule($fresh_bible), 'Fresh Bible removed');
        $this->assertSame(1, ApiKey::where('key', $fresh_key)->count(), 'Fresh key removed');
        $this->assertSame(1, Cache::where('hash', $fresh_hash)->count(), 'Fresh cache row removed');
        $this->assertTrue(Schema::hasTable($live_table), 'Live worker\'s fixture table removed');
    }

    public function testDryRunRemovesNothing(): void
    {
        $Volume = $this->makeVolume(TRUE);
        $hash   = $this->makeCacheRow(TRUE);

        $code = 'qqd';
        $this->deferCleanup(fn() => $this->removeLanguageFixture($code));
        $this->createLanguageFixture($code, 'Cleanup Fixture Language');

        $this->artisan('test:cleanup', ['--dry-run' => TRUE, '--only' => 'volumes,cache,languages'])
            ->expectsOutputToContain($Volume->module)
            ->expectsOutputToContain($hash)
            ->expectsOutputToContain('Languages: ' . $code)
            ->assertExitCode(0);

        $this->assertNotNull(Volume::find($Volume->id));
        $this->assertSame(1, Cache::where('hash', $hash)->count());
        $this->assertSame(1, \App\Models\Language::where('code', $code)->count());
    }

    public function testUnknownCategoryIsRefused(): void
    {
        $this->artisan('test:cleanup', ['--only' => 'cache,nonsense'])->assertExitCode(1);
    }

    public function testRefusesInProduction(): void
    {
        $hash = $this->makeCacheRow(TRUE);
        $env  = $this->app['env'];
        $this->deferCleanup(fn() => $this->app['env'] = $env);

        $this->app['env'] = 'production';

        $this->artisan('test:cleanup', ['--only' => 'cache'])->assertExitCode(1);
        $this->assertSame(1, Cache::where('hash', $hash)->count());
    }
}
