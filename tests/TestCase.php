<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
    
class TestCase extends BaseTestCase
{
    /**
     * The base URL to use while testing the application.
     *
     * @var string
     */
    protected $baseUrl = 'http://localhost';
    protected $use_named_bindings = FALSE;
    protected $test_http = FALSE;

    /**
     * Whether createApplication() lifts the daily API hit cap for this test class.
     * Set FALSE on tests that need to exercise the configured limit itself.
     *
     * @var bool
     */
    protected $lift_daily_access_limit = TRUE;

    /**
     * Cleanups registered with deferCleanup(), run last in, first out by tearDown()
     *
     * @var array<int, callable>
     */
    private array $deferred_cleanups = [];
    
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        ini_set('memory_limit','512M');
        set_exception_handler([new \Symfony\Component\ErrorHandler\ErrorHandler(), 'handleException']);
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        \Illuminate\Foundation\Bootstrap\HandleExceptions::flushState();
        $this->use_named_bindings = config('app.query_use_named_placeholders');
        $this->test_http = config('app.test_http');

        // Lift the daily API hit cap for tests. A suite run issues far more requests than a
        // real day's allowance, and once ip_access_log.limit_reached is set for the day every
        // later API request 429s - silently turning the API tests into skips until midnight.
        // 0 means unlimited (see IpAccess::getAccessLimit), and it also neutralises a flag
        // already set by an earlier run, since both limit checks are guarded on $limit > 0.
        // This must happen after the kernel bootstraps: bss.daily_access_limit is a DB-backed
        // soft config that LoadSoftConfiguration re-applies on every boot, so an env override
        // in phpunit.xml would be overwritten here.
        //
        // Tests that assert on limit enforcement opt out via $lift_daily_access_limit: they
        // work against freshly created fake IPs / keys rather than the client IP the HTTP
        // tests share, so they cannot trip the flag for the rest of the suite.
        if($this->lift_daily_access_limit) {
            config(['bss.daily_access_limit' => 0]);
        }

