<?php

namespace App\Console\Commands;

use App\Models\ApiIpKeyCount;
use App\Models\ApiKey;
use App\Models\ApiKeyAccessLog;
use App\Models\Bible;
use App\Models\Cache;
use App\Models\IpAccess;
use App\Models\Language;
use App\Models\LanguageAttr;
use App\Models\RenderLog;
use App\Models\Volume;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes what PHPUnit tests leave behind when they die before their own cleanup runs.
 *
 * Every test removes its own fixtures in tearDown (see Tests\TestCase::deferCleanup()), which
 * PHPUnit runs after a failure or an error.  Nothing runs after a fatal error, running out of
 * memory, a timeout, Ctrl-C or a crashed paratest worker, though.  This sweeps for those
 * leftovers by the naming conventions the tests use, and only those: nothing else is touched.
 *
 * A fixture is only removed once it is older than --stale minutes, so a test running right now
 * in another process keeps its own.  The exceptions are the ones with no creation time to go by:
 * tables whose owning row is gone (only ever true after a removal), fixture tables whose worker
 * process has exited, and the throwaway languages.  Do not run this while a test run is in
 * progress; the PHPUnit extension runs it once, before any test starts.
 *
 * Refuses to run in production.
 */
class TestCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:cleanup
                            {--stale=60 : Only remove fixtures created at least this many minutes ago}
                            {--only= : Comma separated categories to sweep (default: all), see SWEEPS}
                            {--dry-run : List what would be removed without removing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removes data left behind by PHPUnit tests that died before their own cleanup ran';

    /**
     * Module prefixes of throwaway Bibles
     */
    public const BIBLE_PREFIXES = ['test_bible_', 'offlag_', 'race_', 'prov_', 'containment_test_', 'bss279', 'bobs_test_bible_'];

    /**
     * Module prefixes of throwaway volumes
     */
    public const VOLUME_PREFIXES = [
        'vol_api_', 'vol_broken_', 'vol_ctrl_', 'vol_fixture_', 'vol_langcfg_', 'vol_legacy_',
        'vol_module_', 'vol_resolve_', 'vol_strongs_', 'zz_import_',
    ];

    /**
     * Throwaway language codes: qq? sits in ISO 639's local-use range, so no real language has one
     */
    public const LANGUAGE_PATTERN = '/^qq[a-z]$/';

    /**
     * Tests\TestCase::fixtureTableName() tables: purpose, then the worker's process id
     */
    public const FIXTURE_TABLE_PATTERN = '/^extras_dump_[a-z_]+_(\d+)$/';

    /**
     * Tests\TestCase::fixtureDomain() domains of the IpAccess tests
     */
    public const FIXTURE_DOMAIN_PATTERN = '/^(www\.)?(testdomaincustomlimit|testsamedomain)\d+\.com$/';

    /**
     * Address RenderManagerTest logs its renders under; not a valid IP, so never a real one
     */
    public const RENDER_LOG_IP = '275.999.123.456';

    /**
     * user_id of the keys KeyAccessTest creates (Tests\Feature\Models\KeyAccessTest::TEST_KEY_USER_ID)
     */
    public const API_KEY_USER_ID = -1;

    /**
     * Username prefixes of throwaway users
     */
    public const USER_PREFIXES = ['install_guard_', 'reset_fixture_'];

    /**
     * Prefix of CacheTest's hashes.  Real hashes are hex, so never start with it.
     */
    public const CACHE_HASH_PREFIX = 'test_';

    /**
     * Documentation address ranges (RFC 5737) the IpAccess tests use: never a real client
     */
    public const TEST_NETS = ['192.0.2.', '198.51.100.', '203.0.113.'];

    /**
     * Category option name => [report label, method]
     */
    public const SWEEPS = [
        'bibles'          => ['Bibles', 'sweepBibles'],
        'volumes'         => ['Volumes', 'sweepVolumes'],
        'languages'       => ['Languages', 'sweepLanguages'],
        'fixture-tables'  => ['Fixture tables', 'sweepFixtureTables'],
        'render-log'      => ['Render log', 'sweepRenderLog'],
        'api-keys'        => ['API keys', 'sweepApiKeys'],
        'ip-access'       => ['IP access', 'sweepIpAccess'],
        'cache'           => ['Cache', 'sweepCache'],
        'users'           => ['Users', 'sweepUsers'],
        'module-files'    => ['Module files', 'sweepStrandedModuleFiles'],
        'test-databases'  => ['Test databases', 'sweepTestDatabases'],
    ];

    protected string $cutoff = '';
    protected bool $dry_run = FALSE;

    /**
     * Removed (or, on a dry run, found) items per category
     *
     * @var array<string, array<int, string>>
     */
    protected array $found = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if(app()->environment('production')) {
            $this->error('test:cleanup does not run in production.');
            return static::FAILURE;
        }

        $this->cutoff  = date('Y-m-d H:i:s', time() - max((int) $this->option('stale'), 0) * 60);
        $this->dry_run = (bool) $this->option('dry-run');
        $this->found   = [];
        $failed        = FALSE;

        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));

        if(array_diff($only, array_keys(static::SWEEPS))) {
            $this->error('Unknown category: ' . implode(', ', array_diff($only, array_keys(static::SWEEPS))) . '.  Known: ' . implode(', ', array_keys(static::SWEEPS)));
            return static::FAILURE;
        }

        $sweeps = $only ? array_intersect_key(static::SWEEPS, array_flip($only)) : static::SWEEPS;

        foreach($sweeps as [$category, $method]) {
            $this->found[$category] = [];

            try {
                $this->$method($category);
            }
            catch(\Throwable $e) {
                $failed = TRUE;
                $this->warn($category . ': ' . $e->getMessage());
            }
        }

        $this->report();

        return $failed ? static::FAILURE : static::SUCCESS;
    }

    /**
     * Records an item and, unless this is a dry run, removes it
     *
     * @param string $category
     * @param string $label What is removed, for the report
     * @param callable $remove
     */
    protected function remove(string $category, string $label, callable $remove): void
    {
        $this->found[$category][] = $label;

        if(!$this->dry_run) {
            $remove();
        }
    }

    protected function report(): void
    {
        $total = array_sum(array_map('count', $this->found));

        if($this->dry_run || $this->output->isVerbose()) {
            foreach($this->found as $category => $labels) {
                foreach($labels as $label) {
                    $this->line('  ' . $category . ': ' . $label);
                }
            }
        }

        $verb = $this->dry_run ? 'Would remove' : 'Removed';

        if($total == 0) {
            $this->info('No test leftovers found.');
            return;
        }

        $counts = array_filter(array_map('count', $this->found));
        $this->info($verb . ' ' . $total . ' test leftover(s): ' . implode(', ', array_map(
            fn($category, $count) => $category . ' ' . $count,
            array_keys($counts),
            $counts
        )));
    }

    protected function sweepBibles(string $category): void
    {
        $modules = [];

        foreach(Bible::where('created_at', '<', $this->cutoff)->get(['id', 'module']) as $Bible) {
            if(static::hasPrefix($Bible->module, static::BIBLE_PREFIXES)) {
                $modules[] = $Bible->module;
                $this->remove($category, $Bible->module, fn() => $this->removeBible($Bible->module));
            }
        }

        // Verses tables whose Bible row is gone
        $live = Bible::pluck('module')->all();

        foreach($this->tables() as $table) {
            if(!str_starts_with($table, 'verses_')) {
                continue;
            }

            $module = substr($table, strlen('verses_'));

            if(static::hasPrefix($module, static::BIBLE_PREFIXES) && !in_array($module, $live, TRUE) && !in_array($module, $modules, TRUE)) {
                $this->remove($category, 'table ' . $table, fn() => Schema::dropIfExists($table));
            }
        }
    }

    protected function removeBible(string $module): void
    {
        Bible::where('module', $module)->delete();
        Schema::dropIfExists('verses_' . $module);

        foreach([Bible::getModulePath(), Bible::getUnofficialModulePath()] as $dir) {
            if(is_file($dir . $module . '.zip')) {
                unlink($dir . $module . '.zip');
            }
        }
    }

    protected function sweepVolumes(string $category): void
    {
        $removed = [];

        foreach(Volume::where('created_at', '<', $this->cutoff)->get() as $Volume) {
            if(static::hasPrefix($Volume->module, static::VOLUME_PREFIXES)) {
                $removed[] = $Volume->type . ':' . $Volume->module;
                $this->remove($category, $Volume->type . ' ' . $Volume->module, fn() => $this->removeVolume($Volume));
            }
        }

        // Content tables whose volume row is gone, and stale content table backups
        $live   = Volume::get(['type', 'module'])->map(fn($Volume) => $Volume->type . ':' . $Volume->module)->all();
        $tables = $this->tables();

        foreach(array_keys(Volume::getTypes()) as $type) {
            $content_class = Volume::getContentClassName($type);

            if(!$content_class) {
                continue;
            }

            $prefix = $content_class::getContentTableName($type, '');

            foreach($tables as $table) {
                $module = str_starts_with($table, $prefix) ? substr($table, strlen($prefix)) : NULL;

                if($module === NULL || !static::hasPrefix($module, static::VOLUME_PREFIXES)) {
                    continue;
                }

                if(!in_array($type . ':' . $module, $live, TRUE) && !in_array($type . ':' . $module, $removed, TRUE)) {
                    $this->remove($category, 'table ' . $table, fn() => Schema::dropIfExists($table));
                }
            }
        }

        $backup_prefix = \App\Models\VolumeContent\ContentBase::backupTableName('');
        $backup_prefix = substr($backup_prefix, 0, strpos($backup_prefix, 'bak_') + 4);

        foreach($tables as $table) {
            // A backup only exists while a replace import runs: one with no creation time to
            // check (SQLite) may belong to an import in progress, so it is left alone
            if(str_starts_with($table, $backup_prefix) && $this->tableCreatedBeforeCutoff($table) === TRUE) {
                $this->remove($category, 'table ' . $table, fn() => Schema::dropIfExists($table));
            }
        }
    }

    protected function removeVolume(Volume $Volume): void
    {
        $content_class = Volume::getContentClassName($Volume->type);

        if($content_class) {
            $table = $content_class::getContentTableName($Volume->type, $Volume->module);
            Schema::dropIfExists($table);
            Schema::dropIfExists($content_class::backupTableName($table));
        }

        foreach([Volume::getModulePath($Volume->type), Volume::getUnofficialModulePath($Volume->type)] as $dir) {
            $path = $dir . Volume::moduleFileName($Volume->type, $Volume->module);

            if(is_file($path)) {
                unlink($path);
            }
        }

        $Volume->forceDelete();
    }

    /**
     * The languages table has no timestamps, so these are removed whatever their age
     */
    protected function sweepLanguages(string $category): void
    {
        $codes = [];

        foreach(Language::pluck('code')->all() as $code) {
            if(preg_match(static::LANGUAGE_PATTERN, (string) $code)) {
                $codes[] = $code;
            }
        }

        foreach(LanguageAttr::distinct()->pluck('code')->all() as $code) {
            if(preg_match(static::LANGUAGE_PATTERN, (string) $code)) {
                $codes[] = $code;
            }
        }

        foreach($this->tables() as $table) {
            if(str_starts_with($table, 'books_') && preg_match(static::LANGUAGE_PATTERN, substr($table, strlen('books_')))) {
                $codes[] = substr($table, strlen('books_'));
            }
        }

        foreach(array_unique($codes) as $code) {
            $this->remove($category, $code, function() use ($code) {
                LanguageAttr::where('code', $code)->delete();
                Language::where('code', $code)->delete();
                Schema::dropIfExists('books_' . $code);
            });
        }
    }

    /**
     * A fixture table is named after the worker that made it: once that process has exited,
     * the table is a leftover
     */
    protected function sweepFixtureTables(string $category): void
    {
        foreach($this->tables() as $table) {
            if(preg_match(static::FIXTURE_TABLE_PATTERN, $table, $matches) && !static::processIsRunning((int) $matches[1])) {
                $this->remove($category, 'table ' . $table, fn() => Schema::dropIfExists($table));
            }
        }
    }

    protected function sweepRenderLog(string $category): void
    {
        $count = RenderLog::withTrashed()->where('ip_address', static::RENDER_LOG_IP)->where('created_at', '<', $this->cutoff)->count();

        if($count) {
            $this->remove($category, $count . ' row(s) for ' . static::RENDER_LOG_IP, function() {
                RenderLog::withTrashed()->where('ip_address', static::RENDER_LOG_IP)->where('created_at', '<', $this->cutoff)->forceDelete();
            });
        }
    }

    protected function sweepApiKeys(string $category): void
    {
        foreach(ApiKey::withTrashed()->where('user_id', static::API_KEY_USER_ID)->where('created_at', '<', $this->cutoff)->get() as $Key) {
            $this->remove($category, 'key #' . $Key->id, function() use ($Key) {
                ApiIpKeyCount::where('key_id', $Key->id)->delete();
                ApiKeyAccessLog::where('key_id', $Key->id)->delete();
                $Key->forceDelete();
            });
        }
    }

    /**
     * Buckets for an address that cannot be a real client (the tests' deliberately invalid
     * ones, and the documentation ranges), and for the tests' fixture domains
     */
    protected function sweepIpAccess(string $category): void
    {
        $Query = IpAccess::query();

        if(Schema::hasColumn((new IpAccess)->getTable(), 'created_at')) {
            $Query->where('created_at', '<', $this->cutoff);
        }

        foreach($Query->get() as $Access) {
            $ip     = (string) $Access->ip_address;
            $domain = (string) $Access->domain;

            $is_test = ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) === FALSE)
                || static::hasPrefix($ip, static::TEST_NETS)
                || ($domain !== '' && preg_match(static::FIXTURE_DOMAIN_PATTERN, $domain));

            if($is_test) {
                $this->remove($category, $ip !== '' ? $ip : $domain, fn() => $Access->delete());
            }
        }
    }

    protected function sweepCache(string $category): void
    {
        foreach(Cache::where('hash', 'like', static::CACHE_HASH_PREFIX . '%')->where('created_at', '<', $this->cutoff)->get() as $Cache) {
            if(str_starts_with((string) $Cache->hash, static::CACHE_HASH_PREFIX)) {
                $this->remove($category, $Cache->hash, fn() => $Cache->delete());
            }
        }
    }

    protected function sweepUsers(string $category): void
    {
        foreach(User::where('created_at', '<', $this->cutoff)->get() as $User) {
            if(static::hasPrefix((string) $User->username, static::USER_PREFIXES)) {
                $this->remove($category, $User->username, fn() => $User->delete());
            }
        }
    }

    /**
     * BibleTest::testBibleMigrate moves the official KJV module file to bibles/unofficial and
     * back: a run killed in between leaves it there
     */
    protected function sweepStrandedModuleFiles(string $category): void
    {
        $official   = Bible::getModulePath() . 'kjv.zip';
        $unofficial = Bible::getUnofficialModulePath() . 'kjv.zip';

        if(!is_file($official) && is_file($unofficial)) {
            $this->remove($category, 'kjv.zip back to ' . Bible::getModulePath(TRUE), fn() => rename($unofficial, $official));
        }
    }

    /**
     * The per-process databases `php artisan test --parallel` creates for the current database
     * (<database>_test_<n>).  Left alone while a parallel run is under way, as they are its own.
     */
    protected function sweepTestDatabases(string $category): void
    {
        if(DB::connection()->getDriverName() !== 'mysql' || getenv('LARAVEL_PARALLEL_TESTING')) {
            return;
        }

        $database = DB::connection()->getDatabaseName();

        foreach(DB::select('SHOW DATABASES') as $row) {
            $name = (string) (array_values((array) $row)[0] ?? '');

            if(preg_match('/^' . preg_quote($database, '/') . '_test_\d+$/', $name)) {
                $this->remove($category, $name, fn() => DB::statement('DROP DATABASE `' . str_replace('`', '', $name) . '`'));
            }
        }
    }

    /**
     * Table names on the default connection, without the table prefix
     *
     * @return array<int, string>
     */
    protected function tables(): array
    {
        $prefix = DB::getTablePrefix();
        $tables = [];

        // Not Schema::getTables() on MySQL: it reads every schema on the server, with sizes,
        // which takes minutes on a server holding other large databases
        $names = (DB::connection()->getDriverName() === 'mysql')
            ? array_map(fn($row) => (string) array_values((array) $row)[0], DB::select('SHOW TABLES'))
            : array_column(Schema::getTables(), 'name');

        foreach($names as $name) {

            if($prefix !== '' && !str_starts_with($name, $prefix)) {
                continue;
            }

            $tables[] = substr($name, strlen($prefix));
        }

        return $tables;
    }

    /**
     * Whether a table was created before the cutoff; NULL when the database does not say
     *
     * @param string $table Name without the table prefix
     * @return bool|null
     */
    protected function tableCreatedBeforeCutoff(string $table): ?bool
    {
        if(DB::connection()->getDriverName() !== 'mysql') {
            return NULL;
        }

        $created = DB::selectOne(
            'SELECT CREATE_TIME AS created FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [DB::connection()->getDatabaseName(), DB::getTablePrefix() . $table]
        )?->created;

        return $created ? ($created < $this->cutoff) : NULL;
    }

    /**
     * @param string $value
     * @param array<int, string> $prefixes
     * @return bool
     */
    protected static function hasPrefix(string $value, array $prefixes): bool
    {
        foreach($prefixes as $prefix) {
            if(str_starts_with($value, $prefix)) {
                return TRUE;
            }
        }

        return FALSE;
    }

    protected static function processIsRunning(int $pid): bool
    {
        if($pid < 1) {
            return FALSE;
        }

        if(function_exists('posix_kill')) {
            return posix_kill($pid, 0) || posix_get_last_error() === 1; // 1: EPERM, alive but not ours
        }

        return is_dir('/proc/' . $pid);
    }
}
