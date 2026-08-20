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
        config(['bss.daily_access_limit' => 0]);

        return $app;
    }

    public function tearDown(): void
    {
        $this->beforeApplicationDestroyed(function () {
            \DB::disconnect();
        });

        parent::tearDown();
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
        \App\Engine::resetInstance();
    }
}