        return $app;
    }

    public function tearDown(): void
    {
        $this->beforeApplicationDestroyed(function () {
            \DB::disconnect();
        });

        try {
            $this->runDeferredCleanups();
        }
        finally {
            parent::tearDown();
        }
    }

    /**
     * Registers a cleanup for tearDown(), which PHPUnit runs whether the test passed, failed or
     * errored.  A try / finally in the test body only covers the code inside it; this also
     * covers a fixture whose creation threw part way.
     *
     * Register the cleanup BEFORE creating the fixture, and make it idempotent - delete by
     * module, code or key, whether or not the thing exists - so it is safe however far the
     * creation got.  Cleanups run last in, first out, while the application is still up.
     *
     * @param callable $cleanup
     */
    protected function deferCleanup(callable $cleanup): void
    {
        $this->deferred_cleanups[] = $cleanup;
    }

    /**
     * deferCleanup() for one link of a #[Depends] chain: the cleanup only runs if this test did
     * not pass.  A failure skips the rest of the chain, so the fixture it shares is removed
     * here; when every link passes, the last one removes it with a plain deferCleanup().
     *
     * @param callable $cleanup
     */
    protected function deferCleanupUnlessPassed(callable $cleanup): void
    {
        $this->deferCleanup(function() use ($cleanup) {
            if(!$this->status()->isSuccess()) {
                $cleanup();
            }
        });
    }

    /**
     * Puts the given columns of a real (installed, not throwaway) record back as they are now,
     * in tearDown, however the test ends.  Raw column values are written straight back, past
     * any accessor or mutator, so the stored data is left exactly as it was.
     *
     * @param \Illuminate\Database\Eloquent\Model $Model
     * @param array<int, string> $columns
     */
    protected function restoreColumnsLater(\Illuminate\Database\Eloquent\Model $Model, array $columns): void
    {
        $values = array_intersect_key($Model->getRawOriginal(), array_flip($columns));
        $this->deferCleanup(fn() => $Model->newQuery()->whereKey($Model->getKey())->update($values));
    }

    /**
     * Runs every deferred cleanup, newest first.  One that throws does not stop the rest; the
     * first error is rethrown once all have run, so a broken cleanup still fails the test.
     *
     * @throws \Throwable
     */
    protected function runDeferredCleanups(): void
    {
        $error = NULL;

        while($cleanup = array_pop($this->deferred_cleanups)) {
            try {
                $cleanup();
            }
            catch(\Throwable $e) {
                $error = $error ?? $e;
            }
        }

        if($error) {
            throw $error;
        }
    }

    /**
     * Names a throwaway Bible or volume module for the calling test: $prefix plus a random
     * suffix, so parallel workers and reruns never share one.  Prefixes the test:cleanup
     * sweep knows are listed in TestCleanup.
     */
    protected function fixtureModule(string $prefix): string
    {
        return $prefix . bin2hex(random_bytes(4));
    }

    /**
     * Creates a throwaway language row for a code no real language uses.
     *
     * Every column the schema requires is supplied: SQLite enforces the NOT NULL on iso_name
     * that a MySQL development database in a non-strict mode quietly fills in.
     */
    protected function createLanguageFixture(string $code, string $name): \App\Models\Language
    {
        $this->removeLanguageFixture($code);

        return \App\Models\Language::create([
            'code'        => $code,
            'name'        => $name,
            'iso_name'    => $name,
            'native_name' => $name,
            'iso_endonym' => $name,
            'family'      => 'Test',
        ]);
    }

    /**
     * Whether the configured database is reached by file path rather than by credentials.
     *
     * Development runs on MySQL and CI runs on SQLite, and the installer checklist reports the
     * two differently: a file database has no host, user or password to show, so those rows are
     * replaced by the file and directory ones. Tests asserting on either shape have to ask which
     * they are looking at.
     */
    protected static function databaseIsFileBased(): bool
    {
        return config('database.connections.' . config('database.default') . '.driver') === 'sqlite';
    }

    /**
     * Names a throwaway table for the calling test.
     *
     * A parallel run puts several test processes on the one shared database, so a fixture table
     * named after its purpose alone is dropped out from under whichever test is using it next.
     * The process id keeps the name unique per worker, and the result is still a bare SQL
     * identifier, safe to interpolate into the raw DDL these fixtures need.
     */
    protected function fixtureTableName(string $purpose): string
    {
        return $purpose . '_' . getmypid();
    }

    /**
     * Names a throwaway rate-limit bucket for the calling test.
     *
     * IpAccess buckets are keyed by domain, so two tests naming the same domain share one row on
     * the one shared database - in a parallel run each sees the other's hits, and whichever fails
     * first leaves the row behind for every later run. The process id keeps the bucket per worker.
     */
    protected function fixtureDomain(string $purpose): string
    {
        return $purpose . getmypid() . '.com';
    }

    /**
     * Deletes a throwaway Bible: its row, its verses table and, with $module_files, its module
     * file in either module directory.  Safe to call whether or not any of them exist, so it
     * can be deferred before the Bible is created.  Never call it for an installed, real module.
     *
     * @param string $module
     * @param bool $module_files Also delete bibles/modules|unofficial/<module>.zip
     */
    protected function removeBibleModuleFixture(string $module, bool $module_files = FALSE): void
    {
        \App\Models\Bible::where('module', $module)->delete();
        \Illuminate\Support\Facades\Schema::dropIfExists('verses_' . $module);

        if($module_files) {
            foreach([\App\Models\Bible::getModulePath(), \App\Models\Bible::getUnofficialModulePath()] as $dir) {
                if(is_file($dir . $module . '.zip')) {
                    unlink($dir . $module . '.zip');
                }
            }
        }
    }

    /**
     * Deletes a throwaway volume: its row, its content table and any backup of that table.
     * Safe to call whether or not they exist, so it can be deferred before the volume is
     * created.  Never call it for a real module.
     *
     * @param string $type Volume type, e.g. 'strongs'
     * @param string $module
     */
    protected function removeVolumeModuleFixture(string $type, string $module): void
    {
        $content_class = \App\Models\Volume::getContentClassName($type);

        foreach(\App\Models\Volume::where('type', $type)->where('module', $module)->get() as $Volume) {
            $Volume->forceDelete();
        }

        if($content_class) {
            $table = $content_class::getContentTableName($type, $module);
            \Illuminate\Support\Facades\Schema::dropIfExists($table);
            \Illuminate\Support\Facades\Schema::dropIfExists($content_class::backupTableName($table));
        }
    }

    /**
     * Cleans up after a Bible import chain (importcheck → import → ... → delete): the imported
     * Bible with its table and module files, and the upload importcheck staged.  Either may be
     * NULL when the chain did not get that far.
     *
     * @param string|null $module Module the chain imported into
     * @param string|null $upload File name importcheck answered with
     */
    protected function removeImportChainFixture(?string $module, ?string $upload): void
    {
        if($module !== NULL && $module !== '') {
            $this->removeBibleModuleFixture($module, TRUE);
        }

        if($upload !== NULL && $upload !== '' && basename($upload) === $upload) {
            foreach(glob(base_path('bibles') . '/*/uploads/' . $upload) ?: [] as $path) {
                unlink($path);
            }
        }
    }

    /**
     * Deletes the IpAccess buckets for a throwaway IP and / or domain, with their access logs,
     * whether or not they exist.  Safe for deferCleanup().
     *
     * @param string|null $ip
     * @param string|null $host Domain without scheme; its 'www.' form is removed too
     */
    protected function removeIpAccessFixture(?string $ip, ?string $host = NULL): void
    {
        $Query = \App\Models\IpAccess::query()->where(function($Query) use ($ip, $host) {
            if($ip !== NULL) {
                $Query->orWhere('ip_address', $ip);
            }

            if($host !== NULL) {
                $Query->orWhereIn('domain', [$host, 'www.' . $host]);
            }
        });

        if($ip === NULL && $host === NULL) {
            return;
        }

        foreach($Query->get() as $Access) {
            $Access->delete(); // Takes its access logs with it
        }
    }

    /**
     * Restores the request-host superglobals a test overwrote.
     *
     * @param array<string, string|null> $snapshot the values captured before the test set its own,
     *                                             NULL meaning the key was not set at all
     */
    protected function restoreRequestHost(array $snapshot): void
    {
        foreach($snapshot as $key => $value) {
            if($value === NULL) {
                unset($_SERVER[$key]);
            }
            else {
                $_SERVER[$key] = $value;
            }
        }
    }

    /**
     * Deletes a throwaway language and any attributes it accumulated, whether or not the row was
     * ever created. Safe to call from a finally that may run before the row exists.
     */
    protected function removeLanguageFixture(string $code): void
    {
        \App\Models\LanguageAttr::where('code', $code)->delete();
        \App\Models\Language::where('code', $code)->delete();
    }

    /**
     * Asserts an API error response is the envelope a failed action answers with, carrying
     * $message and $level.
     *
     * The paths that never reach an engine - an unknown or retired version, an unknown
     * action, a GET on a POST-only action, the production 500 - used to answer with a bare
     * string under a 'Content-Type: application/json' header, so json_decode(), and
     * response.json() in a browser, failed on the error before the message could be read.
     * ApiController::_makeErrorResponse() builds them now.
     *
     * @param \Illuminate\Testing\TestResponse $response
     * @param string $message
     * @param int $level
     */
    protected function assertIsTheApiErrorEnvelope($response, string $message, int $level = 4): void
    {
        $body = json_decode($response->getContent(), TRUE);

        $this->assertSame(
            JSON_ERROR_NONE,
            json_last_error(),
            'The body is not JSON, but the Content-Type says it is: ' . $response->getContent()
        );

        $this->assertIsArray($body);
        $this->assertArrayHasKey('errors', $body);
        $this->assertArrayHasKey('error_level', $body);
        $this->assertContains($message, $body['errors']);
        $this->assertSame($level, $body['error_level']);
        $this->assertStringStartsWith('application/json', $response->headers->get('Content-Type'));
    }

    public function setUp(): void
    {
        parent::setUp();

        if(!config('app.installed')) {
            // Skip all tests if not installed to database
            $this->markTestSkipped('APP NOT INSTALLED TO DATABASE, UNABLE TO TEST!!!');
        }

        // The Engine singleton's static instance survives refreshApplication(), and
        // phpunit.xml disables static-property backups, so one Engine would otherwise be
        // shared by every test in the process - leaking its defaults (data format,
        // page_all) and Bible set across test classes. Reset lazily: the next
        // getInstance() builds a fresh one, so tests that never touch the Engine pay
        // nothing.
        //
        // Every version has a slot of its own - EngineV2 and EngineV3 each redeclare
        // $instance, see the note on EngineV2 - so clearing the base class alone leaves
        // whatever EngineFactory::getEngineInstance() built behind for the next test.
        $this->resetEngineInstances();
    }

    /**
     * Discards the singleton every engine version holds.
     *
     * Config itself does not need this treatment - Laravel rebuilds the application, and with
     * it the config, for every test method - but a static property is not part of the
     * application, and phpunit.xml sets backupStaticProperties="false", so an engine built by
     * one test is still there for the next one unless it is cleared here.
     *
     * A version is only reset if its class exists. The advertised list is configuration and
     * the class behind it is not: adding 'v4' to config/app.php before writing EngineV4 would
     * otherwise fatal in setUp() for every feature test in the suite, rather than failing in
     * whichever test actually wanted that engine. ApiExceptionHandlingTest advertises a
     * version with no class on purpose, to exercise the controller's 500 path.
     */
    protected function resetEngineInstances(): void
    {
        \App\Engine::resetInstance();

        foreach(config('app.api_version_list') as $version) {
            $class = \App\Factories\EngineFactory::getClassName(ltrim($version, 'v'));

            if(class_exists($class)) {
                $class::resetInstance();
            }
        }
    }
}


